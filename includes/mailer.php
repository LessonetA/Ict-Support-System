<?php
// ═══════════════════════════════════════════════════════════════════════════
//  ICT HELPDESK — Mailer  (single source of truth for email)
//
//  Provides:
//    sendEmail()                — low-level SMTP send via PHPMailer + Brevo
//    emailHtml()                — branded HTML wrapper template
//    emailTicketBlock()         — reusable ticket detail table for emails
//    sendTicketNotification()   — high-level: build + send + log to DB
//
//  Nothing else should define sendEmail() or sendTicketNotification().
//  functions.php and notifications.php must NOT re-declare these.
// ═══════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/email_config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';   // composer PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ───────────────────────────────────────────────────────────────────────────
// 1.  sendEmail() — low-level send
// ───────────────────────────────────────────────────────────────────────────
if (!function_exists('sendEmail')) {

    /**
     * Send one HTML email via Brevo SMTP.
     *
     * @return array{success:bool, error:string|null}
     */
    function sendEmail(string $toEmail, string $toName,
                       string $subject, string $htmlBody): array
    {
        if (!file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
            return [
                'success' => false,
                'error'   => 'PHPMailer not installed. Run: composer require phpmailer/phpmailer',
            ];
        }

        try {
            $mail = new PHPMailer(true);

            // ── Server ───────────────────────────────────────────────────
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = (MAIL_ENCRYPTION === 'ssl')
                                ? PHPMailer::ENCRYPTION_SMTPS
                                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';

            // ── From / To ────────────────────────────────────────────────
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);

            // ── Content ──────────────────────────────────────────────────
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(
                str_replace(['<br>', '<br/>', '</p>', '</div>', '</tr>'], "\n", $htmlBody)
            );

            $mail->send();
            return ['success' => true, 'error' => null];

        } catch (Exception $e) {
            error_log('[Helpdesk Mailer] Failed to send to ' . $toEmail . ': ' . $e->getMessage());
            return ['success' => false, 'error' => $mail->ErrorInfo];
        } catch (\Exception $e) {
            error_log('[Helpdesk Mailer] Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

} // end sendEmail guard


// ───────────────────────────────────────────────────────────────────────────
// 2.  emailHtml() — branded HTML wrapper
// ───────────────────────────────────────────────────────────────────────────
if (!function_exists('emailHtml')) {

    /**
     * Wrap $bodyContent in the ICT Helpdesk branded email shell.
     *
     * @param  string $title       Shown in the header bar and <title>
     * @param  string $preheader   Hidden preview text (shown in inbox list)
     * @param  string $bodyContent Raw HTML for the white card body
     */
    function emailHtml(string $title, string $preheader, string $bodyContent): string
    {
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif;">

<!-- Preheader (hidden inbox preview) -->
<div style="display:none;max-height:0;overflow:hidden;color:#f0f4f8;">{$preheader}</div>

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
<tr><td align="center">
<table width="580" cellpadding="0" cellspacing="0" style="max-width:580px;width:100%;">

  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#0f2040 0%,#1d4ed8 100%);
               border-radius:14px 14px 0 0;padding:28px 36px;text-align:center;">
      <div style="display:inline-block;width:46px;height:46px;background:rgba(255,255,255,.15);
                  border-radius:12px;line-height:46px;font-size:22px;margin-bottom:10px;">🎧</div>
      <h1 style="margin:0;font-size:20px;font-weight:700;color:#fff;letter-spacing:-.5px;">
        ICT Helpdesk System
      </h1>
      <p style="margin:4px 0 0;font-size:12px;color:rgba(255,255,255,.5);font-family:monospace;">
        {$title}
      </p>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style="background:#fff;padding:36px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">
      {$bodyContent}
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#f8fafc;border:1px solid #e2e8f0;border-top:none;
               border-radius:0 0 14px 14px;padding:20px 36px;text-align:center;">
      <p style="margin:0;font-size:12px;color:#94a3b8;">
        This is an automated notification from the ICT Helpdesk System.
      </p>
      <p style="margin:6px 0 0;font-size:11px;color:#cbd5e1;">
        &copy; {$year} ICT Department &middot; Do not reply to this email
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

} // end emailHtml guard


// ───────────────────────────────────────────────────────────────────────────
// 3.  emailTicketBlock() — reusable ticket detail table
// ───────────────────────────────────────────────────────────────────────────
if (!function_exists('emailTicketBlock')) {

    /**
     * Build a styled ticket-details table for use inside emailHtml() body.
     *
     * @param  array $ticket  Row from the tickets JOIN users query
     */
    function emailTicketBlock(array $ticket): string
    {
        $priority = ucfirst($ticket['priority'] ?? 'medium');
        $status   = ucfirst(str_replace('_', ' ', $ticket['status'] ?? 'open'));
        $title    = htmlspecialchars($ticket['title'] ?? '');
        $ticketNo = htmlspecialchars($ticket['ticket_no'] ?? ($ticket['ticket_number'] ?? ''));

        $prioColors = [
            'low'      => '#059669',
            'medium'   => '#d97706',
            'high'     => '#dc2626',
            'critical' => '#7c3aed',
        ];
        $pColor = $prioColors[strtolower($ticket['priority'] ?? 'medium')] ?? '#64748b';

        return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0"
       style="margin:20px 0;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;
              font-family:'Segoe UI',Arial,sans-serif;">
  <tr style="background:#f8fafc;">
    <td colspan="2"
        style="padding:10px 16px;font-size:11px;font-weight:700;color:#64748b;
               text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid #e2e8f0;">
      Ticket details
    </td>
  </tr>
  <tr>
    <td style="padding:10px 16px;font-size:13px;color:#64748b;width:38%;border-bottom:1px solid #f1f5f9;">
      Ticket number
    </td>
    <td style="padding:10px 16px;font-size:13px;font-weight:700;font-family:monospace;
               color:#0f172a;border-bottom:1px solid #f1f5f9;">
      {$ticketNo}
    </td>
  </tr>
  <tr style="background:#fafafa;">
    <td style="padding:10px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;">
      Issue
    </td>
    <td style="padding:10px 16px;font-size:13px;font-weight:600;color:#0f172a;
               border-bottom:1px solid #f1f5f9;">
      {$title}
    </td>
  </tr>
  <tr>
    <td style="padding:10px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;">
      Priority
    </td>
    <td style="padding:10px 16px;border-bottom:1px solid #f1f5f9;">
      <span style="background:{$pColor}20;color:{$pColor};padding:3px 10px;
                   border-radius:99px;font-size:11.5px;font-weight:700;">
        {$priority}
      </span>
    </td>
  </tr>
  <tr style="background:#fafafa;">
    <td style="padding:10px 16px;font-size:13px;color:#64748b;">Status</td>
    <td style="padding:10px 16px;font-size:13px;font-weight:600;color:#0f172a;">{$status}</td>
  </tr>
</table>
HTML;
    }

} // end emailTicketBlock guard


// ───────────────────────────────────────────────────────────────────────────
// 4.  sendTicketNotification() — high-level orchestrator
// ───────────────────────────────────────────────────────────────────────────
if (!function_exists('sendTicketNotification')) {

    /**
     * Fetch ticket + user, build the branded email, send it, and log to DB.
     *
     * @param  mysqli  $conn
     * @param  int     $ticketId
     * @param  string  $event     'new_ticket' | 'reply' | 'status_change' | 'assigned'
     * @param  array   $extra     Optional: ['note'=>'...'] appended below the ticket block
     * @return bool    true if SMTP delivery succeeded
     */
    function sendTicketNotification($conn, int $ticketId, string $event, array $extra = []): bool
    {
        // ── Fetch ticket + submitter ──────────────────────────────────────
        $stmt = mysqli_prepare($conn,
            "SELECT t.*, u.full_name, u.email AS user_email
               FROM tickets t
               LEFT JOIN users u ON t.user_id = u.id
              WHERE t.id = ?");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'i', $ticketId);
        mysqli_stmt_execute($stmt);
        $ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$ticket) return false;

        // ── Subject lines ─────────────────────────────────────────────────
        $tno = $ticket['ticket_no'] ?? $ticket['ticket_number'] ?? "TKT-{$ticketId}";
        $subjects = [
            'new_ticket'    => "[{$tno}] Your support ticket has been received",
            'reply'         => "[{$tno}] New reply on your ticket",
            'status_change' => "[{$tno}] Ticket status updated",
            'assigned'      => "[{$tno}] Ticket assigned to IT staff",
        ];
        $subject = $subjects[$event] ?? "[{$tno}] Update on your ticket";

        // ── Intro message per event ───────────────────────────────────────
        $intros = [
            'new_ticket'    => 'Your support ticket has been received. Our team will respond as soon as possible.',
            'reply'         => 'A new reply has been added to your ticket. Please log in to view it.',
            'status_change' => 'The status of your ticket has been updated. See the details below.',
            'assigned'      => 'Your ticket has been assigned to an IT staff member who will assist you.',
        ];
        $intro = $intros[$event] ?? 'There is an update on your ticket.';

        // ── Optional extra note block ─────────────────────────────────────
        $extraHtml = '';
        if (!empty($extra['note'])) {
            $note      = htmlspecialchars($extra['note']);
            $extraHtml = "<p style='margin:16px 0 0;padding:14px;background:#f0f9ff;
                           border-left:4px solid #0ea5e9;border-radius:0 8px 8px 0;
                           font-size:14px;color:#0c4a6e;'>{$note}</p>";
        }

        // ── View-ticket CTA ───────────────────────────────────────────────
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $link = $base . '/tickets/view.php?id=' . $ticketId;
        $cta  = "<div style='margin-top:24px;text-align:center;'>
                   <a href='{$link}'
                      style='display:inline-block;padding:12px 28px;
                             background:linear-gradient(135deg,#1e3a5f,#0ea5e9);
                             color:#fff;text-decoration:none;border-radius:8px;
                             font-weight:700;font-size:14px;'>View ticket &rarr;</a>
                 </div>";

        // ── Assemble body ─────────────────────────────────────────────────
        $name = htmlspecialchars($ticket['full_name'] ?? 'User');
        $body = "<p style='margin:0 0 16px;font-size:15px;color:#374151;'>
                    Hi <strong>{$name}</strong>,
                  </p>
                  <p style='margin:0 0 20px;font-size:15px;color:#374151;'>{$intro}</p>"
               . emailTicketBlock($ticket)
               . $extraHtml
               . $cta;

        $html = emailHtml("Ticket update — {$tno}", $intro, $body);

        // ── Send ──────────────────────────────────────────────────────────
        $result = sendEmail(
            $ticket['user_email'],
            $ticket['full_name'],
            $subject,
            $html
        );

        // ── Log to email_notifications table (if it exists) ──────────────
        $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'email_notifications'");
        if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
            $status   = $result['success'] ? 'sent' : 'failed';
            $errorStr = $result['error'] ?? '';
            $logStmt  = mysqli_prepare($conn,
                "INSERT INTO email_notifications
                    (ticket_id, recipient_email, subject, status, error_message, sent_at)
                 VALUES (?, ?, ?, ?, ?, NOW())");
            if ($logStmt) {
                mysqli_stmt_bind_param(
                    $logStmt, 'issss',
                    $ticketId, $ticket['user_email'], $subject, $status, $errorStr
                );
                mysqli_stmt_execute($logStmt);
            }
        }

        return $result['success'];
    }

} // end sendTicketNotification guard