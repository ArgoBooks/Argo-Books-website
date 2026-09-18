<?php

require_once __DIR__ . '/../email_sender.php';
require_once __DIR__ . '/../rate_limit_helper.php';

/**
 * Processes the contact form submission and sends an email
 *
 * @return array Result with 'success', 'message', and 'form_data' keys
 */
function process_contact_form()
{
    // Per IP: the session counter this replaced reset whenever the cookie was dropped.
    if (rate_limit_hit('contact_form', get_client_ip())) {
        return [
            'success' => false,
            'message' => 'Too many submissions. Please wait ' . rate_limit_wait_phrase('contact_form') . ' before trying again.',
            'form_data' => []
        ];
    }

    // Get form data
    $firstName = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
    $lastName = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : 'general';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    $form_data = [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => $email,
        'subject' => $subject,
        'message' => $message
    ];

    // Map subject values to display labels
    $subject_labels = [
        'general' => 'General Inquiry',
        'support' => 'Technical Support',
        'billing' => 'Billing Question',
        'feature' => 'Feature Request',
        'bug' => 'Bug Report',
        'other' => 'Other'
    ];
    $subject_label = isset($subject_labels[$subject]) ? $subject_labels[$subject] : 'General Inquiry';

    // Basic validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($message)) {
        return ['success' => false, 'message' => 'All fields are required.', 'form_data' => $form_data];
    }

    // Validate email format and reject header injection attempts
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.', 'form_data' => $form_data];
    }

    // Send the email via SMTP relay when configured, with mail() fallback
    $safe_first = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $safe_last = htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8');
    $safe_email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    $clean_first = str_replace(["\r", "\n", "\t"], '', $firstName);
    $clean_last = str_replace(["\r", "\n", "\t"], '', $lastName);
    // Category first so the inbox list is scannable when several arrive together.
    $email_subject = "Contact form ({$subject_label}): {$clean_first} {$clean_last}";
    $formatted_message = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

    $body = <<<HTML
        <h1>New Contact Form Submission</h1>
        <p><strong>Name:</strong> {$safe_first} {$safe_last}</p>
        <p><strong>Email:</strong> {$safe_email}</p>
        <p><strong>Category:</strong> {$subject_label}</p>
        <p><strong>Message:</strong></p>
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px; margin-top: 5px;">
            {$formatted_message}
        </div>
        <p style="font-size: 12px; color: #64748b; margin-top: 20px;">Hit Reply and your response goes straight to {$safe_first} at {$safe_email}.</p>
        HTML;

    $to_email = 'contact@argorobots.com';

    // The address stays noreply@argorobots.com because that is what SPF and DKIM
    // authorise this server to send as; only the display name changes, so the inbox
    // list says what the message is instead of "Argo Books" emailing itself.
    // setFrom() is skipped entirely when from_email is null, so the name has to be
    // passed alongside the address to take effect on the SMTP path.
    $preview = trim(preg_replace('/\s+/', ' ', strip_tags($message)));
    if (strlen($preview) > 140) {
        $preview = substr($preview, 0, 137) . '...';
    }
    $preheader = "{$clean_first} {$clean_last} ({$subject_label}): {$preview}";

    $mail_result = send_styled_email(
        $to_email,
        $email_subject,
        $body,
        '',
        'noreply@argorobots.com',
        'Argo Books Contact Form',
        $email,
        [],
        $preheader
    );

    if ($mail_result) {
        return ['success' => true];
    }
    return ['success' => false, 'message' => 'Failed to send message. Please try again or contact support directly.', 'form_data' => $form_data];
}

