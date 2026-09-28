<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
$user = currentUser(); $uid = $user['id'];
$tid  = intval($_GET['id'] ?? 0);

$result = mysqli_query($conn, "SELECT t.*, u.full_name as raised_by, u.email as user_email, u.department, c.name as category FROM tickets t LEFT JOIN users u ON t.user_id=u.id LEFT JOIN categories c ON t.category_id=c.id WHERE t.id=$tid");
$ticket = mysqli_fetch_assoc($result);
if (!$ticket) { header("Location: manage.php"); exit(); }

$isOwner = ($ticket['user_id'] == $uid);
$isAdmin = ($user['role'] === 'admin');
$isStaff = ($user['role'] === 'staff');
if (!$isOwner && !$isAdmin && !$isStaff) { header("Location: manage.php"); exit(); }

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $msg = clean($_POST['message'] ?? '');
    if (!empty($msg)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?,?,?)");
        mysqli_stmt_bind_param($stmt, "iis", $tid, $uid, $msg);
        mysqli_stmt_execute($stmt);
        sendTicketNotification($conn, $tid, 'reply', ['replier'=>$user['name'],'message'=>$msg]);
        logActivity($conn, $uid, 'ticket_reply', "Replied to #{$ticket['ticket_no']}");
        header("Location: view.php?id=$tid&success=Reply+sent");
        exit();
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && ($isAdmin || $isStaff)) {
    $newStatus = clean($_POST['status'] ?? '');
    if (in_array($newStatus, ['open','in_progress','resolved','closed'])) {
        $resolvedAt = in_array($newStatus,['resolved','closed']) ? ", resolved_at=NOW()" : "";
        mysqli_query($conn, "UPDATE tickets SET status='$newStatus'$resolvedAt WHERE id=$tid");
        sendTicketNotification($conn, $tid, 'status_change', ['status'=>$newStatus]);
        logActivity($conn, $uid, 'status_update', "Changed #{$ticket['ticket_no']} to $newStatus");
        $ticket['status'] = $newStatus;
        if (in_array($newStatus,['resolved','closed'])) {
            header("Location: view.php?id=$tid&resolved=1");
            exit();
        }
    }
}

// Handle assign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign']) && $isAdmin) {
    $staff_id = intval($_POST['staff_id'] ?? 0);
    if ($staff_id > 0) {
        mysqli_query($conn, "DELETE FROM assignments WHERE ticket_id=$tid");
        $stmt = mysqli_prepare($conn, "INSERT INTO assignments (ticket_id, staff_id, assigned_by) VALUES (?,?,?)");
        mysqli_stmt_bind_param($stmt, "iii", $tid, $staff_id, $uid);
        mysqli_stmt_execute($stmt);
        $sr = mysqli_query($conn, "SELECT full_name FROM users WHERE id=$staff_id");
        $sn = mysqli_fetch_assoc($sr)['full_name'] ?? '';
        sendTicketNotification($conn, $tid, 'assigned', ['staff_name'=>$sn]);
        mysqli_query($conn, "UPDATE tickets SET status='in_progress' WHERE id=$tid AND status='open'");
        $ticket['status'] = 'in_progress';
        logActivity($conn, $uid, 'ticket_assigned', "Assigned #{$ticket['ticket_no']} to $sn");
    }
}

$rResult = mysqli_query($conn, "SELECT r.*, u.full_name, u.role FROM ticket_replies r LEFT JOIN users u ON r.user_id=u.id WHERE r.ticket_id=$tid ORDER BY r.created_at ASC");
$replies = mysqli_fetch_all($rResult, MYSQLI_ASSOC);
$aResult    = mysqli_query($conn, "SELECT a.*, u.full_name as staff_name FROM assignments a LEFT JOIN users u ON a.staff_id=u.id WHERE a.ticket_id=$tid LIMIT 1");
$assignment = mysqli_fetch_assoc($aResult);
$staffList  = [];
if ($isAdmin) {
    $sResult = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role='staff' ORDER BY full_name");
    $staffList = mysqli_fetch_all($sResult, MYSQLI_ASSOC);
}
$slaInfo = getSLAInfo($conn, $ticket);

// Check if rated
$ratR = mysqli_query($conn, "SELECT * FROM ticket_ratings WHERE ticket_id=$tid AND user_id=$uid");
$myRating = mysqli_fetch_assoc($ratR);

// Average rating for this ticket
$avgRatR = mysqli_query($conn, "SELECT AVG(rating) as avg, COUNT(*) as cnt FROM ticket_ratings WHERE ticket_id=$tid");
$avgRat  = mysqli_fetch_assoc($avgRatR);

// Status timeline
$statusOrder = ['open','in_progress','resolved','closed'];
$currentIdx  = array_search($ticket['status'], $statusOrder);
$statusIcons = ['open'=>'📋','in_progress'=>'⚙️','resolved'=>'✅','closed'=>'🔒'];
$statusLabels= ['open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved','closed'=>'Closed'];

$pageTitle = 'Ticket ' . $ticket['ticket_no'];
include '../includes/header.php';
?>

<?php if (isset($_GET['resolved'])): ?>
<div id="trigger-confetti"></div>
<div class="alert alert-success animate-in">
    <span style="font-size:20px;">🎉</span>
    <div><strong>Ticket Resolved!</strong> Great work. The user has been notified.
        <?php if ($isOwner || $ticket['user_id']==$uid): ?>
        <a href="rate.php?id=<?= $tid ?>" class="btn btn-sm btn-success ms-3"><i class="fas fa-star me-1"></i>Rate Your Experience</a>
        <?php endif; ?>
    </div>
</div>
<?php elseif (isset($_GET['success'])): ?>
<div class="alert alert-success animate-in"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?></div>
<?php endif; ?>

<!-- Back + Export -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="manage.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <a href="export_pdf.php?id=<?= $tid ?>" class="btn btn-sm btn-outline-danger ms-auto" target="_blank"><i class="fas fa-file-pdf me-1"></i>Export PDF</a>
    <?php if ($isOwner && in_array($ticket['status'],['resolved','closed']) && !$myRating): ?>
    <a href="rate.php?id=<?= $tid ?>" class="btn btn-sm btn-warning"><i class="fas fa-star me-1"></i>Rate Support</a>
    <?php endif; ?>
</div>

<!-- STATUS TIMELINE -->
<div class="form-card mb-4 animate-in">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
        <div>
            <h5 style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($ticket['title']) ?></h5>
            <code style="font-size:12px;color:var(--muted);"><?= $ticket['ticket_no'] ?></code>
            <span style="margin-left:10px;"><?= priorityBadge($ticket['priority']) ?></span>
            <span style="margin-left:6px;"><?= statusBadge($ticket['status']) ?></span>
        </div>
        <?php if ($avgRat['cnt'] > 0): ?>
        <div style="text-align:center;background:var(--surface2);border-radius:10px;padding:10px 16px;">
            <div style="font-size:22px;font-weight:700;color:#f59e0b;"><?= number_format($avgRat['avg'],1) ?> ★</div>
            <div style="font-size:11px;color:var(--muted);"><?= $avgRat['cnt'] ?> rating<?= $avgRat['cnt']>1?'s':'' ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Visual Timeline -->
    <div class="ticket-timeline">
        <?php foreach ($statusOrder as $i => $s): 
            $isDone   = $currentIdx > $i;
            $isActive = $currentIdx == $i;
            $cls = $isDone ? 'done' : ($isActive ? 'active' : '');
        ?>
        <div class="timeline-step <?= $cls ?>">
            <div class="ts-dot"><?= $isDone ? '✓' : $statusIcons[$s] ?></div>
            <div class="ts-label"><?= $statusLabels[$s] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- SLA -->
    <?php if ($slaInfo): ?>
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:6px;">
            <span><i class="fas fa-clock me-1"></i>SLA Limit: <?= $slaInfo['hours_allowed'] ?>h</span>
            <?php if (in_array($ticket['status'],['resolved','closed'])): ?>
                <span style="color:var(--success);font-weight:600;">✓ Resolved within SLA</span>
            <?php elseif ($slaInfo['breached']): ?>
                <span style="color:var(--danger);font-weight:600;">⚠ SLA Breached — Deadline: <?= $slaInfo['deadline'] ?></span>
            <?php else: ?>
                <span>Due: <?= $slaInfo['deadline'] ?></span>
            <?php endif; ?>
        </div>
        <div class="sla-track">
            <div class="sla-fill" style="width:<?= $slaInfo['percent'] ?>%;background:<?= $slaInfo['breached']?'#ef4444':($slaInfo['percent']>75?'#f59e0b':'#16a34a') ?>;"></div>
        </div>
        <?php if (!in_array($ticket['status'],['resolved','closed']) && !$slaInfo['breached']): ?>
        <div style="margin-top:10px;background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2);border-radius:8px;padding:8px 14px;display:flex;align-items:center;gap:10px;font-size:13px;">
            <i class="fas fa-clock" style="color:var(--success);"></i>
            <span style="color:var(--success);">Time remaining:</span>
            <strong id="sla-countdown" style="font-family:monospace;color:var(--success);"><?= formatCountdown($slaInfo['remaining_sec']) ?></strong>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Meta -->
    <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);font-size:12.5px;color:var(--muted);">
        <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($ticket['raised_by']) ?></span>
        <span><i class="fas fa-building me-1"></i><?= htmlspecialchars($ticket['department']??'—') ?></span>
        <span><i class="fas fa-tag me-1"></i><?= htmlspecialchars($ticket['category']??'Uncategorized') ?></span>
        <span><i class="fas fa-calendar me-1"></i><?= date('d M Y, H:i',strtotime($ticket['created_at'])) ?></span>
    </div>

    <!-- Description -->
    <div style="background:var(--surface2);border-radius:10px;padding:16px;margin-top:16px;font-size:13.5px;line-height:1.7;white-space:pre-wrap;"><?= htmlspecialchars($ticket['description']) ?></div>
</div>

<div class="row g-4">
    <!-- Replies -->
    <div class="col-lg-8">
        <div class="form-card">
            <h6 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-comments me-2" style="color:var(--primary);"></i>Conversation (<?= count($replies) ?> replies)</h6>
            <?php if (empty($replies)): ?>
                <div style="text-align:center;padding:30px;color:var(--muted);font-size:13px;"><i class="fas fa-comment-slash" style="font-size:28px;display:block;margin-bottom:10px;opacity:0.3;"></i>No replies yet.</div>
            <?php else: foreach ($replies as $i=>$r): $isStaffR = in_array($r['role'],['staff','admin']); ?>
                <div class="reply-box <?= $isStaffR?'staff-reply':'' ?> animate-in" style="animation-delay:<?= $i*0.05 ?>s;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:26px;height:26px;background:<?= $isStaffR?'var(--primary)':'var(--accent)' ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;"><?= strtoupper(substr($r['full_name'],0,1)) ?></div>
                            <strong style="font-size:13px;"><?= htmlspecialchars($r['full_name']) ?></strong>
                            <?php if ($isStaffR): ?><span class="badge bg-primary" style="font-size:10px;">IT Staff</span><?php endif; ?>
                        </div>
                        <span style="font-size:11px;color:var(--muted);"><?= timeAgo($r['created_at']) ?></span>
                    </div>
                    <p style="font-size:13.5px;margin:0;line-height:1.6;"><?= nl2br(htmlspecialchars($r['message'])) ?></p>
                </div>
            <?php endforeach; endif; ?>

            <?php if ($ticket['status']!=='closed'): ?>
            <div style="border-top:1px solid var(--border);padding-top:18px;margin-top:18px;">
                <form method="POST">
                    <label class="form-label">Add Reply <small style="color:var(--muted);">(user is notified by email)</small></label>
                    <textarea name="message" class="form-control" rows="4" placeholder="Type your reply..." required style="resize:vertical;"></textarea>
                    <button type="submit" name="reply" class="btn btn-primary mt-2"><i class="fas fa-paper-plane me-2"></i>Send Reply</button>
                </form>
            </div>
            <?php else: ?>
            <div class="alert alert-secondary mt-3">This ticket is closed. No further replies.</div>
            <?php endif; ?>
        </div>

        <!-- Rating display (if rated) -->
        <?php if ($myRating): ?>
        <div class="form-card mt-3" style="background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.2);">
            <h6 style="font-size:13px;font-weight:600;color:#b45309;margin-bottom:8px;"><i class="fas fa-star me-2"></i>Your Rating</h6>
            <div style="display:flex;gap:4px;margin-bottom:8px;">
                <?php for ($s=1;$s<=5;$s++): ?>
                <span style="font-size:22px;color:<?= $s<=$myRating['rating']?'#f59e0b':'var(--border)' ?>;">★</span>
                <?php endfor; ?>
                <span style="font-size:13px;color:var(--muted);margin-left:8px;align-self:center;"><?= $myRating['rating'] ?>/5</span>
            </div>
            <?php if ($myRating['comment']): ?>
            <p style="font-size:13px;color:var(--text2);font-style:italic;">"<?= htmlspecialchars($myRating['comment']) ?>"</p>
            <?php endif; ?>
        </div>
        <?php elseif ($isOwner && in_array($ticket['status'],['resolved','closed'])): ?>
        <div class="form-card mt-3" style="text-align:center;padding:24px;">
            <p style="color:var(--muted);font-size:13.5px;margin-bottom:14px;">How was the support you received? Your feedback matters!</p>
            <a href="rate.php?id=<?= $tid ?>" class="btn btn-warning btn-lg"><i class="fas fa-star me-2"></i>Rate Your Experience</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Panel -->
    <div class="col-lg-4">
        <?php if ($isAdmin||$isStaff): ?>
        <div class="form-card mb-3">
            <h6 style="font-weight:700;margin-bottom:14px;font-size:13px;"><i class="fas fa-edit me-2" style="color:var(--primary);"></i>Update Status</h6>
            <form method="POST">
                <select name="status" class="form-select mb-2">
                    <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $ticket['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="update_status" class="btn btn-primary w-100">Update Status</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="form-card mb-3">
            <h6 style="font-weight:700;margin-bottom:14px;font-size:13px;"><i class="fas fa-user-check me-2" style="color:var(--primary);"></i>Assign Ticket</h6>
            <?php if ($assignment): ?>
            <div style="background:rgba(59,130,246,0.08);border-radius:8px;padding:10px 14px;font-size:12.5px;margin-bottom:12px;">
                <i class="fas fa-user-tie me-2" style="color:var(--primary);"></i>Assigned to <strong><?= htmlspecialchars($assignment['staff_name']) ?></strong>
            </div>
            <?php endif; ?>
            <form method="POST">
                <select name="staff_id" class="form-select mb-2">
                    <option value="">— Select IT Staff —</option>
                    <?php foreach ($staffList as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ($assignment&&$assignment['staff_id']==$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="assign" class="btn btn-outline-primary w-100">Assign</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="form-card" style="background:var(--surface2);">
            <h6 style="font-weight:700;font-size:13px;margin-bottom:14px;"><i class="fas fa-info-circle me-2" style="color:var(--muted);"></i>Ticket Info</h6>
            <?php
            $infoRows = [
                ['Status', statusBadge($ticket['status'])],
                ['Priority', priorityBadge($ticket['priority'])],
                ['Category', htmlspecialchars($ticket['category']??'—')],
                ['SLA Limit', $slaInfo?$slaInfo['hours_allowed'].'h':'—'],
                ['SLA Deadline', $slaInfo?'<span style="font-size:11.5px;">'.$slaInfo['deadline'].'</span>':'—'],
                ['Created', date('d M Y',strtotime($ticket['created_at']))],
                ['Replies', count($replies)],
            ];
            foreach ($infoRows as [$label,$value]):
            ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:12.5px;">
                <span style="color:var(--muted);"><?= $label ?></span>
                <span><?= $value ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$remainSec = ($slaInfo && !in_array($ticket['status'],['resolved','closed']) && !$slaInfo['breached']) ? $slaInfo['remaining_sec'] : 0;
$extraScript = "<script>startSLACountdown('sla-countdown',$remainSec);</script>";
include '../includes/footer.php';
?>
