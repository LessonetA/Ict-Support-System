<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
global $conn;
$user   = currentUser(); $uid = $user['id'];
$stats  = getUserStats($conn, $uid);
$pageTitle = 'My Dashboard';
$result  = mysqli_query($conn,"SELECT t.*, c.name as category FROM tickets t LEFT JOIN categories c ON t.category_id=c.id WHERE t.user_id=$uid ORDER BY t.created_at DESC LIMIT 8");
$tickets = mysqli_fetch_all($result,MYSQLI_ASSOC);
$slaR    = mysqli_query($conn,"SELECT COUNT(t.id) as c FROM tickets t LEFT JOIN sla_settings s ON s.priority=t.priority WHERE t.user_id=$uid AND t.status NOT IN ('resolved','closed') AND TIMESTAMPDIFF(HOUR,t.created_at,NOW()) > s.hours_to_resolve");
$breached= mysqli_fetch_assoc($slaR)['c']??0;
include '../includes/header.php';
?>

<?php if($breached>0): ?>
<div class="alert alert-danger animate-in critical-pulse"><i class="fas fa-fire me-2"></i><strong><?= $breached ?> ticket<?= $breached>1?'s':'' ?> exceeded their SLA deadline.</strong> Our IT team has been alerted and is prioritising your issue.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach([['total',$stats['total'],'blue','#3b82f6','fa-ticket-alt'],['open',$stats['open'],'orange','#f59e0b','fa-exclamation-circle'],['in_progress',$stats['in_progress'],'purple','#9333ea','fa-spinner'],['resolved',$stats['resolved'],'green','#16a34a','fa-check-circle']] as [$k,$v,$cls,$col,$icon]): ?>
    <div class="col-md-3 col-6"><div class="stat-card <?= $cls ?> animate-in">
        <div class="stat-icon-box" style="background:<?= $col ?>18;"><i class="fas <?= $icon ?>" style="color:<?= $col ?>;"></i></div>
        <div><div class="stat-value" data-count="<?= $v ?>">0</div><div class="stat-label"><?= ucfirst(str_replace('_',' ',$k)) ?></div></div>
    </div></div>
    <?php endforeach; ?>
</div>

<div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="../tickets/create.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Raise New Ticket</a>
    <a href="../tickets/manage.php" class="btn btn-outline-secondary"><i class="fas fa-list-alt me-2"></i>All My Tickets</a>
    <a href="../knowledge/index.php" class="btn btn-outline-info"><i class="fas fa-book me-2"></i>Knowledge Base</a>
</div>

<div class="table-card animate-in delay-2">
    <div class="table-card-header"><h6><i class="fas fa-ticket-alt me-2" style="color:var(--primary);"></i>Recent Tickets</h6></div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Ticket #</th><th>Title</th><th>Category</th><th>Priority</th><th>Status</th><th>SLA</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php if(empty($tickets)): ?>
                <tr><td colspan="8" style="text-align:center;padding:48px;color:var(--muted);"><i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.2;"></i>No tickets yet.<br><a href="../tickets/create.php" class="btn btn-primary btn-sm mt-3">Raise your first ticket</a></td></tr>
            <?php else: foreach($tickets as $t):
                $sla=getSLAInfo($conn,$t);
                $slaLabel='—';$slaCol='var(--muted)';
                if($sla){if(in_array($t['status'],['resolved','closed'])){$slaLabel='✓ Done';$slaCol='var(--success)';}elseif($sla['breached']){$slaLabel='BREACHED';$slaCol='var(--danger)';}elseif($sla['percent']>75){$slaLabel='At Risk';$slaCol='var(--warning)';}else{$slaLabel='On Track';$slaCol='var(--success)';}}
            ?>
            <tr>
                <td><code><?= $t['ticket_no'] ?></code></td>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($t['title']) ?></td>
                <td style="font-size:12.5px;"><?= htmlspecialchars($t['category']??'—') ?></td>
                <td><?= priorityBadge($t['priority']) ?></td>
                <td><?= statusBadge($t['status']) ?></td>
                <td><span style="font-size:12px;font-weight:600;color:<?= $slaCol ?>;"><?= $slaLabel ?></span></td>
                <td style="color:var(--muted);font-size:12px;"><?= timeAgo($t['created_at']) ?></td>
                <td>
                    <a href="../tickets/view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                    <?php if(in_array($t['status'],['resolved','closed'])): ?>
                    <a href="../tickets/rate.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-warning ms-1" title="Rate support"><i class="fas fa-star"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
