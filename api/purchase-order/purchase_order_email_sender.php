<?php
/**
 * Purchase Order Email Sender
 *
 * Sends purchase order emails via SMTP relay (Resend) when configured, with
 * fallback to PHP's native mail(). Plaintext body, PDF attachment.
 *
 * Mirrors InvoiceEmailSender but for plaintext-only purchase order messages.
 */

require_once __DIR__ . '/../../smtp_mailer.php';

class PurchaseOrderEmailSender
{
    private string $defaultFromEmail;
    private string $defaultFromName;
    private bool $logEnabled;
    private string $logFile;

    public function __construct()
    {
        $this->defaultFromEmail = env('PO_DEFAULT_FROM_EMAIL', '') ?: (env('INVOICE_DEFAULT_FROM_EMAIL', '') ?: 'noreply@argorobots.com');
        $this->defaultFromName = env('PO_DEFAULT_FROM_NAME', env('INVOICE_DEFAULT_FROM_NAME', 'Argo Books'));
        $this->logEnabled = filter_var(env('PO_LOG_ENABLED', env('INVOICE_LOG_ENABLED', true)), FILTER_VALIDATE_BOOLEAN);
        $this->logFile = env('PO_LOG_FILE', __DIR__ . '/../../logs/purchase_order_emails.log');
    }

    /**
     * Send a purchase order email.
     *
     * @param array $data Email data from API request
     * @return array Result with success, message, messageId, errorCode, timestamp
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
            $textBody = $data['text'];

            // A purchase order goes to another business that keeps its own books, so the body
            // ends with one line naming Argo Books, tracked like the other document links.
            $textBody = rtrim($textBody) . PHP_EOL . PHP_EOL
                . 'Sent with Argo Books, free accounting software for small businesses: '
                . 'https://argorobots.com/downloads/?source=loop-po-email';

            // Strip CR/LF and control bytes from any value that ends up in a
            // header (Subject, To, From, Reply-To, Cc, Bcc) to prevent header
            // injection via the mail() fallback path.
            $headerSafe = static fn($v) => preg_replace('/[\r\n\x00-\x1f]+/', ' ', (string) $v);
            $fromEmail = $headerSafe($fromEmail);
            $fromName = $headerSafe($fromName);
            $toEmail = $headerSafe($toEmail);
            $toName = $headerSafe($toName);
            $subject = $headerSafe($subject);
            if (!empty($data['replyTo'])) {
                $data['replyTo'] = $headerSafe($data['replyTo']);
            }
            if (!empty($data['cc'])) {
                $data['cc'] = $headerSafe($data['cc']);
            }
            if (!empty($data['bcc'])) {
                $data['bcc'] = $headerSafe($data['bcc']);
            }

            $messageId = $this->generateMessageId($data['purchaseOrderId'] ?? 'po');

            $mailer = create_smtp_mailer();
            if ($mailer) {
                $result = $this->sendViaSMTP($mailer, $toEmail, $toName, $fromEmail, $fromName, $subject, $textBody, $data, $messageId);
            } else {
                $result = $this->sendViaMail($toEmail, $toName, $fromEmail, $fromName, $subject, $textBody, $data, $messageId);
            }

            if ($result) {
                $this->log('Purchase order email sent successfully', [
                    'to' => $toEmail,
                    'subject' => $subject,
                    'messageId' => $messageId,
                    'purchaseOrderId' => $data['purchaseOrderId'] ?? 'N/A',
                    'method' => $mailer ? 'smtp' : 'mail'
                ]);

                return [
                    'success' => true,
                    'message' => 'Email sent successfully.',
                    'messageId' => $messageId,
                    'timestamp' => $timestamp
                ];
            }

            $this->log('Purchase order email sending failed', [
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
        } catch (\Exception $e) {
            $this->log('Purchase order email sending failed', [
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

    private function sendViaSMTP($mailer, string $toEmail, string $toName, string $fromEmail, string $fromName, string $subject, string $textBody, array $data, string $messageId): bool
    {
        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($toEmail, $toName);
        $mailer->Subject = $subject;
        // Plaintext only: do NOT call isHTML(true).
        $mailer->Body = $textBody;
        $mailer->AltBody = $textBody;
        $mailer->MessageID = $messageId;

        if (!empty($data['replyTo'])) {
            $mailer->addReplyTo($data['replyTo']);
        }

        if (!empty($data['cc'])) {
            $mailer->addCC($data['cc']);
        }

        if (!empty($data['bcc'])) {
            $mailer->addBCC($data['bcc']);
        }

        if (!empty($data['pdfAttachment'])) {
            $pdfContent = base64_decode($data['pdfAttachment']);
            if ($pdfContent === false) {
                throw new \Exception('Invalid base64 PDF content');
            }
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $data['pdfFilename'] ?? 'purchase-order.pdf');
            if ($filename === null || $filename === '' || !str_ends_with(strtolower($filename), '.pdf')) {
                $filename = 'purchase-order.pdf';
            }
            $mailer->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');
        }

        $mailer->send();
        return true;
    }

    private function sendViaMail(string $toEmail, string $toName, string $fromEmail, string $fromName, string $subject, string $textBody, array $data, string $messageId): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'Message-ID: ' . $messageId,
            'X-Mailer: ArgoBooks/1.0'
        ];

        if (!empty($data['replyTo'])) {
            $headers[] = 'Reply-To: ' . $data['replyTo'];
        }
        if (!empty($data['cc'])) {
            $headers[] = 'Cc: ' . $data['cc'];
        }
        if (!empty($data['bcc'])) {
            $headers[] = 'Bcc: ' . $data['bcc'];
        }

        $to = $this->formatAddress($toEmail, $toName);

        if (!empty($data['pdfAttachment'])) {
            $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $data['pdfFilename'] ?? 'purchase-order.pdf');
            if ($safeFilename === null || $safeFilename === '' || !str_ends_with(strtolower($safeFilename), '.pdf')) {
                $safeFilename = 'purchase-order.pdf';
            }
            return $this->sendWithAttachment($to, $subject, $textBody, $headers, $data['pdfAttachment'], $safeFilename);
        }

        return mail($to, $subject, $textBody, implode("\r\n", $headers));
    }

    private function sendWithAttachment(string $to, string $subject, string $textBody, array $baseHeaders, string $pdfBase64, string $filename): bool
    {
        $boundary = bin2hex(random_bytes(16));

        // Replace the single-part Content-Type with a multipart envelope.
        $headers = array_filter($baseHeaders, fn($h) => !str_starts_with($h, 'Content-Type:'));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        $pdfContent = base64_decode($pdfBase64);
        if ($pdfContent === false) {
            throw new \Exception('Invalid base64 PDF content');
        }

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
        $body .= chunk_split(base64_encode($pdfContent)) . "\r\n";

        $body .= "--{$boundary}--";

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    private function formatAddress(string $email, string $name = ''): string
    {
        if (empty($name)) {
            return $email;
        }
        return "{$name} <{$email}>";
    }

    private function generateMessageId(string $purchaseOrderId): string
    {
        $domain = 'argorobots.com';
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $idSafe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $purchaseOrderId);
        return "<{$idSafe}.{$timestamp}.{$random}@{$domain}>";
    }

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
