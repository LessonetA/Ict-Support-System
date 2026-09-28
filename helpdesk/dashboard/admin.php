<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
global $conn;
$user  = currentUser();
$stats = getAllStats($conn);
$pageTitle = 'Dashboard';

$slaR = mysqli_query($conn,"SELECT COUNT(t.id) as c FROM tickets t LEFT JOIN sla_settings s ON s.priority=t.priority WHERE t.status NOT IN ('resolved','closed') AND TIMESTAMPDIFF(HOUR,t.created_at,NOW())>s.hours_to_resolve");
$slaBreached = mysqli_fetch_assoc($slaR)['c'] ?? 0;

$avgR = mysqli_query($conn,"SELECT AVG(TIMESTAMPDIFF(HOUR,created_at,resolved_at)) as h FROM tickets WHERE status IN ('resolved','closed') AND resolved_at IS NOT NULL");
$avgRes = round(mysqli_fetch_assoc($avgR)['h'] ?? 0);

$resRate = $stats['total']>0 ? round((($stats['resolved']+($stats['closed']??0))/$stats['total'])*100) : 0;

$result  = mysqli_query($conn,"SELECT t.*,u.full_name,c.name as category FROM tickets t LEFT JOIN users u ON t.user_id=u.id LEFT JOIN categories c ON t.category_id=c.id ORDER BY t.created_at DESC LIMIT 8");
$tickets = mysqli_fetch_all($result,MYSQLI_ASSOC);

$catR    = mysqli_query($conn,"SELECT c.name, COUNT(t.id) as cnt FROM categories c LEFT JOIN tickets t ON t.category_id=c.id GROUP BY c.id ORDER BY cnt DESC");
$catData = mysqli_fetch_all($catR,MYSQLI_ASSOC);
$jCatL   = json_encode(array_column($catData,'name'));
$jCatC   = json_encode(array_column($catData,'cnt'));

$weekR   = mysqli_query($conn,"SELECT DAYNAME(created_at) as day, DAYOFWEEK(created_at) as dn, COUNT(*) as cnt FROM tickets WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY dn,day ORDER BY dn");
$weekD   = mysqli_fetch_all($weekR,MYSQLI_ASSOC);
$jWL = json_encode(array_column($weekD,'day'));
$jWC = json_encode(array_column($weekD,'cnt'));

// ── NEW: Staff Leaderboard ──────────────────────────────────────
$staffR = mysqli_query($conn,"
    SELECT u.full_name,
           COUNT(CASE WHEN t.status IN ('resolved','closed') THEN 1 END) as resolved,
           COUNT(t.id) as total,
           ROUND(AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,t.created_at,t.resolved_at) END),1) as avg_hours,
           ROUND(AVG(r.rating),1) as avg_rating
    FROM users u
    LEFT JOIN assignments a ON a.staff_id=u.id
    LEFT JOIN tickets t ON t.id=a.ticket_id
    LEFT JOIN ticket_ratings r ON r.ticket_id=t.id
    WHERE u.role='staff'
    GROUP BY u.id, u.full_name
    ORDER BY resolved DESC
    LIMIT 5
");
$staffLeaders = mysqli_fetch_all($staffR, MYSQLI_ASSOC);

// ── NEW: Critical unassigned tickets ───────────────────────────
$critR = mysqli_query($conn,"
    SELECT t.*, u.full_name FROM tickets t
    LEFT JOIN users u ON t.user_id=u.id
    WHERE t.priority='critical' AND t.status='open'
    AND t.id NOT IN (SELECT ticket_id FROM assignments)
    ORDER BY t.created_at ASC LIMIT 5
");
$critTickets = mysqli_fetch_all($critR, MYSQLI_ASSOC);

// ── NEW: Activity feed ─────────────────────────────────────────
$actR = mysqli_query($conn,"
    SELECT a.action, a.description, a.created_at, u.full_name, u.role
    FROM activity_log a
    LEFT JOIN users u ON u.id=a.user_id
    ORDER BY a.created_at DESC LIMIT 10
");
$activities = $actR ? mysqli_fetch_all($actR, MYSQLI_ASSOC) : [];

// ── NEW: Avg resolution time per day (last 7 days) ─────────────
$resTimeR = mysqli_query($conn,"
    SELECT DATE_FORMAT(resolved_at,'%a') as day,
           DAYOFWEEK(resolved_at) as dn,
           ROUND(AVG(TIMESTAMPDIFF(HOUR,created_at,resolved_at)),1) as avg_h
    FROM tickets
    WHERE resolved_at IS NOT NULL AND resolved_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)
    GROUP BY dn, day ORDER BY dn
");
$resTimeData = $resTimeR ? mysqli_fetch_all($resTimeR, MYSQLI_ASSOC) : [];
$jResL = json_encode(array_column($resTimeData,'day'));
$jResH = json_encode(array_column($resTimeData,'avg_h'));

// ── NEW: SLA compliance % ──────────────────────────────────────
$slaTotal = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM tickets WHERE status IN ('resolved','closed')"))['c'] ?? 0;
$slaOk    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(t.id) as c FROM tickets t LEFT JOIN sla_settings s ON s.priority=t.priority WHERE t.status IN ('resolved','closed') AND (t.resolved_at IS NULL OR TIMESTAMPDIFF(HOUR,t.created_at,t.resolved_at)<=s.hours_to_resolve)"))['c'] ?? 0;
$slaCompliance = $slaTotal > 0 ? round(($slaOk/$slaTotal)*100) : 100;

include '../includes/header.php';
?>

<!-- ── Critical Alert Banner ───────────────────────────────── -->
<?php if (!empty($critTickets)): ?>
<div style="background:linear-gradient(90deg,rgba(220,38,38,.15),rgba(220,38,38,.05));border:1px solid rgba(220,38,38,.35);border-radius:12px;padding:12px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
  <div style="display:flex;align-items:center;gap:10px;">
    <span style="font-size:18px;animation:online-pulse 2s infinite;">🔴</span>
    <div>
      <strong style="color:#f87171;font-size:13.5px;"><?= count($critTickets) ?> Critical Ticket<?= count($critTickets)>1?'s':'' ?> — Unassigned & Awaiting Action</strong>
      <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
        <?php foreach(array_slice($critTickets,0,2) as $ct): ?>
          <code style="margin-right:8px;color:#fca5a5;"><?= $ct['ticket_no'] ?></code>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <a href="../tickets/manage.php?priority=critical&status=open" class="btn btn-sm" style="background:#dc2626;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:12px;font-weight:600;">
    <i class="fas fa-exclamation-triangle me-1"></i>View Critical Tickets
  </a>
</div>
<?php endif; ?>

<!-- Page header row -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:10px;">
  <div>
    <p style="font-size:13px;color:var(--text-muted);margin:0;">Good <?= date('G')<12?'morning':(date('G')<17?'afternoon':'evening') ?>, <strong style="color:var(--text-primary);"><?= htmlspecialchars(explode(' ',$user['name'])[0]) ?></strong> — here's what's happening today.</p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <a href="../reports/analytics.php" class="btn btn-ghost btn-sm"><i class="fas fa-chart-line me-1"></i>Analytics</a>
    <a href="../reports/export.php?type=excel" class="btn btn-teal btn-sm"><i class="fas fa-file-excel me-1"></i>Export</a>
  </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3" style="margin-bottom:22px;">
  <div class="col-md-2 col-6">
    <div class="stat-card c-blue anim-up">
      <div class="stat-icon-box" style="background:rgba(59,130,246,.1);color:var(--blue-500);"><i class="fas fa-ticket-alt"></i></div>
      <div class="stat-body">
        <div class="stat-value" id="st-total" data-count="<?=$stats['total']?>">0</div>
        <div class="stat-label">Total</div>
      </div>
    </div>
  </div>
  <div class="col-md-2 col-6">
    <div class="stat-card c-amber anim-up d1">
      <div class="stat-icon-box" style="background:rgba(217,119,6,.1);color:var(--amber-500);"><i class="fas fa-inbox"></i></div>
      <div class="stat-body">
        <div class="stat-value" id="st-open" data-count="<?=$stats['open']?>">0</div>
        <div class="stat-label">Open</div>
      </div>
    </div>
  </div>
  <div class="col-md-2 col-6">
    <div class="stat-card c-violet anim-up d2">
      <div class="stat-icon-box" style="background:rgba(124,58,237,.1);color:var(--violet-500);"><i class="fas fa-hourglass-half"></i></div>
      <div class="stat-body">
        <div class="stat-value" id="st-in-progress" data-count="<?=$stats['in_progress']?>">0</div>
        <div class="stat-label">In Progress</div>
      </div>
    </div>
  </div>
  <div class="col-md-2 col-6">
    <div class="stat-card c-green anim-up d3">
      <div class="stat-icon-box" style="background:rgba(5,150,105,.1);color:var(--emerald-500);"><i class="fas fa-check-double"></i></div>
      <div class="stat-body">
        <div class="stat-value" id="st-resolved" data-count="<?=$stats['resolved']?>">0</div>
        <div class="stat-label">Resolved</div>
      </div>
    </div>
  </div>
  <div class="col-md-2 col-6">
    <div class="stat-card c-teal anim-up d4">
      <div class="stat-icon-box" style="background:rgba(14,165,233,.1);color:var(--teal-500);"><i class="fas fa-percentage"></i></div>
      <div class="stat-body">
        <div class="stat-value" data-count="<?=$resRate?>">0</div>
        <div class="stat-label">Rate %</div>
        <div style="height:3px;background:var(--surface-3);border-radius:99px;margin-top:8px;"><div style="width:<?=$resRate?>%;height:3px;background:var(--teal-500);border-radius:99px;transition:width 1.2s ease;"></div></div>
      </div>
    </div>
  </div>
  <div class="col-md-2 col-6">
    <div class="stat-card c-rose anim-up d5 <?= $slaBreached>0?'card-breach':'' ?>">
      <div class="stat-icon-box" style="background:rgba(220,38,38,.1);color:var(--rose-500);"><i class="fas fa-fire-alt"></i></div>
      <div class="stat-body">
        <div class="stat-value" id="st-sla-breached" style="<?=$slaBreached>0?'color:var(--rose-500);':''?>" data-count="<?=$slaBreached?>">0</div>
        <div class="stat-label">SLA Breach</div>
        <?php if($slaBreached>0):?><div class="stat-sub down"><i class="fas fa-exclamation-triangle" style="font-size:10px;"></i>Needs attention</div><?php endif;?>
      </div>
    </div>
  </div>
</div>

<!-- ── NEW: SLA Compliance Meter ──────────────────────────── -->
<div class="form-card anim-up" style="margin-bottom:22px;padding:20px 24px;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="width:52px;height:52px;border-radius:12px;background:rgba(14,165,233,.1);display:flex;align-items:center;justify-content:center;font-size:22px;">📊</div>
      <div>
        <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:2px;">SLA Compliance This Month</div>
        <div style="font-size:28px;font-weight:800;color:<?= $slaCompliance>=80?'#22c55e':($slaCompliance>=60?'#f59e0b':'#ef4444') ?>;"><?= $slaCompliance ?>%</div>
      </div>
    </div>
    <div style="flex:1;min-width:200px;">
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:6px;">
        <span><?= $slaOk ?> within SLA</span>
        <span><?= $slaTotal - $slaOk ?> breached</span>
      </div>
      <div style="height:10px;background:var(--surface-3);border-radius:99px;overflow:hidden;">
        <div style="height:100%;width:<?= $slaCompliance ?>%;background:<?= $slaCompliance>=80?'linear-gradient(90deg,#16a34a,#22c55e)':($slaCompliance>=60?'linear-gradient(90deg,#d97706,#f59e0b)':'linear-gradient(90deg,#dc2626,#ef4444)') ?>;border-radius:99px;transition:width 1.5s ease;"></div>
      </div>
    </div>
    <div style="display:flex;gap:16px;font-size:12px;">
      <div style="text-align:center;"><div style="font-weight:700;font-size:18px;color:var(--text-primary);"><?= $avgRes ?>h</div><div style="color:var(--text-muted);">Avg Resolution</div></div>
      <div style="text-align:center;"><div style="font-weight:700;font-size:18px;color:var(--text-primary);"><?= $stats['users'] ?></div><div style="color:var(--text-muted);">Active Users</div></div>
      <div style="text-align:center;"><div style="font-weight:700;font-size:18px;color:var(--text-primary);"><?= $stats['staff'] ?></div><div style="color:var(--text-muted);">IT Staff</div></div>
    </div>
  </div>
</div>

<!-- CHARTS ROW -->
<div class="row g-3" style="margin-bottom:22px;">
  <div class="col-md-4">
    <div class="form-card" style="height:100%;">
      <div class="form-card-title"><i class="fas fa-chart-pie me-2" style="color:var(--teal-500);font-size:13px;"></i>Tickets by Category</div>
      <canvas id="catChart" style="max-height:200px;"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-card" style="height:100%;">
      <div class="form-card-title"><i class="fas fa-calendar-week me-2" style="color:var(--blue-500);font-size:13px;"></i>This Week — Volume</div>
      <canvas id="weekChart" style="max-height:200px;"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-card" style="height:100%;">
      <div class="form-card-title"><i class="fas fa-clock me-2" style="color:var(--violet-500);font-size:13px;"></i>Avg Resolution Time (hrs)</div>
      <canvas id="resTimeChart" style="max-height:200px;"></canvas>
      <?php if (empty($resTimeData)): ?>
      <div style="text-align:center;padding:30px 0;color:var(--text-muted);font-size:13px;">No resolved tickets this week yet</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── NEW ROW: Staff Leaderboard + Activity Feed ──────────── -->
<div class="row g-3" style="margin-bottom:22px;">

  <!-- Staff Leaderboard -->
  <div class="col-md-7">
    <div class="table-card" style="height:100%;">
      <div class="table-card-header">
        <h6 class="card-title"><i class="fas fa-trophy me-2" style="color:#f59e0b;"></i>Staff Leaderboard</h6>
        <a href="../reports/analytics.php" class="btn btn-ghost btn-sm">Full Report</a>
      </div>
      <?php if (empty($staffLeaders)): ?>
        <div style="padding:30px;text-align:center;color:var(--text-muted);font-size:13px;">No staff data yet — assign tickets to staff members to see leaderboard.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th style="width:30px;">#</th>
              <th>Staff Member</th>
              <th>Resolved</th>
              <th>Avg Time</th>
              <th>Rating</th>
              <th>Performance</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $medals = ['🥇','🥈','🥉','4️⃣','5️⃣'];
          foreach ($staffLeaders as $i => $s):
            $perfPct = $s['total']>0 ? round(($s['resolved']/$s['total'])*100) : 0;
            $perfColor = $perfPct>=80?'#22c55e':($perfPct>=50?'#f59e0b':'#ef4444');
          ?>
          <tr>
            <td style="font-size:16px;"><?= $medals[$i] ?? ($i+1) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#1e3a5f,#0ea5e9);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;">
                  <?= strtoupper(substr($s['full_name'],0,1)) ?>
                </div>
                <span style="font-size:13px;font-weight:500;"><?= htmlspecialchars($s['full_name']) ?></span>
              </div>
            </td>
            <td><span style="font-weight:700;color:var(--text-primary);"><?= $s['resolved'] ?></span><span style="color:var(--text-muted);font-size:11px;"> / <?= $s['total'] ?></span></td>
            <td style="font-size:12.5px;color:var(--text-muted);"><?= $s['avg_hours'] ? $s['avg_hours'].'h' : '—' ?></td>
            <td>
              <?php if ($s['avg_rating']): ?>
                <span style="color:#f59e0b;">★</span>
                <span style="font-size:13px;font-weight:600;"><?= $s['avg_rating'] ?></span>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:12px;">No ratings</span>
              <?php endif; ?>
            </td>
            <td style="min-width:90px;">
              <div style="display:flex;align-items:center;gap:6px;">
                <div style="flex:1;height:6px;background:var(--surface-3);border-radius:99px;overflow:hidden;">
                  <div style="width:<?= $perfPct ?>%;height:100%;background:<?= $perfColor ?>;border-radius:99px;"></div>
                </div>
                <span style="font-size:11px;color:<?= $perfColor ?>;font-weight:600;min-width:28px;"><?= $perfPct ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Activity Feed -->
  <div class="col-md-5">
    <div class="form-card" style="height:100%;">
      <div class="form-card-title" style="justify-content:space-between;display:flex;align-items:center;">
        <span><i class="fas fa-stream me-2" style="color:var(--blue-500);font-size:13px;"></i>Live Activity Feed</span>
        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;animation:online-pulse 2s infinite;"></span>
      </div>
      <?php if (empty($activities)): ?>
        <div style="padding:30px;text-align:center;color:var(--text-muted);font-size:13px;">No activity recorded yet.</div>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:0;max-height:320px;overflow-y:auto;">
        <?php
        $actIcons = [
          'login'         => ['🟢','#22c55e'],
          'logout'        => ['🔴','#ef4444'],
          'ticket_created'=> ['🎫','#3b82f6'],
          'ticket_reply'  => ['💬','#8b5cf6'],
          'status_change' => ['🔄','#f59e0b'],
          'ticket_assigned'=>['👤','#0ea5e9'],
          'user_approved' => ['✅','#22c55e'],
        ];
        foreach ($activities as $act):
          [$icon,$color] = $actIcons[$act['action']] ?? ['📌','#94a3b8'];
        ?>
        <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);">
          <div style="width:28px;height:28px;border-radius:8px;background:var(--surface-2);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;"><?= $icon ?></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:12.5px;color:var(--text-primary);font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= htmlspecialchars($act['full_name'] ?? 'System') ?>
              <span style="font-weight:400;color:var(--text-muted);"> — <?= htmlspecialchars($act['description'] ?: $act['action']) ?></span>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= timeAgo($act['created_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- RECENT TICKETS -->
<div class="table-card anim-up d2">
  <div class="table-card-header">
    <h6 class="card-title"><i class="fas fa-ticket-alt me-2" style="color:var(--blue-500);"></i>Recent Tickets</h6>
    <a href="../tickets/manage.php" class="btn btn-ghost btn-sm">View all <i class="fas fa-arrow-right ms-1" style="font-size:10px;"></i></a>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Ticket</th><th>Issue</th><th>Raised By</th><th>Priority</th><th>Status</th><th>SLA</th><th>Age</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tickets as $t):
        $sla = getSLAInfo($conn, $t);
        if (!$sla) { $slaHtml = '<span style="color:var(--text-muted);">—</span>'; }
        elseif (in_array($t['status'],['resolved','closed'])) { $slaHtml = '<span class="badge badge-green">Done</span>'; }
        elseif ($sla['breached']) { $slaHtml = '<span class="badge badge-rose">Breached</span>'; }
        elseif ($sla['percent']>75) { $slaHtml = '<span class="badge badge-amber">At Risk</span>'; }
        else { $slaHtml = '<span class="badge badge-green">On Track</span>'; }
      ?>
      <tr>
        <td><code><?=$t['ticket_no']?></code></td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=htmlspecialchars($t['title'])?></td>
        <td style="font-size:12.5px;color:var(--text-secondary);"><?=htmlspecialchars($t['full_name'])?></td>
        <td><?=priorityBadge($t['priority'])?></td>
        <td><?=statusBadge($t['status'])?></td>
        <td><?=$slaHtml?></td>
        <td style="font-size:12px;color:var(--text-muted);"><?=timeAgo($t['created_at'])?></td>
        <td><a href="../tickets/view.php?id=<?=$t['id']?>" class="btn btn-outline-primary btn-sm">View</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Quick Actions (bottom) -->
<div class="form-card anim-up" style="margin-top:22px;">
  <div class="form-card-title"><i class="fas fa-bolt me-2" style="color:var(--amber-500);font-size:13px;"></i>Quick Actions</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;">
    <?php
    $actions=[
      ['../tickets/manage.php','fa-ticket-alt','All Tickets','btn-outline-primary'],
      ['../admin/users.php','fa-users','Users','btn-outline-secondary'],
      ['../knowledge/manage.php','fa-book-open','Knowledge','btn-outline-info'],
      ['../reports/analytics.php','fa-chart-line','Analytics','btn-outline-success'],
      ['../announcements/manage.php','fa-bullhorn','Announce','btn-outline-warning'],
      ['../reports/export.php?type=excel','fa-file-excel','Export','btn-ghost'],
    ];
    foreach($actions as [$url,$icon,$label,$cls]):?>
    <a href="<?=$url?>" class="btn <?=$cls?>" style="flex-direction:column;gap:6px;padding:12px 8px;font-size:11.5px;border-radius:10px;">
      <i class="fas <?=$icon?>" style="font-size:17px;"></i><?=$label?>
    </a>
    <?php endforeach;?>
  </div>
</div>

<?php
$extraScript = <<<JS
<script>
const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.04)';
const textColor = isDark ? '#8b9eb7' : '#94a3b8';

Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.color = textColor;

// Category donut
new Chart(document.getElementById('catChart'), {
  type:'doughnut',
  data:{ labels:$jCatL, datasets:[{ data:$jCatC, backgroundColor:['#3b82f6','#0ea5e9','#10b981','#f59e0b','#8b5cf6','#ec4899'], borderWidth:2, borderColor:'transparent', hoverOffset:8 }] },
  options:{ responsive:true, cutout:'68%', plugins:{ legend:{ position:'bottom', labels:{ padding:12, font:{ size:11 } } } } }
});

// Weekly volume bar
new Chart(document.getElementById('weekChart'), {
  type:'bar',
  data:{ labels:$jWL, datasets:[{ label:'Tickets', data:$jWC, backgroundColor:'rgba(59,130,246,.75)', borderRadius:8, borderSkipped:false, hoverBackgroundColor:'rgba(14,165,233,.9)' }] },
  options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 }, grid:{ color:gridColor } }, x:{ grid:{ display:false } } } }
});

// Avg resolution time line
const resCtx = document.getElementById('resTimeChart');
if (resCtx) {
  new Chart(resCtx, {
    type:'line',
    data:{ labels:$jResL, datasets:[{ label:'Avg Hours', data:$jResH, borderColor:'#8b5cf6', backgroundColor:'rgba(139,92,246,.1)', borderWidth:2, pointBackgroundColor:'#8b5cf6', tension:0.4, fill:true }] },
    options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, grid:{ color:gridColor } }, x:{ grid:{ display:false } } } }
  });
}
</script>
JS;
include '../includes/footer.php';
?>