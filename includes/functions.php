<?php
// ═══════════════════════════════════════════════════════════════════════════
//  ICT HELPDESK — Core Helper Functions
//
//  What lives here:  ticket helpers, SLA, badges, stats, activity log,
//                    announcements, utility functions.
//
//  What does NOT live here:  sendEmail(), sendTicketNotification(),
//                             emailHtml(), emailTicketBlock().
//                             All email logic is in mailer.php.
// ═══════════════════════════════════════════════════════════════════════════

if (!defined('BASE_URL')) {
    define('BASE_URL', '/helpdesk_v4/');
}

// Email + notification functions live here — loaded once, used everywhere
require_once __DIR__ . '/mailer.php';


// ── Ticket number generator ───────────────────────────────────────────────
function generateTicketNo($conn): string
{
    $year = date('Y');
    $r    = mysqli_query($conn,
        "SELECT COUNT(*) AS cnt FROM tickets WHERE YEAR(created_at) = {$year}");
    $seq  = str_pad((int) mysqli_fetch_assoc($r)['cnt'] + 1, 4, '0', STR_PAD_LEFT);
    return "TKT-{$year}{$seq}";
}


// ── Input sanitisation ────────────────────────────────────────────────────
function clean(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)));
}


// ── Badge renderers ───────────────────────────────────────────────────────
function priorityBadge(string $p): string
{
    $map = [
        'low'      => 'success',
        'medium'   => 'warning',
        'high'     => 'danger',
        'critical' => 'dark',
    ];
    return "<span class='badge bg-" . ($map[$p] ?? 'secondary') . "'>"
         . ucfirst($p) . "</span>";
}

function statusBadge(string $s): string
{
    $map = [
        'open'        => 'primary',
        'in_progress' => 'warning',
        'resolved'    => 'success',
        'closed'      => 'secondary',
    ];
    $label = ucfirst(str_replace('_', ' ', $s));
    return "<span class='badge bg-" . ($map[$s] ?? 'secondary') . "'>{$label}</span>";
}


// ── Human-readable time ───────────────────────────────────────────────────
function timeAgo(string $dt): string
{
    $d = time() - strtotime($dt);
    if ($d < 60)    return $d . 's ago';
    if ($d < 3600)  return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    return floor($d / 86400) . 'd ago';
}


// ── Category helpers ──────────────────────────────────────────────────────
function getCategories($conn): array
{
    $r = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");
    return $r ? mysqli_fetch_all($r, MYSQLI_ASSOC) : [];
}


// ── Per-user ticket counts ────────────────────────────────────────────────
function getUserStats($conn, int $uid): array
{
    $stats = [];
    foreach (['open', 'in_progress', 'resolved', 'closed'] as $s) {
        $r          = mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM tickets WHERE user_id = {$uid} AND status = '{$s}'");
        $stats[$s]  = (int) mysqli_fetch_assoc($r)['c'];
    }
    $stats['total'] = array_sum($stats);
    return $stats;
}


// ── System-wide ticket counts ─────────────────────────────────────────────
function getAllStats($conn): array
{
    $stats = [];
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tickets");
    $stats['total'] = (int) mysqli_fetch_assoc($r)['c'];

    foreach (['open', 'in_progress', 'resolved', 'closed'] as $s) {
        $r          = mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM tickets WHERE status = '{$s}'");
        $stats[$s]  = (int) mysqli_fetch_assoc($r)['c'];
    }

    $r = mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM users WHERE role = 'user' AND status = 'active'");
    $stats['users'] = (int) mysqli_fetch_assoc($r)['c'];

    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'staff'");
    $stats['staff'] = (int) mysqli_fetch_assoc($r)['c'];

    return $stats;
}


// ── SLA calculation ───────────────────────────────────────────────────────
function getSLAInfo($conn, array $ticket): ?array
{
    $priority = mysqli_real_escape_string($conn, $ticket['priority']);
    $r        = mysqli_query($conn,
        "SELECT * FROM sla_settings WHERE priority = '{$priority}'");
    $sla      = $r ? mysqli_fetch_assoc($r) : null;
    if (!$sla) return null;

    $created   = strtotime($ticket['created_at']);
    $total     = (int) $sla['hours_to_resolve'] * 3600;
    $deadline  = $created + $total;
    $now       = time();
    $elapsed   = $now - $created;
    $remaining = $deadline - $now;
    $percent   = min(100, (int) round(($elapsed / $total) * 100));

    return [
        'hours_allowed' => (int) $sla['hours_to_resolve'],
        'respond_hours' => (int) $sla['hours_to_respond'],
        'deadline'      => date('d M Y H:i', $deadline),
        'remaining_sec' => $remaining,
        'percent'       => $percent,
        'breached'      => ($remaining < 0 && !in_array($ticket['status'], ['resolved', 'closed'])),
        'status'        => $ticket['status'],
    ];
}


// ── SLA progress bar HTML ─────────────────────────────────────────────────
function slaBar(?array $slaInfo): string
{
    if (!$slaInfo) return '';

    if (in_array($slaInfo['status'], ['resolved', 'closed'])) {
        return "<div style='font-size:12px;color:var(--success);'>
                  <i class='fas fa-check-circle me-1'></i>Resolved within SLA
                </div>";
    }

    $color = $slaInfo['breached']
        ? '#ef4444'
        : ($slaInfo['percent'] > 75 ? '#f59e0b' : '#16a34a');

    $label = $slaInfo['breached']
        ? "<span style='color:#ef4444;font-weight:600;'>SLA BREACHED</span>"
        : "Due: {$slaInfo['deadline']}";

    return "<div style='margin-top:6px;'>
              <div style='display:flex;justify-content:space-between;
                          font-size:12px;color:var(--muted);margin-bottom:4px;'>
                <span>SLA ({$slaInfo['hours_allowed']}h limit)</span>{$label}
              </div>
              <div class='sla-track'>
                <div class='sla-fill'
                     style='width:{$slaInfo['percent']}%;background:{$color};'></div>
              </div>
            </div>";
}


// ── Countdown formatter ───────────────────────────────────────────────────
function formatCountdown(int $seconds): string
{
    if ($seconds < 0) return 'BREACHED';
    return sprintf('%02dh %02dm %02ds',
        floor($seconds / 3600),
        floor(($seconds % 3600) / 60),
        $seconds % 60
    );
}


// ── Activity logger ───────────────────────────────────────────────────────
function logActivity($conn, int $uid, string $action, string $desc = ''): void
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = mysqli_prepare($conn,
        "INSERT INTO activity_log (user_id, action, description, ip_address)
         VALUES (?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'isss', $uid, $action, $desc, $ip);
        mysqli_stmt_execute($stmt);
    }
}


// ── Active announcements ──────────────────────────────────────────────────
function getAnnouncements($conn): array
{
    $r = mysqli_query($conn,
        "SELECT a.*, u.full_name
           FROM announcements a
           LEFT JOIN users u ON a.admin_id = u.id
          WHERE (a.expires_at IS NULL OR a.expires_at >= CURDATE())
          ORDER BY a.created_at DESC
          LIMIT 5");
    return $r ? mysqli_fetch_all($r, MYSQLI_ASSOC) : [];
}