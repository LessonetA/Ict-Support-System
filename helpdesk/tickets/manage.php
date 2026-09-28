<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
$user=currentUser(); $uid=$user['id'];
$pageTitle='Tickets';
$fs=clean($_GET['status']??''); $fp=clean($_GET['priority']??''); $q=clean($_GET['search']??'');
$where=[];
if($user['role']==='user') $where[]="t.user_id=$uid";
elseif($user['role']==='staff') $where[]="a.staff_id=$uid";
if($fs) $where[]="t.status='$fs'";
if($fp) $where[]="t.priority='$fp'";
if($q) $where[]="(t.title LIKE '%$q%' OR t.ticket_no LIKE '%$q%')";
$wSQL=$where?'WHERE '.implode(' AND ',$where):'';
$joinSQL=($user['role']==='staff')?"INNER JOIN assignments a ON a.ticket_id=t.id":"LEFT JOIN assignments a ON a.ticket_id=t.id";
$result=mysqli_query($conn,"SELECT t.*,u.full_name as raised_by,c.name as category,su.full_name as assigned_to FROM tickets t LEFT JOIN users u ON t.user_id=u.id LEFT JOIN categories c ON t.category_id=c.id $joinSQL LEFT JOIN users su ON a.staff_id=su.id $wSQL GROUP BY t.id ORDER BY FIELD(t.priority,'critical','high','medium','low'),t.created_at DESC");
$tickets=mysqli_fetch_all($result,MYSQLI_ASSOC);
include '../includes/header.php';
?>
<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;align-items:center;">
    <input type="text" name="search" class="form-control" placeholder="Search ticket # or title..." value="<?=htmlspecialchars($q)?>" style="max-width:280px;">
    <select name="status" class="form-select" style="max-width:160px;">
        <option value="">All Statuses</option>
        <?php foreach(['open','in_progress','resolved','closed'] as $s): ?>
        <option value="<?=$s?>" <?=$fs===$s?'selected':''?>><?=ucfirst(str_replace('_',' ',$s))?></option>
        <?php endforeach; ?>
    </select>
    <select name="priority" class="form-select" style="max-width:150px;">
        <option value="">All Priorities</option>
        <?php foreach(['low','medium','high','critical'] as $p): ?>
        <option value="<?=$p?>" <?=$fp===$p?'selected':''?>><?=ucfirst($p)?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Filter</button>
    <?php if($fs||$fp||$q): ?><a href="manage.php" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
    <?php if($user['role']==='user'): ?><a href="create.php" class="btn btn-primary" style="margin-left:auto;"><i class="fas fa-plus me-1"></i>New Ticket</a><?php endif; ?>
</form>

<div class="table-card animate-in">
    <div class="table-card-header">
        <h6><i class="fas fa-ticket-alt me-2" style="color:var(--primary);"></i><?=$user['role']==='user'?'My Tickets':'All Tickets'?> <span class="badge bg-secondary ms-2"><?=count($tickets)?></span></h6>
        <?php if(in_array($user['role'],['admin','staff'])): ?><a href="../reports/export.php?type=csv<?=$fs?"&status=$fs":''?>" class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i>Export CSV</a><?php endif; ?>
    </div>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Ticket #</th><th>Title</th><?php if($user['role']!=='user'): ?><th>Raised By</th><?php endif; ?><th>Category</th><th>Priority</th><th>Status</th><?php if($user['role']==='admin'): ?><th>Assigned To</th><?php endif; ?><th>SLA</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($tickets)): ?>
            <tr><td colspan="10" style="text-align:center;padding:50px;color:var(--muted);"><i class="fas fa-inbox" style="font-size:36px;display:block;margin-bottom:12px;opacity:0.3;"></i>No tickets found.</td></tr>
        <?php else: foreach($tickets as $t): $sla=getSLAInfo($conn,$t);
            $slaTxt='—'; $slaStyle='';
            if($sla){if(in_array($t['status'],['resolved','closed'])){$slaTxt='✓';$slaStyle='color:var(--success)';}elseif($sla['breached']){$slaTxt='BREACH';$slaStyle='color:var(--danger);font-weight:700';}elseif($sla['percent']>75){$slaTxt='Risk';$slaStyle='color:var(--warning)';}else{$slaTxt='OK';$slaStyle='color:var(--success)';}}
        ?>
        <tr>
            <td><code style="font-size:11.5px;"><?=$t['ticket_no']?></code></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;"><?=htmlspecialchars($t['title'])?></td>
            <?php if($user['role']!=='user'): ?><td style="font-size:12.5px;"><?=htmlspecialchars($t['raised_by'])?></td><?php endif; ?>
            <td style="font-size:12.5px;"><?=htmlspecialchars($t['category']??'—')?></td>
            <td><?=priorityBadge($t['priority'])?></td>
            <td><?=statusBadge($t['status'])?></td>
            <?php if($user['role']==='admin'): ?><td style="font-size:12.5px;"><?=$t['assigned_to']?htmlspecialchars($t['assigned_to']):'<span style="color:var(--muted);">Unassigned</span>'?></td><?php endif; ?>
            <td><span style="font-size:11.5px;font-weight:600;<?=$slaStyle?>"><?=$slaTxt?></span></td>
            <td style="color:var(--muted);font-size:11.5px;"><?=timeAgo($t['created_at'])?></td>
            <td><a href="view.php?id=<?=$t['id']?>" class="btn btn-sm btn-outline-primary">View</a></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
<?php include '../includes/footer.php'; ?>
