<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAnyRole(['admin','staff']);
$pageTitle = 'Advanced Analytics';

$r = mysqli_query($conn,"SELECT COUNT(*) as c FROM tickets"); $total = mysqli_fetch_assoc($r)['c'];
$r = mysqli_query($conn,"SELECT COUNT(*) as c FROM tickets WHERE status IN ('resolved','closed')"); $resolved = mysqli_fetch_assoc($r)['c'];
$resRate = $total>0?round(($resolved/$total)*100):0;
$r = mysqli_query($conn,"SELECT AVG(TIMESTAMPDIFF(HOUR,created_at,IFNULL(resolved_at,NOW()))) as h FROM tickets WHERE status IN ('resolved','closed')");
$avgRes = round(mysqli_fetch_assoc($r)['h']??0);
$r = mysqli_query($conn,"SELECT COUNT(t.id) as c FROM tickets t LEFT JOIN sla_settings s ON s.priority=t.priority WHERE t.status NOT IN ('resolved','closed') AND TIMESTAMPDIFF(HOUR,t.created_at,NOW()) > s.hours_to_resolve");
$slaBreached = mysqli_fetch_assoc($r)['c']??0;
$slaComp = $total>0?round((($total-$slaBreached)/$total)*100):100;

// Avg satisfaction rating
$ratingR = mysqli_query($conn,"SELECT AVG(rating) as avg, COUNT(*) as cnt FROM ticket_ratings");
$ratingData = mysqli_fetch_assoc($ratingR);
$avgRating = $ratingData['avg']?round($ratingData['avg'],1):0;
$ratingCnt = $ratingData['cnt']??0;

$hourR  = mysqli_query($conn,"SELECT HOUR(created_at) as hr, COUNT(*) as cnt FROM tickets GROUP BY hr ORDER BY hr");
$hourData = array_fill(0,24,0);
while($row=mysqli_fetch_assoc($hourR)) $hourData[$row['hr']]=(int)$row['cnt'];

$dayR    = mysqli_query($conn,"SELECT DAYNAME(created_at) as day, DAYOFWEEK(created_at) as dn, COUNT(*) as cnt FROM tickets GROUP BY dn,day ORDER BY dn");
$dayData=[]; while($row=mysqli_fetch_assoc($dayR)) $dayData[$row['day']]=(int)$row['cnt'];
$dayOrder=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$dayLabels=$dayCounts=[];
foreach($dayOrder as $d){$dayLabels[]=$d;$dayCounts[]=$dayData[$d]??0;}

$deptR    = mysqli_query($conn,"SELECT u.department, COUNT(t.id) as cnt, SUM(t.status IN ('resolved','closed')) as resolved FROM tickets t LEFT JOIN users u ON t.user_id=u.id WHERE u.department IS NOT NULL AND u.department!='' GROUP BY u.department ORDER BY cnt DESC LIMIT 8");
$deptData = mysqli_fetch_all($deptR,MYSQLI_ASSOC);

$catR    = mysqli_query($conn,"SELECT c.name, COUNT(t.id) as cnt, SUM(t.status IN ('resolved','closed')) as resolved, AVG(TIMESTAMPDIFF(HOUR,t.created_at,IFNULL(t.resolved_at,NOW()))) as avg_hrs FROM tickets t LEFT JOIN categories c ON t.category_id=c.id GROUP BY c.id ORDER BY cnt DESC");
$catData = mysqli_fetch_all($catR,MYSQLI_ASSOC);

$monthR  = mysqli_query($conn,"SELECT DATE_FORMAT(created_at,'%b %Y') as month, DATE_FORMAT(created_at,'%Y-%m') as ym, COUNT(*) as total, SUM(status IN ('resolved','closed')) as resolved FROM tickets WHERE created_at >= DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY ym ORDER BY ym ASC");
$monthData = mysqli_fetch_all($monthR,MYSQLI_ASSOC);

$staffR    = mysqli_query($conn,"SELECT u.full_name, COUNT(a.id) as assigned, SUM(t.status='resolved') as resolved, SUM(t.status='closed') as closed, AVG(TIMESTAMPDIFF(HOUR,t.created_at,IFNULL(t.resolved_at,NOW()))) as avg_hrs FROM users u LEFT JOIN assignments a ON a.staff_id=u.id LEFT JOIN tickets t ON a.ticket_id=t.id WHERE u.role='staff' GROUP BY u.id ORDER BY assigned DESC");
$staffData = mysqli_fetch_all($staffR,MYSQLI_ASSOC);

// Rating distribution
$rDistR   = mysqli_query($conn,"SELECT rating, COUNT(*) as cnt FROM ticket_ratings GROUP BY rating ORDER BY rating");
$rDist    = array_fill(1,5,0);
while($row=mysqli_fetch_assoc($rDistR)) $rDist[$row['rating']]=(int)$row['cnt'];

$busiestHour=array_search(max($hourData),$hourData);
$busiestDay=$dayLabels[array_search(max($dayCounts),$dayCounts)]??'N/A';
$topCat=$catData[0]['name']??'N/A';
$topDept=$deptData[0]['department']??'N/A';

$jHour=json_encode($hourData);
$jDayL=json_encode($dayLabels);$jDayC=json_encode($dayCounts);
$jMonth=json_encode(array_column($monthData,'month'));
$jMonthT=json_encode(array_column($monthData,'total'));
$jMonthR2=json_encode(array_column($monthData,'resolved'));
$jCatL=json_encode(array_column($catData,'name'));
$jCatC=json_encode(array_column($catData,'cnt'));
$jDeptL=json_encode(array_column($deptData,'department'));
$jDeptC=json_encode(array_column($deptData,'cnt'));
$jDeptR=json_encode(array_column($deptData,'resolved'));
$jRDist=json_encode(array_values($rDist));

include '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div><h6 style="font-family:'Syne',sans-serif;font-weight:700;margin:0;">Advanced Analytics</h6><p style="font-size:12px;color:var(--muted);margin:2px 0 0;">Deep insights into helpdesk performance</p></div>
    <div class="d-flex gap-2">
        <a href="export.php?type=csv" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
        <a href="export.php?type=excel" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-excel me-1"></i>Export Excel</a>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <?php $kpis=[['Total Tickets',$total,'blue','fas fa-ticket-alt','#3b82f6'],['Resolution Rate',$resRate.'%','green','fas fa-percentage','#16a34a'],['Avg Resolution',$avgRes.'h','orange','fas fa-clock','#f59e0b'],['SLA Compliance',$slaComp.'%',$slaComp>=80?'green':'red','fas fa-shield-alt',$slaComp>=80?'#16a34a':'#ef4444'],['Satisfaction',$avgRating?$avgRating.'★ / 5':'N/A','teal','fas fa-star','#00bfae']];
    foreach($kpis as [$l,$v,$cls,$icon,$col]): ?>
    <div class="col animate-in" style="flex:1;min-width:160px;"><div class="stat-card <?= $cls ?>">
        <div class="stat-icon" style="background:<?= $col ?>20;"><i class="<?= $icon ?>" style="color:<?= $col ?>;"></i></div>
        <div><div style="font-family:'Syne',sans-serif;font-size:26px;font-weight:700;line-height:1;"><?= $v ?></div><div class="stat-label"><?= $l ?></div></div>
    </div></div>
    <?php endforeach; ?>
</div>

<!-- Monthly Trend -->
<div class="form-card mb-4 animate-in">
    <h6 style="font-size:13px;font-weight:700;margin-bottom:16px;"><i class="fas fa-chart-line me-2" style="color:var(--primary);"></i>Monthly Ticket Trend — Last 12 Months</h6>
    <canvas id="monthChart" height="80"></canvas>
</div>

<!-- Heatmap -->
<div class="form-card mb-4 animate-in">
    <h6 style="font-size:13px;font-weight:700;margin-bottom:4px;"><i class="fas fa-fire me-2" style="color:#ef4444;"></i>Hourly Heatmap — When Do Issues Occur?</h6>
    <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">Darker = more tickets at that hour. Use this to schedule IT staff shifts.</p>
    <div id="heatmap" style="display:grid;grid-template-columns:repeat(24,1fr);gap:3px;"></div>
    <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:10px;color:var(--muted);"><span>12 AM</span><span>6 AM</span><span>12 PM</span><span>6 PM</span><span>11 PM</span></div>
    <div style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:11.5px;color:var(--muted);">
        <span>Low</span>
        <?php foreach(['#eff6ff','#dbeafe','#93c5fd','#60a5fa','#3b82f6','#1d4ed8'] as $c): ?>
        <div style="width:20px;height:14px;background:<?= $c ?>;border-radius:3px;"></div>
        <?php endforeach; ?>
        <span>High</span>
    </div>
</div>

<!-- Day + Dept -->
<div class="row g-4 mb-4">
    <div class="col-md-5"><div class="form-card animate-in"><h6 style="font-size:13px;font-weight:700;margin-bottom:16px;"><i class="fas fa-calendar-week me-2" style="color:var(--primary);"></i>Tickets by Day of Week</h6><canvas id="dayChart" height="200"></canvas></div></div>
    <div class="col-md-7"><div class="form-card animate-in delay-1"><h6 style="font-size:13px;font-weight:700;margin-bottom:16px;"><i class="fas fa-building me-2" style="color:var(--primary);"></i>Tickets by Department</h6><canvas id="deptChart" height="200"></canvas></div></div>
</div>

<!-- Category + Ratings -->
<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="table-card animate-in">
            <div class="table-card-header"><h6><i class="fas fa-microscope me-2" style="color:var(--primary);"></i>Category Deep Dive</h6></div>
            <table class="table">
                <thead><tr><th>Category</th><th>Total</th><th>Resolved</th><th>Resolution Rate</th><th>Avg Time</th><th>Trend</th></tr></thead>
                <tbody>
                <?php foreach($catData as $c):
                    $rate=$c['cnt']>0?round(($c['resolved']/$c['cnt'])*100):0;
                    $rCol=$rate>=75?'var(--success)':($rate>=50?'var(--warning)':'var(--danger)');
                ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></td>
                    <td><span class="badge bg-primary"><?= $c['cnt'] ?></span></td>
                    <td><span class="badge bg-success"><?= $c['resolved'] ?></span></td>
                    <td><div style="display:flex;align-items:center;gap:8px;"><div style="flex:1;background:var(--border);border-radius:4px;height:6px;min-width:60px;"><div style="width:<?= $rate ?>%;background:<?= $rCol ?>;height:6px;border-radius:4px;"></div></div><span style="font-size:12px;font-weight:600;color:<?= $rCol ?>;"><?= $rate ?>%</span></div></td>
                    <td style="font-size:12px;"><?= round($c['avg_hrs']??0) ?>h</td>
                    <td><span style="font-size:12px;color:<?= $rCol ?>;"><?= $rate>=75?'↑ Good':($rate>=50?'→ Average':'↓ Needs work') ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-4">
        <?php if ($ratingCnt > 0): ?>
        <div class="form-card animate-in delay-1" style="margin-bottom:16px;">
            <h6 style="font-size:13px;font-weight:700;margin-bottom:14px;"><i class="fas fa-star me-2" style="color:#f59e0b;"></i>Satisfaction Ratings</h6>
            <div style="text-align:center;margin-bottom:16px;">
                <div style="font-family:'Syne',sans-serif;font-size:48px;font-weight:800;color:#f59e0b;line-height:1;"><?= $avgRating ?></div>
                <div style="color:var(--muted);font-size:12px;margin-top:4px;"><?= $ratingCnt ?> rating<?= $ratingCnt>1?'s':'' ?></div>
            </div>
            <?php for($s=5;$s>=1;$s--): $cnt=$rDist[$s]; $pct=$ratingCnt>0?round($cnt/$ratingCnt*100):0; ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:12px;">
                <span style="color:#f59e0b;width:12px;text-align:right;"><?= $s ?></span>
                <i class="fas fa-star" style="color:#f59e0b;font-size:11px;"></i>
                <div style="flex:1;background:var(--border);border-radius:100px;height:8px;"><div style="width:<?= $pct ?>%;background:#f59e0b;height:8px;border-radius:100px;"></div></div>
                <span style="color:var(--muted);width:28px;"><?= $cnt ?></span>
            </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <div class="form-card animate-in delay-2">
            <h6 style="font-size:13px;font-weight:700;margin-bottom:14px;"><i class="fas fa-medal me-2" style="color:#f59e0b;"></i>Staff Leaderboard</h6>
            <?php foreach($staffData as $i=>$s): $done=($s['resolved']+$s['closed']); $perf=$s['assigned']>0?round($done/max($s['assigned'],1)*100):0; $col=$perf>=75?'var(--success)':($perf>=40?'var(--warning)':'var(--danger)'); ?>
            <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);">
                <span style="font-size:18px;"><?= ['🥇','🥈','🥉'][$i]??($i+1) ?></span>
                <div class="avatar" style="width:28px;height:28px;font-size:11px;"><?= strtoupper(substr($s['full_name'],0,1)) ?></div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($s['full_name']) ?></div>
                    <div style="height:5px;background:var(--border);border-radius:100px;margin-top:4px;"><div style="width:<?= $perf ?>%;background:<?= $col ?>;height:5px;border-radius:100px;"></div></div>
                </div>
                <span style="font-size:12px;font-weight:700;color:<?= $col ?>;"><?= $perf ?>%</span>
            </div>
            <?php endforeach; if(empty($staffData)): ?><p style="color:var(--muted);font-size:13px;">No staff assigned yet.</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- Key Insights Panel -->
<div class="form-card animate-in" style="background:linear-gradient(135deg,#0d1b2a,#1a3a5c);border-color:rgba(59,130,246,0.2);color:#fff;">
    <h6 style="font-weight:700;font-size:14px;margin-bottom:16px;color:#fff;"><i class="fas fa-lightbulb me-2" style="color:#fbbf24;"></i>Key Insights & Recommendations</h6>
    <div class="row g-3">
        <?php $insights=[['PEAK HOUR',sprintf('%02d:00',$busiestHour),'Busiest time for tickets','Schedule extra IT staff coverage'],['BUSIEST DAY',$busiestDay,'Most tickets raised on this day','Plan capacity and staffing'],['TOP ISSUE',$topCat,'Most reported category','Create a Knowledge Base article'],['TOP DEPT',$topDept,'Highest volume department','Consider ICT training session']];
        foreach($insights as [$lbl,$val,$desc,$rec]): ?>
        <div class="col-md-3 col-6">
            <div style="background:rgba(255,255,255,0.07);border-radius:12px;padding:16px;">
                <div style="font-size:10px;opacity:0.5;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:6px;"><?= $lbl ?></div>
                <div style="font-size:20px;font-weight:700;margin-bottom:6px;"><?= htmlspecialchars($val) ?></div>
                <div style="font-size:11.5px;opacity:0.65;line-height:1.4;"><?= $desc ?><br><em><?= $rec ?></em></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$extraScript = "<script>
new Chart(document.getElementById('monthChart'),{type:'line',data:{labels:{$jMonth},datasets:[{label:'Raised',data:{$jMonthT},borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,0.08)',tension:0.4,fill:true,pointRadius:4,pointBackgroundColor:'#3b82f6'},{label:'Resolved',data:{$jMonthR2},borderColor:'#16a34a',backgroundColor:'rgba(22,163,74,0.06)',tension:0.4,fill:true,pointRadius:4,pointBackgroundColor:'#16a34a'}]},options:{responsive:true,interaction:{mode:'index'},plugins:{legend:{position:'top',labels:{font:{family:'DM Sans',size:12}}}},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(0,0,0,0.04)'}},x:{grid:{display:false}}}}});
new Chart(document.getElementById('dayChart'),{type:'bar',data:{labels:{$jDayL},datasets:[{data:{$jDayC},backgroundColor:['#94a3b8','#3b82f6','#3b82f6','#3b82f6','#3b82f6','#3b82f6','#94a3b8'],borderRadius:8,borderSkipped:false}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(0,0,0,0.04)'}},x:{grid:{display:false}}}}});
new Chart(document.getElementById('deptChart'),{type:'bar',data:{labels:{$jDeptL},datasets:[{label:'Total',data:{$jDeptC},backgroundColor:'rgba(59,130,246,0.7)',borderRadius:6},{label:'Resolved',data:{$jDeptR},backgroundColor:'rgba(22,163,74,0.7)',borderRadius:6}]},options:{responsive:true,plugins:{legend:{position:'top',labels:{font:{family:'DM Sans',size:11}}}},scales:{y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(0,0,0,0.04)'}},x:{grid:{display:false}}}}});
const hData={$jHour};const maxV=Math.max(...hData,1);const colors=['#eff6ff','#dbeafe','#bfdbfe','#93c5fd','#60a5fa','#3b82f6','#1d4ed8'];
const hm=document.getElementById('heatmap');
hData.forEach((v,hr)=>{
    const intensity=Math.min(Math.floor((v/maxV)*(colors.length-1)),colors.length-1);
    const cell=document.createElement('div');
    cell.style.cssText='height:44px;border-radius:6px;background:'+colors[intensity]+';display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:'+(intensity>3?'#fff':'#1e293b')+';cursor:default;transition:transform 0.2s;';
    cell.title=hr+':00 — '+v+' ticket(s)';
    cell.textContent=v||'';
    cell.addEventListener('mouseover',()=>{cell.style.transform='scale(1.15)';cell.style.zIndex='10';});
    cell.addEventListener('mouseout',()=>{cell.style.transform='';cell.style.zIndex='';});
    hm.appendChild(cell);
});
</script>";
include '../includes/footer.php';
?>
