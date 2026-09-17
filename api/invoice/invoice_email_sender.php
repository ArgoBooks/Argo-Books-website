<?php
/**
 * Invoice Email Sender
 *
 * Handles sending invoice emails via SMTP relay (Resend) when configured,
 * with fallback to PHP's native mail() function.
 */

require_once __DIR__ . '/../../smtp_mailer.php';

class InvoiceEmailSender
{
    private string $defaultFromEmail;
    private string $defaultFromName;
    private bool $logEnabled;
    private string $logFile;

    public function __construct()
    {
        $this->defaultFromEmail = env('INVOICE_DEFAULT_FROM_EMAIL', '') ?: 'noreply@argorobots.com';
        $this->defaultFromName = env('INVOICE_DEFAULT_FROM_NAME', 'Argo Books');
        $this->logEnabled = filter_var(env('INVOICE_LOG_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        $this->logFile = env('INVOICE_LOG_FILE', __DIR__ . '/../../logs/invoice_emails.log');
    }

    /**
     * Send an invoice email
     *
     * @param array $data Email data from API request
     * @return array Result with success, message, messageId, and timestamp
     */
    public function send(array $data): array
    {
        $timestamp = date('c');

        try {
            $sender = pin_client_email_sender($data, $this->defaultFromEmail, $this->defaultFromName);
            $fromEmail = $sender['fromEmail'];
            $fromName = $sender['fromName'];
            $data['replyTo'] = $sender['replyTo'];
            $toEmail = $data['to'];
            $toName = $data['toName'] ?? '';
            $subject = $data['subject'];
            $htmlBody = $data['html'];
            $textBody = $data['text'] ?? strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
            [$htmlBody, $textBody] = $this->withLoopFooter($htmlBody, $textBody);

            // Strip CR/LF + control bytes from every value that ends up in an
            // email header. The desktop client supplies subject / to / from /
            // replyTo / bcc and a malicious payload could otherwise inject
            // Bcc: or other headers via the mail() fallback path.
            $headerSafe = static fn($v) => preg_replace('/[\r\n\x00-\x1f]+/', ' ', (string) $v);
            $fromEmail = $headerSafe($fromEmail);
            $fromName = $headerSafe($fromName);
            $toEmail = $headerSafe($toEmail);
            $toName = $headerSafe($toName);
            $subject = $headerSafe($subject);
            if (!empty($data['replyTo'])) {
                $data['replyTo'] = $headerSafe($data['replyTo']);
            }
            if (!empty($data['bcc'])) {
                $data['bcc'] = $headerSafe($data['bcc']);
            }

            // Generate unique message ID
            $messageId = $this->generateMessageId($data['invoiceId'] ?? 'invoice');

            // Try SMTP relay first
            $mailer = create_smtp_mailer();
            if ($mailer) {
                $result = $this->sendViaSMTP($mailer, $toEmail, $toName, $fromEmail, $fromName, $subject, $htmlBody, $data, $messageId);
            } else {
                // Fall back to mail()
                $result = $this->sendViaMail($toEmail, $toName, $fromEmail, $fromName, $subject, $htmlBody, $textBody, $data, $messageId);
            }

            if ($result) {
                $this->log('Email sent successfully', [
                    'to' => $toEmail,
                    'subject' => $subject,
                    'messageId' => $messageId,
                    'invoiceId' => $data['invoiceId'] ?? 'N/A',
                    'method' => $mailer ? 'smtp' : 'mail'
                ]);

                return [
                    'success' => true,
                    'message' => 'Email sent successfully.',
                    'messageId' => $messageId,
                    'timestamp' => $timestamp
                ];
            } else {
                $this->log('Email sending failed', [
                    'to' => $toEmail,
                    'error' => 'send returned false'
                ], 'ERROR');

                return [
                    'success' => false,
                    'message' => 'Failed to send email.',
                    'messageId' => null,
                    'errorCode' => 'SEND_FAILED',
                    'timestamp' => $timestamp
                ];
            }

        } catch (\Exception $e) {
            $this->log('Email sending failed', [
                'to' => $data['to'] ?? 'unknown',
                'error' => $e->getMessage()
            ], 'ERROR');

            return [
                'success' => false,
                'message' => 'Failed to send email.',
                'messageId' => null,
                'errorCode' => 'SEND_FAILED',
                'timestamp' => $timestamp
            ];
        }
    }

    /**
     * Adds one small line naming Argo Books to the bottom of an invoice email. The person
     * receiving an invoice is often a small business that sends invoices too, and the link is
     * tracked so installs from it show on the referral links page.
     */
    private function withLoopFooter(string $html, string $text): array
    {
        $url = 'https://argorobots.com/downloads/?source=loop-invoice-email';
        $line = '<p style="margin:24px 0 0;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8a8a8a;">'
            . 'Sent with <a href="' . $url . '" style="color:#8a8a8a;text-decoration:underline;">Argo Books</a>, free invoicing and accounting software</p>';

        $bodyEnd = strripos($html, '</body>');
        $html = $bodyEnd === false ? $html . $line : substr($html, 0, $bodyEnd) . $line . substr($html, $bodyEnd);
        $text = rtrim($text) . PHP_EOL . PHP_EOL . 'Sent with Argo Books, free invoicing and accounting software: ' . $url;

        return [$html, $text];
    }

    /**
     * Send email via SMTP relay using PHPMailer
     */
    private function sendViaSMTP($mailer, string $toEmail, string $toName, string $fromEmail, string $fromName, string $subject, string $htmlBody, array $data, string $messageId): bool
    {
        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($toEmail, $toName);
        $mailer->Subject = $subject;
        $mailer->Body = $htmlBody;
        $mailer->MessageID = $messageId;

        if (!empty($data['replyTo'])) {
            $mailer->addReplyTo($data['replyTo']);
        }

        if (!empty($data['bcc'])) {
            $mailer->addBCC($data['bcc']);
        }

        // Handle PDF attachment
        if (!empty($data['pdfAttachment'])) {
            $pdfContent = base64_decode($data['pdfAttachment']);
            if ($pdfContent === false) {
                throw new \Exception('Invalid base64 PDF content');
            }
            // Sanitize filename to prevent header injection or path traversal
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $data['pdfFilename'] ?? 'invoice.pdf');
            if ($filename === null || $filename === '' || !str_ends_with(strtolower($filename), '.pdf')) {
                $filename = 'invoice.pdf';
            }
            $mailer->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
        }

        $mailer->send();
        return true;
    }

    /**
     * Send email via PHP's native mail() function (fallback)
     */
    private function sendViaMail(string $toEmail, string $toName, string $fromEmail, string $fromName, string $subject, string $htmlBody, string $textBody, array $data, string $messageId): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'Message-ID: ' . $messageId,
            'X-Mailer: ArgoBooks/1.0'
        ];

        if (!empty($data['replyTo'])) {
            $headers[] = 'Reply-To: ' . $data['replyTo'];
        }

        if (!empty($data['bcc'])) {
            $headers[] = 'Bcc: ' . $data['bcc'];
        }

        $to = $this->formatAddress($toEmail, $toName);

        if (!empty($data['pdfAttachment'])) {
            // Sanitize filename to prevent header injection or path traversal
            $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $data['pdfFilename'] ?? 'invoice.pdf');
            if ($safe_filename === null || $safe_filename === '' || !str_ends_with(strtolower($safe_filename), '.pdf')) {
                $safe_filename = 'invoice.pdf';
            }
            return $this->sendWithAttachment($to, $subject, $htmlBody, $textBody, $headers, $data['pdfAttachment'], $safe_filename);
        }

        return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }

    /**
     * Send email with PDF attachment using multipart MIME (mail() fallback only)
     */
    private function sendWithAttachment(string $to, string $subject, string $htmlBody, string $textBody, array $baseHeaders, string $pdfBase64, string $filename): bool
    {
        $boundary = bin2hex(random_bytes(16));

        // Remove Content-Type from base headers (we'll set multipart)
        $headers = array_filter($baseHeaders, fn($h) => !str_starts_with($h, 'Content-Type:'));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        // Decode PDF
        $pdfContent = base64_decode($pdfBase64);
        if ($pdfContent === false) {
            throw new \Exception('Invalid base64 PDF content');
        }

        // Build multipart message
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
        $body .= chunk_split(base64_encode($pdfContent)) . "\r\n";

        $body .= "--{$boundary}--";

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Format email address with optional name
     */
    private function formatAddress(string $email, string $name = ''): string
    {
        if (empty($name)) {
            return $email;
        }
        return "{$name} <{$email}>";
    }

    /**
     * Generate a unique message ID
     */
    private function generateMessageId(string $invoiceId): string
    {
        $domain = 'argorobots.com';
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        return "<{$invoiceId}.{$timestamp}.{$random}@{$domain}>";
    }

    /**
     * Log email activity
     */
    private function log(string $message, array $context = [], string $level = 'INFO'): void
    {
        if (!$this->logEnabled) {
            return;
        }

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logLine = "[{$timestamp}] [{$level}] {$message} {$contextStr}\n";

        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}
