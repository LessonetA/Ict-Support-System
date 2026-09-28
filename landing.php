<?php
require_once 'includes/db.php';
// Get live stats for the landing page
$tr = mysqli_query($conn,"SELECT COUNT(*) as c FROM tickets"); $tCount = mysqli_fetch_assoc($tr)['c']??0;
$ur = mysqli_query($conn,"SELECT COUNT(*) as c FROM users WHERE role='user'"); $uCount = mysqli_fetch_assoc($ur)['c']??0;
$rr = mysqli_query($conn,"SELECT COUNT(*) as c FROM tickets WHERE status IN ('resolved','closed')"); $rCount = mysqli_fetch_assoc($rr)['c']??0;
$rate = $tCount>0 ? round(($rCount/$tCount)*100) : 98;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ICT Helpdesk System — Smart IT Support</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<style>
body{background:var(--navy);color:#fff;font-family:var(--font-body)}
nav.lnav{position:fixed;top:0;left:0;right:0;z-index:999;padding:20px 60px;display:flex;justify-content:space-between;align-items:center;transition:all .3s}
nav.lnav.scrolled{background:rgba(13,27,53,.95);backdrop-filter:blur(20px);padding:14px 60px;border-bottom:1px solid rgba(255,255,255,.06)}
.lnav .brand{display:flex;align-items:center;gap:12px;text-decoration:none}
.lnav .brand .li{width:38px;height:38px;background:linear-gradient(135deg,var(--accent),#0099ff);border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:17px;box-shadow:0 4px 14px rgba(0,212,170,.35)}
.lnav .brand span{font-family:var(--font-head);font-size:15px;font-weight:700;color:#fff}
.hero{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;position:relative;padding:100px 20px 60px;overflow:hidden}
.hero-bg{position:absolute;top:0;left:0;right:0;bottom:0;overflow:hidden}
.hero-orb{position:absolute;border-radius:50%;filter:blur(80px);pointer-events:none}
.orb1{width:600px;height:600px;background:radial-gradient(circle,rgba(0,212,170,.12),transparent 70%);top:-200px;left:-200px;animation:float1 8s ease-in-out infinite}
.orb2{width:500px;height:500px;background:radial-gradient(circle,rgba(15,76,129,.2),transparent 70%);bottom:-100px;right:-100px;animation:float2 10s ease-in-out infinite}
.orb3{width:300px;height:300px;background:radial-gradient(circle,rgba(0,153,255,.08),transparent 70%);top:50%;left:50%;transform:translate(-50%,-50%);animation:float3 6s ease-in-out infinite}
@keyframes float1{0%,100%{transform:translate(0,0)}50%{transform:translate(40px,30px)}}
@keyframes float2{0%,100%{transform:translate(0,0)}50%{transform:translate(-30px,-40px)}}
@keyframes float3{0%,100%{transform:translate(-50%,-50%) scale(1)}50%{transform:translate(-50%,-50%) scale(1.2)}}
/* Grid lines background */
.hero-grid{position:absolute;top:0;left:0;right:0;bottom:0;background-image:linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);background-size:60px 60px;mask-image:radial-gradient(ellipse at center,black 20%,transparent 80%)}
.hero-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(0,212,170,.1);border:1px solid rgba(0,212,170,.25);border-radius:99px;padding:7px 18px;font-size:12.5px;color:var(--accent);font-weight:600;letter-spacing:.5px;margin-bottom:26px;animation:fadeInUp .6s ease both}
.hero-title{font-family:var(--font-head);font-size:clamp(38px,6vw,74px);font-weight:800;line-height:1.05;letter-spacing:-2.5px;max-width:820px;margin-bottom:22px;animation:fadeInUp .6s ease .1s both}
.hero-title .grad{background:linear-gradient(90deg,var(--accent),#0099ff,var(--accent));background-size:200%;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gradientMove 3s linear infinite}
@keyframes gradientMove{0%{background-position:0%}100%{background-position:200%}}
.hero-sub{font-size:clamp(15px,2vw,18px);color:rgba(255,255,255,.5);max-width:520px;line-height:1.75;margin:0 auto 42px;animation:fadeInUp .6s ease .2s both}
.hero-cta{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;animation:fadeInUp .6s ease .3s both}
.btn-hero-primary{background:linear-gradient(135deg,var(--accent),#0099ff);color:#fff;padding:14px 32px;border-radius:12px;font-size:15px;font-weight:600;font-family:var(--font-head);border:none;cursor:pointer;transition:all .25s;box-shadow:0 8px 32px rgba(0,212,170,.35);text-decoration:none;display:inline-flex;align-items:center;gap:9px}
.btn-hero-primary:hover{transform:translateY(-3px);box-shadow:0 14px 40px rgba(0,212,170,.5);color:#fff}
.btn-hero-ghost{background:rgba(255,255,255,.07);color:#fff;padding:14px 32px;border-radius:12px;font-size:15px;font-weight:600;font-family:var(--font-head);border:1px solid rgba(255,255,255,.12);cursor:pointer;transition:all .25s;text-decoration:none;display:inline-flex;align-items:center;gap:9px}
.btn-hero-ghost:hover{background:rgba(255,255,255,.12);color:#fff;transform:translateY(-2px)}
/* Floating UI mockup */
.hero-visual{margin-top:60px;position:relative;animation:fadeInUp .6s ease .4s both}
.ui-mockup{background:rgba(17,29,58,.9);border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:20px;max-width:780px;margin:0 auto;box-shadow:0 40px 100px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.05);backdrop-filter:blur(10px)}
.ui-mockup .mock-header{display:flex;align-items:center;gap:8px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,.06)}
.mock-dot{width:10px;height:10px;border-radius:50%}
.mock-title{font-family:var(--font-head);font-size:12px;font-weight:600;color:rgba(255,255,255,.4);flex:1;text-align:center}
.mock-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px}
.mock-stat{background:rgba(255,255,255,.04);border-radius:10px;padding:12px;border:1px solid rgba(255,255,255,.06)}
.mock-stat .mv{font-family:var(--font-head);font-size:22px;font-weight:800;color:#fff}
.mock-stat .ml{font-size:10px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.8px;margin-top:2px}
.mock-stat.accent .mv{color:var(--accent)}
.mock-stat.blue .mv{color:#60a5fa}
.mock-stat.green .mv{color:#34d399}
.mock-stat.red .mv{color:#f87171}
.mock-ticket{background:rgba(255,255,255,.04);border-radius:10px;padding:11px 14px;border:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:12px;margin-bottom:8px;font-size:12px}
.mock-ticket:last-child{margin-bottom:0}
.mock-badge{padding:3px 8px;border-radius:99px;font-size:10px;font-weight:700;font-family:var(--font-mono)}
/* STATS SECTION */
.stats-section{padding:70px 40px;background:rgba(0,0,0,.2);text-align:center}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:30px;max-width:800px;margin:0 auto}
.stat-num{font-family:var(--font-head);font-size:52px;font-weight:800;background:linear-gradient(135deg,var(--accent),#0099ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.stat-lbl{font-size:14px;color:rgba(255,255,255,.45);margin-top:6px}
/* FEATURES SECTION */
.features-section{padding:80px 40px;background:linear-gradient(180deg,rgba(0,0,0,.1),transparent)}
.section-tag{display:inline-block;background:rgba(0,212,170,.1);border:1px solid rgba(0,212,170,.2);color:var(--accent);padding:5px 14px;border-radius:99px;font-size:11.5px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;margin-bottom:14px}
.section-title{font-family:var(--font-head);font-size:clamp(28px,4vw,44px);font-weight:800;letter-spacing:-1.5px;margin-bottom:12px}
.section-sub{font-size:15px;color:rgba(255,255,255,.45);max-width:480px;margin:0 auto 50px;line-height:1.7}
.feat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;max-width:1100px;margin:0 auto}
.feat-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:30px;transition:all .3s;position:relative;overflow:hidden}
.feat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--accent),transparent);opacity:0;transition:opacity .3s}
.feat-card:hover{border-color:rgba(0,212,170,.2);background:rgba(0,212,170,.04);transform:translateY(-5px);box-shadow:0 20px 50px rgba(0,0,0,.3)}
.feat-card:hover::before{opacity:1}
.feat-icon{width:50px;height:50px;border-radius:14px;background:rgba(0,212,170,.1);border:1px solid rgba(0,212,170,.15);display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:18px;transition:all .3s}
.feat-card:hover .feat-icon{background:rgba(0,212,170,.2);box-shadow:0 0 20px rgba(0,212,170,.2)}
.feat-card h4{font-family:var(--font-head);font-size:16px;font-weight:700;color:#fff;margin-bottom:8px}
.feat-card p{font-size:13px;color:rgba(255,255,255,.42);line-height:1.7}
/* HOW IT WORKS */
.how-section{padding:80px 40px;text-align:center}
.steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;max-width:900px;margin:0 auto;position:relative}
.steps::before{content:'';position:absolute;top:30px;left:15%;right:15%;height:2px;background:linear-gradient(90deg,transparent,var(--accent),var(--accent),transparent);opacity:.3}
.step{padding:30px 20px;text-align:center}
.step-num{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#0099ff);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:20px;font-weight:800;color:#fff;margin:0 auto 16px;box-shadow:0 8px 24px rgba(0,212,170,.35)}
.step h4{font-family:var(--font-head);font-size:15px;font-weight:700;color:#fff;margin-bottom:8px}
.step p{font-size:13px;color:rgba(255,255,255,.4);line-height:1.6}
/* CTA SECTION */
.cta-section{padding:80px 40px;text-align:center;background:rgba(0,212,170,.04);border-top:1px solid rgba(0,212,170,.08);border-bottom:1px solid rgba(0,212,170,.08)}
/* FOOTER */
.landing-footer{padding:30px 60px;border-top:1px solid rgba(255,255,255,.05);display:flex;justify-content:space-between;align-items:center;font-size:12.5px;color:rgba(255,255,255,.3)}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
/* Scroll reveal */
.reveal{opacity:0;transform:translateY(30px);transition:all .7s ease}
.reveal.visible{opacity:1;transform:translateY(0)}
</style>
</head>
<body>

<!-- NAV -->
<nav class="lnav" id="lnav">
  <a href="landing.php" class="brand">
    <div class="li">🎧</div>
    <span>ICT Helpdesk</span>
  </a>
  <div style="display:flex;gap:10px;align-items:center;">
    <a href="index.php" style="color:rgba(255,255,255,.5);font-size:13.5px;text-decoration:none;font-weight:500;padding:8px 16px;border-radius:9px;transition:color .2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.5)'">Sign In</a>
    <a href="register.php" class="btn-hero-primary" style="padding:10px 22px;font-size:13.5px;">Get Started <i class="fas fa-arrow-right"></i></a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg">
    <div class="hero-orb orb1"></div>
    <div class="hero-orb orb2"></div>
    <div class="hero-orb orb3"></div>
    <div class="hero-grid"></div>
  </div>

  <div style="position:relative;z-index:2;width:100%;">
    <div class="hero-tag">
      <span class="live-dot"></span>
      Smart ICT Support Platform
    </div>
    <h1 class="hero-title">
      Resolve IT Issues<br><span class="grad">Faster Than Ever</span>
    </h1>
    <p class="hero-sub">
      A powerful helpdesk system that brings structure, speed, and intelligence to your ICT support operations.
    </p>
    <div class="hero-cta">
      <a href="register.php" class="btn-hero-primary">
        <i class="fas fa-rocket"></i> Get Started Free
      </a>
      <a href="index.php" class="btn-hero-ghost">
        <i class="fas fa-sign-in-alt"></i> Sign In
      </a>
    </div>

    <!-- UI MOCKUP -->
    <div class="hero-visual">
      <div class="ui-mockup">
        <div class="mock-header">
          <div class="mock-dot" style="background:#ef4444"></div>
          <div class="mock-dot" style="background:#f59e0b;margin-left:4px"></div>
          <div class="mock-dot" style="background:#10b981;margin-left:4px"></div>
          <span class="mock-title">Admin Dashboard — ICT Helpdesk System</span>
          <div style="width:8px;height:8px;border-radius:50%;background:var(--success);animation:pulseDot 2s infinite;box-shadow:0 0 0 3px rgba(16,185,129,.2)"></div>
        </div>
        <div class="mock-stats">
          <div class="mock-stat accent"><div class="mv" id="m-total"><?= $tCount ?: '24' ?></div><div class="ml">Total Tickets</div></div>
          <div class="mock-stat blue"><div class="mv" id="m-open"><?= max(1,$tCount-$rCount) ?></div><div class="ml">Open</div></div>
          <div class="mock-stat green"><div class="mv" id="m-res"><?= $rCount ?: '18' ?></div><div class="ml">Resolved</div></div>
          <div class="mock-stat red"><div class="mv">0</div><div class="ml">SLA Breach</div></div>
        </div>
        <div class="mock-ticket">
          <div style="width:8px;height:8px;border-radius:50%;background:#ef4444;flex-shrink:0"></div>
          <div style="flex:1;color:rgba(255,255,255,.7)">TKT-20260001 — Cannot connect to printer on 2nd floor</div>
          <span class="mock-badge" style="background:rgba(239,68,68,.15);color:#f87171;">Critical</span>
          <span class="mock-badge" style="background:rgba(59,130,246,.15);color:#60a5fa;">Open</span>
        </div>
        <div class="mock-ticket">
          <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b;flex-shrink:0"></div>
          <div style="flex:1;color:rgba(255,255,255,.7)">TKT-20260002 — Email not syncing on Outlook</div>
          <span class="mock-badge" style="background:rgba(245,158,11,.15);color:#fbbf24;">High</span>
          <span class="mock-badge" style="background:rgba(245,158,11,.15);color:#fbbf24;">In Progress</span>
        </div>
        <div class="mock-ticket">
          <div style="width:8px;height:8px;border-radius:50%;background:#10b981;flex-shrink:0"></div>
          <div style="flex:1;color:rgba(255,255,255,.7)">TKT-20260003 — Software installation request — MS Office</div>
          <span class="mock-badge" style="background:rgba(16,185,129,.15);color:#34d399;">Low</span>
          <span class="mock-badge" style="background:rgba(16,185,129,.15);color:#34d399;">Resolved</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- LIVE STATS -->
<section class="stats-section reveal">
  <div class="stats-grid">
    <div>
      <div class="stat-num" id="s1">0</div>
      <div class="stat-lbl">Tickets Managed</div>
    </div>
    <div>
      <div class="stat-num" id="s2">0</div>
      <div class="stat-lbl">Users Supported</div>
    </div>
    <div>
      <div class="stat-num" id="s3">0%</div>
      <div class="stat-lbl">Resolution Rate</div>
    </div>
    <div>
      <div class="stat-num" id="s4">0h</div>
      <div class="stat-lbl">Avg Resolution Time</div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features-section reveal">
  <div style="text-align:center;margin-bottom:0;">
    <div class="section-tag">Features</div>
    <h2 class="section-title">Everything Your IT Team Needs</h2>
    <p class="section-sub">Built with real workflows in mind — not just feature checklists.</p>
  </div>
  <div class="feat-grid">
    <?php
    $feats = [
      ['🎫','Smart Ticketing','Auto-generated ticket numbers, priority levels, category routing, and full lifecycle tracking from open to close.'],
      ['⏱️','SLA Enforcement','Live countdown timers for every ticket. Critical=8h, High=24h, Medium=48h, Low=72h. Red alerts when breached.'],
      ['📊','Advanced Analytics','Hourly heatmaps, trend charts, department breakdown, staff leaderboard, and AI-powered insights.'],
      ['📚','Knowledge Base','Self-service article library that reduces repetitive tickets. Searchable, categorised, view-tracked.'],
      ['🔔','Real-time Notifications','Instant alerts when tickets are assigned, updated, or replied to. Never miss an update.'],
      ['📄','PDF & Excel Export','One-click ticket PDF export and full data export in CSV or Excel for management reporting.'],
      ['🔐','Role-Based Security','Admin approval workflow, bcrypt password hashing, SQL injection prevention, and session management.'],
      ['📢','Announcements','Broadcast system-wide notices with expiry dates. Shown on every dashboard with notification bell.'],
      ['🌙','Dark Mode','Full dark/light theme toggle. Remembers your preference. Easy on the eyes during long shifts.'],
    ];
    foreach($feats as [$icon,$title,$desc]):
    ?>
    <div class="feat-card">
      <div class="feat-icon"><?= $icon ?></div>
      <h4><?= $title ?></h4>
      <p><?= $desc ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section reveal">
  <div class="section-tag">How It Works</div>
  <h2 class="section-title">Simple. Structured. Fast.</h2>
  <p class="section-sub">From problem to solution in minutes.</p>
  <div class="steps">
    <div class="step"><div class="step-num">1</div><h4>Register & Get Approved</h4><p>Sign up and wait for admin verification to keep the system secure.</p></div>
    <div class="step"><div class="step-num">2</div><h4>Submit a Ticket</h4><p>Describe your issue, set priority, and choose a category. The system generates a unique ticket number.</p></div>
    <div class="step"><div class="step-num">3</div><h4>IT Staff Responds</h4><p>Admin assigns the ticket. Staff get notified and reply through the platform — you get email updates.</p></div>
    <div class="step"><div class="step-num">4</div><h4>Rate & Close</h4><p>Once resolved, rate the service. Feedback drives team improvement.</p></div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section reveal">
  <h2 class="section-title">Ready to Transform Your IT Support?</h2>
  <p class="section-sub" style="margin-bottom:32px;">Join hundreds of teams using structured helpdesk workflows.</p>
  <div class="hero-cta">
    <a href="register.php" class="btn-hero-primary"><i class="fas fa-rocket"></i> Start Now — It's Free</a>
    <a href="index.php" class="btn-hero-ghost"><i class="fas fa-sign-in-alt"></i> Already have an account?</a>
  </div>
</section>

<!-- FOOTER -->
<footer class="landing-footer">
  <div>🎧 <strong style="color:rgba(255,255,255,.6)">ICT Helpdesk System</strong> — ACS Final Year Project &copy; <?= date('Y') ?></div>
  <div><a href="index.php" style="color:rgba(255,255,255,.3);text-decoration:none;">Login Portal →</a></div>
</footer>

<script>
// Navbar scroll effect
window.addEventListener('scroll',()=>{
  document.getElementById('lnav').classList.toggle('scrolled', window.scrollY>50);
});
// Scroll reveal
const revealEls = document.querySelectorAll('.reveal');
const revealObs = new IntersectionObserver((entries)=>{
  entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('visible'); revealObs.unobserve(e.target); } });
},{threshold:.1});
revealEls.forEach(el=>revealObs.observe(el));
// Animated counters
function animateCount(el, target, suffix='', duration=2000){
  let start=0; const step=target/60;
  const timer=setInterval(()=>{
    start=Math.min(start+step, target);
    el.textContent = Math.floor(start)+suffix;
    if(start>=target) clearInterval(timer);
  }, duration/60);
}
const statsObs = new IntersectionObserver((entries)=>{
  if(entries[0].isIntersecting){
    animateCount(document.getElementById('s1'), <?= max($tCount,24) ?>);
    animateCount(document.getElementById('s2'), <?= max($uCount,12) ?>);
    animateCount(document.getElementById('s3'), <?= $rate ?>, '%');
    animateCount(document.getElementById('s4'), 18, 'h');
    statsObs.disconnect();
  }
},{threshold:.3});
statsObs.observe(document.querySelector('.stats-section'));
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
