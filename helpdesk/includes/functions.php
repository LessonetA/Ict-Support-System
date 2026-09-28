<?php
define('BASE_URL', '/helpdesk_v4/');

function generateTicketNo($conn) {
    $year = date('Y');
    $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tickets WHERE YEAR(created_at)=$year");
    $num = str_pad(mysqli_fetch_assoc($r)['cnt'] + 1, 4, '0', STR_PAD_LEFT);
    return "TKT-{$year}{$num}";
}
function clean($data) { return htmlspecialchars(strip_tags(trim($data))); }

function priorityBadge($p) {
    $map = ['low'=>'success','medium'=>'warning','high'=>'danger','critical'=>'dark'];
    return "<span class='badge bg-".($map[$p]??'secondary')."'>".ucfirst($p)."</span>";
}
function statusBadge($s) {
    $map = ['open'=>'primary','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary'];
    $label = ucfirst(str_replace('_',' ',$s));
    return "<span class='badge bg-".($map[$s]??'secondary')."'>{$label}</span>";
}
function timeAgo($dt) {
    $d = time() - strtotime($dt);
    if ($d < 60) return $d."s ago";
    if ($d < 3600) return floor($d/60)."m ago";
    if ($d < 86400) return floor($d/3600)."h ago";
    return floor($d/86400)."d ago";
}
function getCategories($conn) {
    $r = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");
    return mysqli_fetch_all($r, MYSQLI_ASSOC);
}
function getUserStats($conn, $uid) {
    $stats = [];
    foreach (['open','in_progress','resolved','closed'] as $s) {
        $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE user_id=$uid AND status='$s'");
        $stats[$s] = mysqli_fetch_assoc($r)['c'];
    }
    $stats['total'] = array_sum($stats);
    return $stats;
}
function getAllStats($conn) {
    $stats = [];
    $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets"); $stats['total'] = mysqli_fetch_assoc($r)['c'];
    foreach (['open','in_progress','resolved','closed'] as $s) {
        $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE status='$s'"); $stats[$s] = mysqli_fetch_assoc($r)['c'];
    }
    $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='user' AND status='active'"); $stats['users'] = mysqli_fetch_assoc($r)['c'];
    $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='staff'"); $stats['staff'] = mysqli_fetch_assoc($r)['c'];
    return $stats;
}
function getSLAInfo($conn, $ticket) {
    $r = mysqli_query($conn, "SELECT * FROM sla_settings WHERE priority='{$ticket['priority']}'");
    $sla = mysqli_fetch_assoc($r);
    if (!$sla) return null;
    $created  = strtotime($ticket['created_at']);
    $deadline = $created + ($sla['hours_to_resolve'] * 3600);
    $now      = time();
    $remaining = $deadline - $now;
    $elapsed   = $now - $created;
    $total     = $sla['hours_to_resolve'] * 3600;
    $percent   = min(100, round(($elapsed / $total) * 100));
    return [
        'hours_allowed' => $sla['hours_to_resolve'],
        'respond_hours' => $sla['hours_to_respond'],
        'deadline'      => date('d M Y H:i', $deadline),
        'remaining_sec' => $remaining,
        'percent'       => $percent,
        'breached'      => ($remaining < 0 && !in_array($ticket['status'], ['resolved','closed'])),
        'status'        => $ticket['status'],
    ];
}
function slaBar($slaInfo) {
    if (!$slaInfo) return '';
    if (in_array($slaInfo['status'],['resolved','closed'])) return "<div style='font-size:12px;color:var(--success);'><i class='fas fa-check-circle me-1'></i>Resolved within SLA</div>";
    $color = $slaInfo['breached'] ? '#ef4444' : ($slaInfo['percent'] > 75 ? '#f59e0b' : '#16a34a');
    $label = $slaInfo['breached'] ? "<span style='color:#ef4444;font-weight:600;'>SLA BREACHED</span>" : "Due: {$slaInfo['deadline']}";
    return "<div style='margin-top:6px;'><div style='display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:4px;'><span>SLA ({$slaInfo['hours_allowed']}h limit)</span>{$label}</div><div class='sla-track'><div class='sla-fill' style='width:{$slaInfo['percent']}%;background:{$color};'></div></div></div>";
}
function formatCountdown($s) {
    if ($s < 0) return 'BREACHED';
    return sprintf('%02dh %02dm %02ds', floor($s/3600), floor(($s%3600)/60), $s%60);
}
function sendTicketNotification($conn, $ticket_id, $event, $extra = []) {
    // Load SMTP mailer
    $emailConfig = __DIR__ . '/email_config.php';
    if (file_exists($emailConfig)) require_once $emailConfig;

    $r = mysqli_query($conn, "SELECT t.*, u.full_name, u.email as user_email FROM tickets t LEFT JOIN users u ON t.user_id=u.id WHERE t.id=$ticket_id");
    $ticket = mysqli_fetch_assoc($r);
    if (!$ticket) return false;

    $subjects = [
        'new_ticket'    => "[{$ticket['ticket_no']}] New Ticket Submitted",
        'reply'         => "[{$ticket['ticket_no']}] New Reply on Your Ticket",
        'status_change' => "[{$ticket['ticket_no']}] Ticket Status Updated",
        'assigned'      => "[{$ticket['ticket_no']}] Ticket Assigned",
    ];
    $subject = $subjects[$event] ?? "[{$ticket['ticket_no']}] Update";
    $to      = $ticket['user_email'];
    $toName  = $ticket['full_name'];

    // ── Build a rich event message ──────────────────────────────
    $eventMessages = [
        'new_ticket'    => "Your support ticket has been received. Our team will respond shortly.",
        'reply'         => "A new reply has been added to your ticket. Please log in to view it.",
        'status_change' => "The status of your ticket has been updated.",
        'assigned'      => "Your ticket has been assigned to an IT staff member.",
    ];
    $eventMsg  = $eventMessages[$event] ?? "There is an update on your ticket.";
    $statusBadgeColors = ['open'=>'#3b82f6','in_progress'=>'#f59e0b','resolved'=>'#22c55e','closed'=>'#6b7280'];
    $sColor    = $statusBadgeColors[$ticket['status']] ?? '#6b7280';
    $sLabel    = ucfirst(str_replace('_',' ', $ticket['status']));
    $extraNote = !empty($extra['note']) ? "<p style='margin:16px 0 0;padding:14px;background:#f0f9ff;border-left:4px solid #0ea5e9;border-radius:0 8px 8px 0;font-size:14px;color:#0c4a6e;'>{$extra['note']}</p>" : '';

    $content = "
<p style='margin:0 0 16px;font-size:15px;color:#374151;'>Hi <strong>" . htmlspecialchars($toName) . "</strong>,</p>
<p style='margin:0 0 20px;font-size:15px;color:#374151;'>{$eventMsg}</p>

<table width='100%' cellpadding='0' cellspacing='0' style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:20px;'>
  <tr><td style='padding:16px 20px;border-bottom:1px solid #e2e8f0;'>
    <span style='font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;'>Ticket Number</span><br>
    <strong style='font-size:16px;color:#0ea5e9;font-family:monospace;'>{$ticket['ticket_no']}</strong>
  </td></tr>
  <tr><td style='padding:16px 20px;border-bottom:1px solid #e2e8f0;'>
    <span style='font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;'>Subject</span><br>
    <strong style='font-size:14px;color:#1e293b;'>" . htmlspecialchars($ticket['title']) . "</strong>
  </td></tr>
  <tr><td style='padding:16px 20px;border-bottom:1px solid #e2e8f0;'>
    <span style='font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;'>Priority</span><br>
    <strong style='font-size:14px;color:#1e293b;'>" . ucfirst($ticket['priority']) . "</strong>
  </td></tr>
  <tr><td style='padding:16px 20px;'>
    <span style='font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;'>Status</span><br>
    <span style='display:inline-block;margin-top:4px;padding:3px 12px;background:{$sColor};color:#fff;border-radius:99px;font-size:12px;font-weight:600;'>{$sLabel}</span>
  </td></tr>
</table>
{$extraNote}
<div style='margin-top:24px;text-align:center;'>
  <a href='" . (defined('BASE_URL') ? rtrim(BASE_URL,'/') : '') . "/tickets/view.php?id={$ticket['id']}' style='display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#1e3a5f,#0ea5e9);color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;'>View Ticket →</a>
</div>";

    // ── Send via SMTP ───────────────────────────────────────────
    $sent   = false;
    $status = 'failed';
    $error  = null;

    if (function_exists('sendEmail') && function_exists('buildEmailTemplate')) {
        $html   = buildEmailTemplate("Ticket Update — {$ticket['ticket_no']}", $content);
        $result = sendEmail($to, $toName, $subject, $html);
        $sent   = $result['success'];
        $error  = $result['error'];
    } else {
        // Fallback: PHP mail() (only works if server has a local MTA)
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: ICT Helpdesk <helpdesk@ictdesk.local>\r\n";
        $sent    = @mail($to, $subject, "<html><body>{$content}</body></html>", $headers);
        $error   = $sent ? null : 'mail() fallback failed — install PHPMailer';
    }

    $status = $sent ? 'sent' : 'failed';

    // ── Log to DB ───────────────────────────────────────────────
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'email_notifications'");
    if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
        $errorStr = $error ?? '';
        $stmt = mysqli_prepare($conn, "INSERT INTO email_notifications (ticket_id, recipient_email, subject, status, error_message, sent_at) VALUES (?,?,?,?,?, NOW())");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issss", $ticket_id, $to, $subject, $status, $errorStr);
            mysqli_stmt_execute($stmt);
        }
    }
    return $sent;
}
function logActivity($conn, $uid, $action, $desc = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?,?,?,?)");
    if ($stmt) { mysqli_stmt_bind_param($stmt, "isss", $uid, $action, $desc, $ip); mysqli_stmt_execute($stmt); }
}
function getAnnouncements($conn) {
    $r = mysqli_query($conn, "SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON a.admin_id=u.id WHERE (expires_at IS NULL OR expires_at >= CURDATE()) ORDER BY a.created_at DESC LIMIT 5");
    return $r ? mysqli_fetch_all($r, MYSQLI_ASSOC) : [];
}
