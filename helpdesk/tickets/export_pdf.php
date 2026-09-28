<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
$user=currentUser(); $uid=$user['id'];
$tid=intval($_GET['id']??0);
$result=mysqli_query($conn,"SELECT t.*,u.full_name as raised_by,u.email,u.department,c.name as category FROM tickets t LEFT JOIN users u ON t.user_id=u.id LEFT JOIN categories c ON t.category_id=c.id WHERE t.id=$tid");
$ticket=mysqli_fetch_assoc($result);
if(!$ticket) die("Ticket not found.");
$isOwner=($ticket['user_id']==$uid);
if(!$isOwner&&!in_array($user['role'],['admin','staff'])) die("Access denied.");
$rResult=mysqli_query($conn,"SELECT r.*,u.full_name,u.role FROM ticket_replies r LEFT JOIN users u ON r.user_id=u.id WHERE r.ticket_id=$tid ORDER BY r.created_at ASC");
$replies=mysqli_fetch_all($rResult,MYSQLI_ASSOC);
$aResult=mysqli_query($conn,"SELECT a.*,u.full_name as staff_name FROM assignments a LEFT JOIN users u ON a.staff_id=u.id WHERE a.ticket_id=$tid LIMIT 1");
$assignment=mysqli_fetch_assoc($aResult);
$slaInfo=getSLAInfo($conn,$ticket);
$pColors=['low'=>'#16a34a','medium'=>'#d97706','high'=>'#dc2626','critical'=>'#1e293b'];
$sColors=['open'=>'#2563eb','in_progress'=>'#d97706','resolved'=>'#16a34a','closed'=>'#64748b'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<title>Ticket <?=$ticket['ticket_no']?> — ICT Helpdesk</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;font-size:13px;color:#1e293b;background:#fff;padding:40px;}
.header{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:20px;border-bottom:3px solid #0f4c81;margin-bottom:28px;}
.brand h2{font-family:'Syne',sans-serif;font-size:22px;color:#0f4c81;font-weight:800;}.brand p{font-size:11px;color:#64748b;margin-top:4px;}
.ticket-no{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:#0f4c81;}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px;}
.meta-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;}
.meta-box label{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;display:block;margin-bottom:4px;}
.meta-box span{font-size:13px;font-weight:600;}
.badge{display:inline-block;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:700;color:#fff;}
.section-title{font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:#0f4c81;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;}
.description{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:24px;line-height:1.7;white-space:pre-wrap;}
.reply{border-left:3px solid #00bfae;background:#f8fafc;border-radius:0 8px 8px 0;padding:12px 16px;margin-bottom:12px;}
.reply.staff{border-left-color:#0f4c81;}
.reply-header{display:flex;justify-content:space-between;margin-bottom:6px;font-size:12px;}
.sla-bar{height:6px;background:#e2e8f0;border-radius:4px;margin-top:6px;}
.sla-fill{height:6px;border-radius:4px;}
.footer{margin-top:36px;padding-top:16px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:11px;color:#94a3b8;}
.print-btn{position:fixed;bottom:28px;right:28px;background:#0f4c81;color:#fff;border:none;padding:14px 28px;border-radius:10px;font-size:14px;font-family:'DM Sans',sans-serif;cursor:pointer;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.2);}
@media print{.print-btn{display:none;}body{padding:20px;}}
</style></head><body>
<div class="header">
    <div class="brand"><h2>🎧 ICT Helpdesk System</h2><p>Official Ticket Report — Generated <?=date('d M Y, H:i')?></p></div>
    <div class="ticket-no"><?=$ticket['ticket_no']?></div>
</div>
<div style="margin-bottom:20px;">
    <h3 style="font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:10px;"><?=htmlspecialchars($ticket['title'])?></h3>
    <span class="badge" style="background:<?=$pColors[$ticket['priority']]??'#64748b'?>;"><?=ucfirst($ticket['priority'])?> Priority</span>
    &nbsp;<span class="badge" style="background:<?=$sColors[$ticket['status']]??'#64748b'?>;"><?=ucfirst(str_replace('_',' ',$ticket['status']))?></span>
</div>
<div class="meta-grid">
    <div class="meta-box"><label>Raised By</label><span><?=htmlspecialchars($ticket['raised_by'])?></span></div>
    <div class="meta-box"><label>Department</label><span><?=htmlspecialchars($ticket['department']??'—')?></span></div>
    <div class="meta-box"><label>Category</label><span><?=htmlspecialchars($ticket['category']??'—')?></span></div>
    <div class="meta-box"><label>Date Submitted</label><span><?=date('d M Y, H:i',strtotime($ticket['created_at']))?></span></div>
    <div class="meta-box"><label>Assigned To</label><span><?=$assignment?htmlspecialchars($assignment['staff_name']):'Unassigned'?></span></div>
    <div class="meta-box"><label>SLA Status</label>
        <?php if($slaInfo): ?><span style="color:<?=$slaInfo['breached']?'#dc2626':'#16a34a'?>;"><?=$slaInfo['breached']?'BREACHED':'On Track'?> (<?=$slaInfo['hours_allowed']?>h limit)</span><div class="sla-bar"><div class="sla-fill" style="width:<?=$slaInfo['percent']?>%;background:<?=$slaInfo['breached']?'#ef4444':($slaInfo['percent']>75?'#f59e0b':'#10b981')?>;"></div></div><?php else: ?><span>—</span><?php endif; ?>
    </div>
</div>
<div class="section-title">Issue Description</div>
<div class="description"><?=htmlspecialchars($ticket['description'])?></div>
<div class="section-title">Conversation (<?=count($replies)?> replies)</div>
<?php if(empty($replies)): ?><p style="color:#94a3b8;font-size:13px;margin-bottom:20px;">No replies on this ticket.</p>
<?php else: foreach($replies as $r): $isSR=in_array($r['role'],['staff','admin']); ?>
<div class="reply <?=$isSR?'staff':''?>">
    <div class="reply-header"><strong><?=htmlspecialchars($r['full_name'])?><?=$isSR?' (IT Staff)':''?></strong><span><?=date('d M Y, H:i',strtotime($r['created_at']))?></span></div>
    <p style="line-height:1.6;"><?=nl2br(htmlspecialchars($r['message']))?></p>
</div>
<?php endforeach; endif; ?>
<div class="footer"><span>ICT Helpdesk System — Confidential</span><span>Printed: <?=date('d/m/Y H:i')?></span></div>
<button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
</body></html>
