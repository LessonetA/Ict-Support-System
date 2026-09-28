<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAnyRole(['admin','staff']);
$pageTitle = 'Summary Report';
$stats = getAllStats($conn);
$statusData = []; foreach(['open','in_progress','resolved','closed'] as $s) { $r=mysqli_query($conn,"SELECT COUNT(*) as c FROM tickets WHERE status='$s'"); $statusData[$s]=mysqli_fetch_assoc($r)['c']; }
$prioData   = []; foreach(['low','medium','high','critical'] as $p) { $r=mysqli_query($conn,"SELECT COUNT(*) as c FROM tickets WHERE priority='$p'"); $prioData[$p]=mysqli_fetch_assoc($r)['c']; }
$catR    = mysqli_query($conn,"SELECT c.name, COUNT(t.id) as cnt FROM categories c LEFT JOIN tickets t ON t.category_id=c.id GROUP BY c.id ORDER BY cnt DESC");
$catData = mysqli_fetch_all($catR,MYSQLI_ASSOC);
$monthR  = mysqli_query($conn,"SELECT DATE_FORMAT(created_at,'%b %Y') as month, DATE_FORMAT(created_at,'%Y-%m') as ym, COUNT(*) as total, SUM(status IN ('resolved','closed')) as resolved FROM tickets WHERE created_at >= DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY ym ORDER BY ym ASC");
$monthData = mysqli_fetch_all($monthR,MYSQLI_ASSOC);
$staffR    = mysqli_query($conn,"SELECT u.full_name, COUNT(a.id) as assigned, SUM(t.status='resolved') as resolved, SUM(t.status='closed') as closed FROM users u LEFT JOIN assignments a ON a.staff_id=u.id LEFT JOIN tickets t ON a.ticket_id=t.id WHERE u.role='staff' GROUP BY u.id ORDER BY assigned DESC");
$staffData = mysqli_fetch_all($staffR,MYSQLI_ASSOC);
$jStatus = json_encode(['Open','In Progress','Resolved','Closed']);
$jStatusC= json_encode(array_values($statusData));
$jPrio   = json_encode(['Low','Medium','High','Critical']);
$jPrioC  = json_encode(array_values($prioData));
$jMonth  = json_encode(array_column($monthData,'month'));
$jMonthT = json_encode(array_column($monthData,'total'));
$jMonthR = json_encode(array_column($monthData,'resolved'));
$jCat    = json_encode(array_column($catData,'name'));
$jCatC   = json_encode(array_column($catData,'cnt'));
include '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div><h6 style="font-family:'Syne',sans-serif;font-weight:700;margin:0;">Summary Report</h6><p style="font-size:12px;color:var(--muted);margin:2px 0 0;">Generated <?= date('d M Y, H:i') ?></p></div>
    <div class="d-flex gap-2">
        <a href="export.php?type=csv" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv me-1"></i>CSV</a>
        <a href="export.php?type=excel" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-excel me-1"></i>Excel</a>
        <a href="analytics.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chart-line me-1"></i>Advanced Analytics</a>
    </div>
</div>
<div class="row g-3 mb-4">
    <?php foreach([['total','Total',$stats['total'],'blue','#3b82f6'],['open','Open',$stats['open'],'orange','#f59e0b'],['in_progress','In Progress',$stats['in_progress'],'purple','#9333ea'],['resolved','Resolved',$stats['resolved'],'green','#16a34a']] as [$k,$l,$v,$cls,$col]): ?>
    <div class="col-md-3 col-6"><div class="stat-card <?= $cls ?> animate-in">
        <div class="stat-icon" style="background:<?= $col ?>20;"><i class="fas fa-ticket-alt" style="color:<?= $col ?>;"></i></div>
        <div><div class="stat-value" data-count="<?= $v ?>">0</div><div class="stat-label"><?= $l ?></div></div>
    </div></div>
    <?php endforeach; ?>
</div>
<div class="row g-4 mb-4">
    <div class="col-md-8"><div class="form-card animate-in"><h6 style="font-size:13px;font-weight:700;margin-bottom:16px;"><i class="fas fa-chart-line me-2" style="color:var(--primary);"></i>Monthly Trend (12 months)</h6><canvas id="monthChart" height="100"></canvas></div></div>
    <div class="col-md-4"><div class="form-card animate-in delay-1"><h6 style="font-size:13px;font-weight:700;margin-bottom:16px;"><i class="fas fa-chart-pie me-2" style="color:var(--primary);"></i>By Status</h6><canvas id="statusChart" height="220"></canvas></div></div>
</div>
<div class="row g-4 mb-4">
    <div class="col-md-6"><div class="form-card animate-in"><h6 style="font-size:13px;font-weight:700;margin-bottom:16px;"><i class="fas fa-chart-bar me-2" style="color:var(--primary);"></i>By Category</h6><canvas id="catChart" height="200"></canvas></div></div>
    <div class="col-md-6"><div class="form-card animate-in delay-1"><h6 style="font-size:13px;font-weight:700;margin-bottom:16px;"><i class="fas fa-medal me-2" style="color:#f59e0b;"></i>Staff Performance</h6>
        <?php if(empty($staffData)): ?><p style="color:var(--muted);font-size:13px;">No staff data yet.</p><?php else: ?>
        <?php foreach($staffData as $i=>$s): $done=($s['resolved']+$s['closed']); $perf=$s['assigned']>0?round($done/max($s['assigned'],1)*100):0; $col=$perf>=75?'var(--success)':($perf>=40?'var(--warning)':'var(--danger)'); ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
            <div style="font-size:20px;"><?= ['🥇','🥈','🥉'][$i]??'·' ?></div>
            <div style="flex:1;">
                <div style="font-weight:600;font-size:13px;margin-bottom:4px;"><?= htmlspecialchars($s['full_name']) ?></div>
                <div style="height:6px;background:var(--border);border-radius:100px;"><div style="width:<?= $perf ?>%;height:6px;background:<?= $col ?>;border-radius:100px;"></div></div>
            </div>
            <div style="text-align:right;font-size:12px;"><div style="font-weight:700;color:<?= $col ?>;"><?= $perf ?>%</div><div style="color:var(--muted);"><?= $done ?>/<?= $s['assigned'] ?></div></div>
        </div>
        <?php endforeach; endif; ?>
    </div></div>
</div>
<?php
$extraScript = "<script>
new Chart(document.getElementById('monthChart'),{type:'line',data:{labels:{$jMonth},datasets:[{label:'Raised',data:{$jMonthT},borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,0.08)',tension:0.4,fill:true,pointRadius:4},{label:'Resolved',data:{$jMonthR},borderColor:'#16a34a',backgroundColor:'rgba(22,163,74,0.06)',tension:0.4,fill:true,pointRadius:4}]},options:{responsive:true,interaction:{mode:'index'},plugins:{legend:{position:'top',labels:{font:{family:'DM Sans',size:12}}}},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(0,0,0,0.04)'}},x:{grid:{display:false}}}}});
new Chart(document.getElementById('statusChart'),{type:'doughnut',data:{labels:{$jStatus},datasets:[{data:{$jStatusC},backgroundColor:['#3b82f6','#f59e0b','#16a34a','#94a3b8'],borderWidth:2,borderColor:'transparent',hoverOffset:6}]},options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{family:'DM Sans',size:11}}}}}});
new Chart(document.getElementById('catChart'),{type:'bar',data:{labels:{$jCat},datasets:[{data:{$jCatC},backgroundColor:'rgba(15,76,129,0.7)',borderRadius:8,borderSkipped:false}]},options:{responsive:true,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{stepSize:1},grid:{display:false}},y:{grid:{display:false}}}}});
</script>";
include '../includes/footer.php';
?>
