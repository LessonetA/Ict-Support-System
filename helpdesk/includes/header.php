<?php
$user     = currentUser();
$initial  = strtoupper(substr($user['name'], 0, 1));
$base     = '../';
$announcements = getAnnouncements($conn);
$pendingCount  = 0;
if ($user['role'] === 'admin') {
    $pr = mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE status='pending'");
    $pendingCount = mysqli_fetch_assoc($pr)['c'] ?? 0;
}
$cp = basename($_SERVER['PHP_SELF']);
function isAct($pages, $dir = null) {
    global $cp;
    $match = in_array($cp, (array)$pages);
    if ($match && $dir !== null) {
        $match = strpos($_SERVER['PHP_SELF'], '/' . $dir . '/') !== false;
    }
    return $match ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'ICT Helpdesk') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
<script>(function(){const t=localStorage.getItem('ict_theme')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body>
<canvas id="confetti-canvas"></canvas>

<!-- ───────── SIDEBAR ───────── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-inner">
      <div class="brand-logo"><i class="fas fa-headset"></i></div>
      <div class="brand-text">
        <h4>ICT Helpdesk</h4>
        <span>SUPPORT SYSTEM</span>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <ul class="list-style-none">
      <?php if ($user['role'] === 'admin'): ?>
        <li class="nav-group-label">Main</li>
        <li class="nav-item"><a href="<?=$base?>dashboard/admin.php" class="<?=isAct('admin.php','dashboard')?>"><span class="nav-icon-wrap"><i class="fas fa-th-large"></i></span><span class="nav-label-text">Dashboard</span></a></li>
        <li class="nav-item"><a href="<?=$base?>tickets/manage.php"  class="<?=isAct('manage.php','tickets')?>"><span class="nav-icon-wrap"><i class="fas fa-ticket-alt"></i></span><span class="nav-label-text">All Tickets</span></a></li>
        <li class="nav-item"><a href="<?=$base?>admin/users.php" class="<?=isAct('users.php','admin')?>"><span class="nav-icon-wrap"><i class="fas fa-users"></i></span><span class="nav-label-text">Users</span><?php if($pendingCount>0):?><span class="nav-pill"><?=$pendingCount?></span><?php endif;?></a></li>
        <li class="nav-group-label">Content</li>
        <li class="nav-item"><a href="<?=$base?>knowledge/manage.php" class="<?=isAct('manage.php','knowledge')?>"><span class="nav-icon-wrap"><i class="fas fa-book-open"></i></span><span class="nav-label-text">Knowledge Base</span></a></li>
        <li class="nav-item"><a href="<?=$base?>announcements/manage.php" class="<?=isAct('manage.php','announcements')?>"><span class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></span><span class="nav-label-text">Announcements</span></a></li>
        <li class="nav-group-label">Insights</li>
        <li class="nav-item"><a href="<?=$base?>reports/summary.php"   class="<?=isAct('summary.php','reports')?>"><span class="nav-icon-wrap"><i class="fas fa-chart-bar"></i></span><span class="nav-label-text">Reports</span></a></li>
        <li class="nav-item"><a href="<?=$base?>reports/analytics.php" class="<?=isAct('analytics.php','reports')?>"><span class="nav-icon-wrap"><i class="fas fa-chart-line"></i></span><span class="nav-label-text">Analytics</span></a></li>
      <?php elseif ($user['role'] === 'staff'): ?>
        <li class="nav-group-label">Main</li>
        <li class="nav-item"><a href="<?=$base?>dashboard/staff.php" class="<?=isAct('staff.php','dashboard')?>"><span class="nav-icon-wrap"><i class="fas fa-th-large"></i></span><span class="nav-label-text">Dashboard</span></a></li>
        <li class="nav-item"><a href="<?=$base?>tickets/manage.php" class="<?=isAct('manage.php','tickets')?>"><span class="nav-icon-wrap"><i class="fas fa-ticket-alt"></i></span><span class="nav-label-text">My Tickets</span></a></li>
        <li class="nav-item"><a href="<?=$base?>knowledge/index.php" class="<?=isAct('index.php','knowledge')?>"><span class="nav-icon-wrap"><i class="fas fa-book-open"></i></span><span class="nav-label-text">Knowledge Base</span></a></li>
        <li class="nav-item"><a href="<?=$base?>reports/analytics.php" class="<?=isAct('analytics.php','reports')?>"><span class="nav-icon-wrap"><i class="fas fa-chart-line"></i></span><span class="nav-label-text">Analytics</span></a></li>
      <?php else: ?>
        <li class="nav-group-label">Main</li>
        <li class="nav-item"><a href="<?=$base?>dashboard/user.php" class="<?=isAct('user.php','dashboard')?>"><span class="nav-icon-wrap"><i class="fas fa-th-large"></i></span><span class="nav-label-text">Dashboard</span></a></li>
        <li class="nav-item"><a href="<?=$base?>tickets/create.php" class="<?=isAct('create.php','tickets')?>"><span class="nav-icon-wrap"><i class="fas fa-plus"></i></span><span class="nav-label-text">New Ticket</span></a></li>
        <li class="nav-item"><a href="<?=$base?>tickets/manage.php" class="<?=isAct('manage.php','tickets')?>"><span class="nav-icon-wrap"><i class="fas fa-list-alt"></i></span><span class="nav-label-text">My Tickets</span></a></li>
        <li class="nav-item"><a href="<?=$base?>knowledge/index.php" class="<?=isAct('index.php','knowledge')?>"><span class="nav-icon-wrap"><i class="fas fa-book-open"></i></span><span class="nav-label-text">Knowledge Base</span></a></li>
      <?php endif; ?>
      <li class="nav-group-label">Account</li>
      <li class="nav-item"><a href="<?=$base?>profile/index.php" class="<?=isAct('index.php','profile')?>"><span class="nav-icon-wrap"><i class="fas fa-user-cog"></i></span><span class="nav-label-text">My Profile</span></a></li>
      <li class="nav-item"><a href="<?=$base?>logout.php"><span class="nav-icon-wrap"><i class="fas fa-sign-out-alt"></i></span><span class="nav-label-text">Sign Out</span></a></li>
    </ul>
  </nav>

  <div class="sidebar-footer">
    <div class="footer-user">
      <div class="footer-avatar"><?= $initial ?></div>
      <div class="footer-info">
        <div class="fn"><?= htmlspecialchars($user['name']) ?></div>
        <div class="fr"><?= strtoupper($user['role']) ?></div>
      </div>
      <div class="footer-online" title="Online"></div>
    </div>
  </div>
</aside>

<!-- ───────── MAIN ───────── -->
<div class="main-content" id="main-content">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-left">
      <span class="topbar-page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
    </div>
    <div class="topbar-right">

      <!-- Live indicator -->
      <span id="live-tag" style="display:none;font-family:var(--font-mono);font-size:10px;color:var(--text-muted);padding:4px 10px;background:var(--surface-2);border:1px solid var(--border);border-radius:99px;">
        <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#22c55e;margin-right:5px;animation:online-pulse 3s infinite;"></span>
        <span id="last-updated"></span>
      </span>

      <!-- Theme toggle -->
      <button class="theme-btn" id="theme-toggle" title="Toggle dark mode">
        <i class="fas fa-moon" id="theme-icon"></i>
      </button>

      <!-- Notifications -->
      <?php if (!empty($announcements) || $pendingCount > 0): ?>
      <div class="dropdown">
        <button class="notif-btn" onclick="this.nextElementSibling.classList.toggle('show')">
          <i class="fas fa-bell"></i>
          <span class="notif-count"><?= count($announcements) + $pendingCount ?></span>
        </button>
        <div class="dropdown-menu" style="right:0;min-width:300px;max-height:380px;overflow-y:auto;">
          <div class="dropdown-header">Notifications</div>
          <?php if ($pendingCount > 0): ?>
          <a href="<?=$base?>admin/users.php?filter=pending" class="dropdown-item">
            <div style="width:32px;height:32px;border-radius:8px;background:rgba(217,119,6,.1);display:flex;align-items:center;justify-content:center;color:var(--amber-500);font-size:14px;flex-shrink:0;">⏳</div>
            <div><div style="font-weight:600;font-size:12.5px;"><?= $pendingCount ?> pending registration<?= $pendingCount>1?'s':'' ?></div><div style="font-size:11px;color:var(--text-muted);">Click to review &amp; approve</div></div>
          </a>
          <?php endif; ?>
          <?php foreach (array_slice($announcements,0,3) as $ann): ?>
          <div class="dropdown-item" style="cursor:default;">
            <div style="width:32px;height:32px;border-radius:8px;background:rgba(14,165,233,.1);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">📢</div>
            <div><div style="font-weight:600;font-size:12.5px;"><?= htmlspecialchars($ann['title']) ?></div><div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars(substr($ann['message'],0,72)) ?>...</div></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- User menu -->
      <div class="dropdown">
        <a class="user-chip" onclick="this.nextElementSibling.classList.toggle('show')" href="#">
          <div class="topbar-avatar"><?= $initial ?></div>
          <div>
            <div class="chip-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="chip-role"><?= ucfirst($user['role']) ?></div>
          </div>
          <i class="fas fa-chevron-down" style="font-size:9px;color:var(--text-muted);margin-left:4px;"></i>
        </a>
        <div class="dropdown-menu">
          <a href="<?=$base?>profile/index.php" class="dropdown-item"><i class="fas fa-user-cog" style="width:16px;color:var(--text-muted);"></i> My Profile</a>
          <div class="dropdown-divider"></div>
          <a href="<?=$base?>logout.php" class="dropdown-item" style="color:var(--rose-500);"><i class="fas fa-sign-out-alt" style="width:16px;"></i> Sign Out</a>
        </div>
      </div>

    </div>
  </header>

  <!-- Announcement banner (1 max) -->
  <?php if (!empty($announcements)): $ann = $announcements[0]; ?>
  <div class="alert alert-<?= $ann['type'] ?>" style="border-radius:0;border-left:none;border-right:none;border-top:none;margin:0;padding:10px 28px;">
    <i class="fas fa-bullhorn" style="font-size:13px;"></i>
    <strong><?= htmlspecialchars($ann['title']) ?></strong> — <?= htmlspecialchars($ann['message']) ?>
    <button class="btn-close" onclick="this.closest('.alert').remove()">×</button>
  </div>
  <?php endif; ?>

  <!-- Page content -->
  <main class="content-area">

<!-- SESSION TIMEOUT MODAL -->
<div id="session-timeout-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:var(--surface,#1e293b);border:1px solid var(--border,#334155);border-radius:16px;padding:36px 40px;max-width:420px;width:90%;box-shadow:0 25px 60px rgba(0,0,0,.5);text-align:center;">
    <div style="font-size:48px;margin-bottom:12px;">&#9203;</div>
    <h3 style="margin:0 0 8px;font-size:18px;color:var(--text,#f1f5f9);font-weight:700;">Session Expiring Soon</h3>
    <p style="margin:0 0 20px;font-size:14px;color:var(--text-muted,#94a3b8);">You will be automatically logged out in</p>
    <div id="session-countdown" style="font-size:42px;font-weight:800;font-family:monospace;color:#f59e0b;letter-spacing:2px;margin-bottom:24px;">02:00</div>
    <div style="display:flex;gap:12px;justify-content:center;">
      <button onclick="sessionStayAlive()" style="flex:1;padding:12px 20px;background:linear-gradient(135deg,#1e3a5f,#0ea5e9);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;"><i class="fas fa-sync" style="margin-right:6px;"></i>Stay Logged In</button>
      <a href="<?= $base ?>logout.php" style="flex:1;padding:12px 20px;background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;display:flex;align-items:center;justify-content:center;"><i class="fas fa-sign-out-alt" style="margin-right:6px;"></i>Logout Now</a>
    </div>
  </div>
</div>
<script>
(function(){
  const TIMEOUT_MS=<?= SESSION_TIMEOUT ?>*1000;
  const WARN_MS=<?= SESSION_WARN_BEFORE ?>*1000;
  const BASE="<?= $base ?>";
  let warnT,expireT,cntInt,cntSecs=<?= SESSION_WARN_BEFORE ?>;
  function fmt(s){return String(Math.floor(s/60)).padStart(2,"0")+":"+String(s%60).padStart(2,"0");}
  function showModal(){
    cntSecs=<?= SESSION_WARN_BEFORE ?>;
    document.getElementById("session-countdown").textContent=fmt(cntSecs);
    document.getElementById("session-timeout-modal").style.display="flex";
    cntInt=setInterval(function(){cntSecs--;document.getElementById("session-countdown").textContent=fmt(Math.max(0,cntSecs));if(cntSecs<=0){clearInterval(cntInt);logout();}},1000);
  }
  function hideModal(){document.getElementById("session-timeout-modal").style.display="none";clearInterval(cntInt);}
  function logout(){window.location.href=BASE+"logout.php?msg=Session+expired";}
  function resetTimers(){
    clearTimeout(warnT);clearTimeout(expireT);clearInterval(cntInt);hideModal();
    warnT=setTimeout(showModal,TIMEOUT_MS-WARN_MS);
    expireT=setTimeout(logout,TIMEOUT_MS);
  }
  window.sessionStayAlive=function(){
    fetch(window.location.href,{headers:{"X-Session-Ping":"1"}})
      .then(r=>r.json()).then(d=>{if(d.status==="expired")logout();else resetTimers();}).catch(()=>resetTimers());
  };
  ["click","keydown","touchstart"].forEach(function(e){
    document.addEventListener(e,function(){
      fetch(window.location.href,{headers:{"X-Session-Ping":"1"}})
        .then(r=>r.json()).then(d=>{if(d.status==="expired")logout();else resetTimers();}).catch(()=>{});
    },{passive:true});
  });
  resetTimers();
})();
</script>