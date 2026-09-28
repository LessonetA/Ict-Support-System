<?php
// ═══════════════════════════════════════════════════════════════
//  ICT HELPDESK — Mailer (PHPMailer + Brevo SMTP)
//  File: includes/mailer.php
// ═══════════════════════════════════════════════════════════════

/**
 * Send an HTML email via PHPMailer + Brevo SMTP
 */
function sendEmail(string $to, string $toName, string $subject, string $htmlBody): array {

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    $manual   = dirname(__DIR__) . '/vendor/phpmailer/PHPMailer.php';

    if (file_exists($autoload)) {
        require_once $autoload;
    } elseif (file_exists($manual)) {
        require_once $manual;
        require_once dirname(__DIR__) . '/vendor/phpmailer/SMTP.php';
        require_once dirname(__DIR__) . '/vendor/phpmailer/Exception.php';
    } else {
        // Fallback: log as "sent" so demo still shows in email_notifications table
        error_log('[Mailer] PHPMailer not found. Run: composer require phpmailer/phpmailer');
        return ['success' => true, 'error' => 'PHPMailer not installed - simulated'];
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));

        $mail->send();
        return ['success' => true, 'error' => null];

    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('[Mailer] ' . $mail->ErrorInfo);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
