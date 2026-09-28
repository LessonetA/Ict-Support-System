<?php
// ============================================================
// notifications.php — All email notification templates
// Every event that triggers an email lives here
// ============================================================
require_once __DIR__ . '/mailer.php';

/**
 * Master dispatcher — call this everywhere
 * $event: 'new_ticket' | 'ticket_reply' | 'status_change' |
 *         'ticket_assigned' | 'account_approved' | 'account_rejected'
 */
function sendTicketNotification($conn, int $ticketId, string $event, array $extra = []) : bool {
    // Fetch ticket + user details
    $r = mysqli_query($conn, "SELECT t.*, u.full_name, u.email as user_email, c.name as category
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.id = {$ticketId}");
    $ticket = mysqli_fetch_assoc($r);
    if (!$ticket) return false;

    $result = false;

    switch ($event) {
        case 'new_ticket':
            $result = emailNewTicket($ticket);
            break;
        case 'ticket_reply':
            $result = emailTicketReply($ticket, $extra['replier'] ?? 'IT Support', $extra['message'] ?? '');
            break;
        case 'status_change':
            $result = emailStatusChange($ticket, $extra['status'] ?? $ticket['status']);
            break;
        case 'ticket_assigned':
            $result = emailTicketAssigned($ticket, $extra['staff_name'] ?? 'IT Staff');
            break;
    }

    // Log to database regardless of send result
    logEmailNotification($conn, $ticketId, $ticket['user_email'], $event, $result ? 'sent' : 'failed');
    return $result;
}

// ── EVENT: New Ticket Created ─────────────────────────────────
function emailNewTicket(array $ticket) : bool {
    $name  = $ticket['full_name'];
    $body  = emailHtml(
        'Ticket Received',
        "Your support ticket {$ticket['ticket_no']} has been received.",
        "<p style='font-size:15px;color:#0f172a;margin:0 0 6px;'>Hello <strong>{$name}</strong>,</p>
        <p style='font-size:14px;color:#475569;margin:0 0 20px;line-height:1.7;'>
            Your support ticket has been <strong style='color:#059669;'>successfully submitted</strong>. 
            Our IT team will review it and respond as soon as possible.
        </p>"
        . emailTicketBlock($ticket) .
        "<p style='font-size:13px;color:#64748b;margin:20px 0 0;line-height:1.7;'>
            You can track the status of your ticket by logging into the helpdesk portal. 
            You will receive email updates whenever your ticket is updated.
        </p>
        <div style='margin:24px 0 0;text-align:center;'>
            <a href='http://localhost/helpdesk/tickets/view.php?id={$ticket['id']}' 
               style='display:inline-block;background:linear-gradient(135deg,#1d4ed8,#0ea5e9);color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;'>
               View Ticket →
            </a>
        </div>"
    );
    $r = sendEmail($ticket['user_email'], $ticket['full_name'], "[{$ticket['ticket_no']}] Ticket Submitted — {$ticket['title']}", $body);
    return $r['success'];
}

// ── EVENT: Reply Added ────────────────────────────────────────
function emailTicketReply(array $ticket, string $replierName, string $message) : bool {
    $name    = $ticket['full_name'];
    $msgHtml = nl2br(htmlspecialchars($message));
    $body    = emailHtml(
        'New Reply on Your Ticket',
        "IT Support has replied to ticket {$ticket['ticket_no']}.",
        "<p style='font-size:15px;color:#0f172a;margin:0 0 6px;'>Hello <strong>{$name}</strong>,</p>
        <p style='font-size:14px;color:#475569;margin:0 0 20px;line-height:1.7;'>
            <strong>{$replierName}</strong> from IT Support has replied to your ticket.
        </p>
        <div style='background:#f8fafc;border-left:4px solid #1d4ed8;border-radius:0 10px 10px 0;padding:16px 20px;margin:0 0 20px;'>
            <p style='margin:0 0 8px;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;'>Reply from {$replierName}</p>
            <p style='margin:0;font-size:14px;color:#334155;line-height:1.7;'>{$msgHtml}</p>
        </div>"
        . emailTicketBlock($ticket) .
        "<div style='margin:24px 0 0;text-align:center;'>
            <a href='http://localhost/helpdesk/tickets/view.php?id={$ticket['id']}' 
               style='display:inline-block;background:linear-gradient(135deg,#1d4ed8,#0ea5e9);color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;'>
               View Full Conversation →
            </a>
        </div>"
    );
    $r = sendEmail($ticket['user_email'], $ticket['full_name'], "[{$ticket['ticket_no']}] New Reply — {$ticket['title']}", $body);
    return $r['success'];
}

// ── EVENT: Status Changed ─────────────────────────────────────
function emailStatusChange(array $ticket, string $newStatus) : bool {
    $name    = $ticket['full_name'];
    $label   = ucfirst(str_replace('_', ' ', $newStatus));
    $msgs    = [
        'open'        => ['Your ticket is open and awaiting assignment.', '#3b82f6'],
        'in_progress' => ['An IT staff member is actively working on your issue.', '#d97706'],
        'resolved'    => ['Great news — your ticket has been resolved! Please review the solution and rate your experience.', '#059669'],
        'closed'      => ['Your ticket has been closed. Thank you for using the ICT Helpdesk.', '#64748b'],
    ];
    [$statusMsg, $statusColor] = $msgs[$newStatus] ?? ['Your ticket status has been updated.', '#64748b'];
    $rateLink = ($newStatus === 'resolved')
        ? "<div style='margin:20px 0;text-align:center;'><a href='http://localhost/helpdesk/tickets/rate.php?id={$ticket['id']}' style='display:inline-block;background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;'>⭐ Rate Your Experience</a></div>"
        : '';
    $body = emailHtml(
        "Ticket Status: {$label}",
        "Your ticket {$ticket['ticket_no']} status is now {$label}.",
        "<p style='font-size:15px;color:#0f172a;margin:0 0 6px;'>Hello <strong>{$name}</strong>,</p>
        <p style='font-size:14px;color:#475569;margin:0 0 20px;line-height:1.7;'>{$statusMsg}</p>
        <div style='background:{$statusColor}14;border:1px solid {$statusColor}40;border-radius:10px;padding:14px 20px;margin:0 0 20px;text-align:center;'>
            <span style='font-size:13px;font-weight:700;color:{$statusColor};text-transform:uppercase;letter-spacing:.5px;'>Status Updated → {$label}</span>
        </div>"
        . emailTicketBlock($ticket)
        . $rateLink .
        "<div style='margin:24px 0 0;text-align:center;'>
            <a href='http://localhost/helpdesk/tickets/view.php?id={$ticket['id']}' 
               style='display:inline-block;background:linear-gradient(135deg,#1d4ed8,#0ea5e9);color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;'>
               View Ticket →
            </a>
        </div>"
    );
    $r = sendEmail($ticket['user_email'], $ticket['full_name'], "[{$ticket['ticket_no']}] Status Updated: {$label}", $body);
    return $r['success'];
}

// ── EVENT: Ticket Assigned ────────────────────────────────────
function emailTicketAssigned(array $ticket, string $staffName) : bool {
    $name = $ticket['full_name'];
    $body = emailHtml(
        'Ticket Assigned to IT Staff',
        "Your ticket {$ticket['ticket_no']} has been assigned.",
        "<p style='font-size:15px;color:#0f172a;margin:0 0 6px;'>Hello <strong>{$name}</strong>,</p>
        <p style='font-size:14px;color:#475569;margin:0 0 20px;line-height:1.7;'>
            Your ticket has been assigned to <strong style='color:#1d4ed8;'>{$staffName}</strong>, 
            who will be handling your issue. You can expect a response shortly.
        </p>
        <div style='background:#dbeafe;border:1px solid #93c5fd;border-radius:10px;padding:14px 20px;margin:0 0 20px;display:flex;align-items:center;gap:12px;'>
            <span style='font-size:24px;'>👨‍💻</span>
            <div><p style='margin:0;font-size:13px;font-weight:600;color:#1d4ed8;'>Assigned IT Staff</p><p style='margin:2px 0 0;font-size:14px;color:#1e40af;font-weight:700;'>{$staffName}</p></div>
        </div>"
        . emailTicketBlock($ticket) .
        "<div style='margin:24px 0 0;text-align:center;'>
            <a href='http://localhost/helpdesk/tickets/view.php?id={$ticket['id']}' 
               style='display:inline-block;background:linear-gradient(135deg,#1d4ed8,#0ea5e9);color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;'>
               Track Your Ticket →
            </a>
        </div>"
    );
    $r = sendEmail($ticket['user_email'], $ticket['full_name'], "[{$ticket['ticket_no']}] Assigned to {$staffName}", $body);
    return $r['success'];
}

// ── EVENT: Account Approved ───────────────────────────────────
function emailAccountApproved($conn, int $userId) : bool {
    $r    = mysqli_query($conn, "SELECT full_name, email FROM users WHERE id={$userId}");
    $user = mysqli_fetch_assoc($r);
    if (!$user) return false;
    $body = emailHtml(
        'Account Approved',
        'Your ICT Helpdesk account has been approved.',
        "<p style='font-size:15px;color:#0f172a;margin:0 0 6px;'>Hello <strong>{$user['full_name']}</strong>,</p>
        <div style='background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;padding:16px 20px;margin:0 0 20px;text-align:center;'>
            <p style='margin:0;font-size:16px;'>✅ <strong style='color:#065f46;'>Your account has been approved!</strong></p>
        </div>
        <p style='font-size:14px;color:#475569;margin:0 0 20px;line-height:1.7;'>
            You can now log in to the ICT Helpdesk portal and start submitting support tickets.
        </p>
        <div style='margin:24px 0 0;text-align:center;'>
            <a href='http://localhost/helpdesk/' 
               style='display:inline-block;background:linear-gradient(135deg,#059669,#10b981);color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;'>
               Sign In Now →
            </a>
        </div>"
    );
    $r2 = sendEmail($user['email'], $user['full_name'], 'Your ICT Helpdesk Account Has Been Approved', $body);
    return $r2['success'];
}

// ── LOG TO DATABASE ───────────────────────────────────────────
function logEmailNotification($conn, int $ticketId, string $email, string $event, string $status) : void {
    $subject = "Event: {$event} | Ticket ID: {$ticketId}";
    $stmt = mysqli_prepare($conn, "INSERT INTO email_notifications (ticket_id, recipient_email, subject, status) VALUES (?,?,?,?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isss", $ticketId, $email, $subject, $status);
        mysqli_stmt_execute($stmt);
    }
}
