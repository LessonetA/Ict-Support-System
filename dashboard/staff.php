<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAnyRole(['staff','admin']);
$user=currentUser(); $uid=$user['id'];
$pageTitle='Staff Dashboard';
$result=mysqli_query($conn,"SELECT t.*,u.full_name as raised_by,c.name as category FROM tickets t INNER JOIN assignments a ON a.ticket_id=t.id LEFT JOIN users u ON t.user_id=u.id LEFT JOIN categories c ON t.category_id=c.id WHERE a.staff_id=$uid ORDER BY FIELD(t.priority,'critical','high','medium','low'),t.created_at DESC");
$tickets=mysqli_fetch_all($result,MYSQLI_ASSOC);
$open=array_filter($tickets,fn($t)=>$t['status']==='open');
$inprog=array_filter($tickets,fn($t)=>$t['status']==='in_progress');
$resolved=array_filter($tickets,fn($t)=>in_array($t['status'],['resolved','closed']));
$breachedR=mysqli_query($conn,"SELECT COUNT(t.id) as c FROM tickets t INNER JOIN assignments a ON a.ticket_id=t.id LEFT JOIN sla_settings s ON s.priority=t.priority WHERE a.staff_id=$uid AND t.status NOT IN('resolved','closed') AND TIMESTAMPDIFF(HOUR,t.created_at,NOW())>s.hours_to_resolve");
$breached=mysqli_fetch_assoc($breachedR)['c']??0;
include '../includes/header.php';
?>
<?php if($breached>0): ?>
<div class="alert alert-danger critical-pulse animate-in"><i class="fas fa-fire me-2"></i><strong><?=$breached?> SLA breach<?=$breached>1?'es':''?></strong> in your queue! Address these immediately.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="stat-card blue animate-in"><div class="stat-icon-box" style="background:rgba(59,130,246,0.12);"><i class="fas fa-inbox" style="color:#3b82f6;"></i></div><div><div class="stat-value" data-count="<?=count($tickets)?>">0</div><div class="stat-label">Assigned to Me</div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card orange animate-in delay-1"><div class="stat-icon-box" style="background:rgba(245,158,11,0.12);"><i class="fas fa-clock" style="color:#f59e0b;"></i></div><div><div class="stat-value" data-count="<?=count($open)+count($inprog)?>">0</div><div class="stat-label">Need Attention</div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card green animate-in delay-2"><div class="stat-icon-box" style="background:rgba(22,163,74,0.12);"><i class="fas fa-check-circle" style="color:#16a34a;"></i></div><div><div class="stat-value" data-count="<?=count($resolved)?>">0</div><div class="stat-label">Resolved</div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card <?=$breached>0?'red critical-pulse':''?> animate-in delay-3" style="<?=$breached>0?'border-color:rgba(239,68,68,0.3);':''?>"><div class="stat-icon-box" style="background:rgba(239,68,68,0.12);"><i class="fas fa-fire" style="color:#ef4444;"></i></div><div><div class="stat-value" style="<?=$breached>0?'color:#ef4444;':''?>" data-count="<?=$breached?>">0</div><div class="stat-label">SLA Breached</div></div></div></div>
</div>

<div class="table-card animate-in delay-1">
    <div class="table-card-header"><h6><i class="fas fa-ticket-alt me-2" style="color:var(--primary);"></i>My Assigned Tickets <small style="color:var(--muted);font-size:11px;margin-left:6px;">Sorted by priority</small></h6></div>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Ticket #</th><th>Title</th><th>Raised By</th><th>Priority</th><th>Status</th><th>SLA</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($tickets)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);"><i class="fas fa-clipboard-check" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>No tickets assigned to you yet.</td></tr>
        <?php else: foreach($tickets as $t): $sla=getSLAInfo($conn,$t);
            $slaTxt='—'; $slaStyle='';
            if($sla){if(in_array($t['status'],['resolved','closed'])){$slaTxt='✓ Done';$slaStyle='color:var(--success)';}elseif($sla['breached']){$slaTxt='BREACH';$slaStyle='color:var(--danger);font-weight:700;animation:pulse-badge 2s infinite';}elseif($sla['percent']>75){$slaTxt='At Risk';$slaStyle='color:var(--warning)';}else{$slaTxt='On Track';$slaStyle='color:var(--success)';}}
        ?>
        <tr>
            <td><code><?=$t['ticket_no']?></code></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=htmlspecialchars($t['title'])?></td>
            <td style="font-size:12.5px;"><?=htmlspecialchars($t['raised_by'])?></td>
            <td><?=priorityBadge($t['priority'])?></td>
            <td><?=statusBadge($t['status'])?></td>
            <td><span style="font-size:12px;font-weight:600;<?=$slaStyle?>"><?=$slaTxt?></span></td>
            <td style="color:var(--muted);font-size:12px;"><?=timeAgo($t['created_at'])?></td>
            <td><a href="../tickets/view.php?id=<?=$t['id']?>" class="btn btn-sm btn-outline-primary">Open</a></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
<?php include '../includes/footer.php'; ?>
