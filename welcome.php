<?php
// welcome.php — Stunning public landing page
require_once 'includes/db.php';

// Live stats for the landing page counter animation
$r1 = mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets"); $totalTickets = mysqli_fetch_assoc($r1)['c'] ?? 0;
$r2 = mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE status IN ('resolved','closed')"); $resolved = mysqli_fetch_assoc($r2)['c'] ?? 0;
$r3 = mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='user' AND status='active'"); $users = mysqli_fetch_assoc($r3)['c'] ?? 0;
$r4 = mysqli_query($conn, "SELECT COUNT(*) as c FROM knowledge_base"); $articles = mysqli_fetch_assoc($r4)['c'] ?? 0;
$resRate = $totalTickets > 0 ? round(($resolved/$totalTickets)*100) : 98;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ICT Helpdesk — Enterprise Support System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --bg: #050d1a;
  --surface: #0a1628;
  --card: rgba(255,255,255,0.04);
  --border: rgba(255,255,255,0.08);
  --primary: #3b82f6;
  --accent: #00e5cc;
  --text: #f0f6ff;
  --muted: rgba(255,255,255,0.45);
}
*{margin:0;padding:0;box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{
  font-family:'DM Sans',sans-serif;
  background:var(--bg);
  color:var(--text);
  overflow-x:hidden;
  cursor:none;
}

/* Custom cursor */
.cursor{position:fixed;pointer-events:none;z-index:9999;}
.cursor-dot{width:8px;height:8px;background:var(--accent);border-radius:50%;transform:translate(-50%,-50%);}
.cursor-ring{width:36px;height:36px;border:1.5px solid rgba(0,229,204,0.5);border-radius:50%;transform:translate(-50%,-50%);transition:transform 0.1s,width 0.2s,height 0.2s,opacity 0.2s;}

/* ── CANVAS BACKGROUND ── */
#bg-canvas{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;opacity:0.6;}

/* ── NAV ── */
nav{
  position:fixed;top:0;left:0;right:0;z-index:100;
  padding:20px 60px;
  display:flex;align-items:center;justify-content:space-between;
  border-bottom:1px solid transparent;
  transition:all 0.3s;
}
nav.scrolled{background:rgba(5,13,26,0.95);border-color:var(--border);backdrop-filter:blur(20px);}
.nav-logo{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;letter-spacing:-0.5px;}
.nav-logo span{color:var(--accent);}
.nav-links{display:flex;gap:32px;align-items:center;}
.nav-links a{color:var(--muted);text-decoration:none;font-size:14px;font-weight:500;transition:color 0.2s;}
.nav-links a:hover{color:var(--text);}
.btn-nav{
  background:var(--primary);color:#fff;padding:10px 24px;
  border-radius:100px;font-size:14px;font-weight:500;
  text-decoration:none;transition:all 0.2s;border:none;cursor:pointer;
}
.btn-nav:hover{background:#2563eb;transform:translateY(-1px);box-shadow:0 8px 24px rgba(59,130,246,0.4);}

/* ── HERO ── */
.hero{
  position:relative;z-index:1;
  min-height:100vh;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-align:center;padding:120px 20px 80px;
}
.hero-badge{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(0,229,204,0.1);border:1px solid rgba(0,229,204,0.3);
  border-radius:100px;padding:6px 16px;font-size:12.5px;
  color:var(--accent);margin-bottom:28px;
  animation:fadeUp 0.6s ease both;
}
.hero-badge .dot{width:6px;height:6px;background:var(--accent);border-radius:50%;animation:pulse 2s infinite;}
.hero h1{
  font-family:'Syne',sans-serif;
  font-size:clamp(44px,7vw,88px);
  font-weight:800;
  line-height:1.0;
  letter-spacing:-2px;
  margin-bottom:24px;
  animation:fadeUp 0.7s 0.1s ease both;
}
.hero h1 .line2{
  background:linear-gradient(135deg,var(--primary),var(--accent));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.hero p{
  max-width:560px;font-size:17px;color:var(--muted);
  line-height:1.7;margin-bottom:44px;
  animation:fadeUp 0.7s 0.2s ease both;
}
.hero-actions{
  display:flex;gap:14px;flex-wrap:wrap;justify-content:center;
  animation:fadeUp 0.7s 0.3s ease both;
}
.btn-hero{
  padding:15px 36px;border-radius:100px;font-size:15px;font-weight:500;
  text-decoration:none;transition:all 0.2s;display:flex;align-items:center;gap:10px;
}
.btn-hero.primary{
  background:linear-gradient(135deg,var(--primary),#60a5fa);color:#fff;
  box-shadow:0 8px 32px rgba(59,130,246,0.35);
}
.btn-hero.primary:hover{transform:translateY(-2px);box-shadow:0 12px 40px rgba(59,130,246,0.5);}
.btn-hero.ghost{
  border:1px solid var(--border);color:var(--text);background:var(--card);
  backdrop-filter:blur(12px);
}
.btn-hero.ghost:hover{border-color:rgba(255,255,255,0.2);background:rgba(255,255,255,0.08);}

/* ── FLOATING TICKET MOCKUP ── */
.hero-visual{
  position:relative;margin-top:70px;width:100%;max-width:760px;
  animation:fadeUp 0.8s 0.4s ease both;
}
.mockup-window{
  background:rgba(10,22,40,0.9);
  border:1px solid var(--border);border-radius:16px;
  overflow:hidden;box-shadow:0 40px 120px rgba(0,0,0,0.6),0 0 0 1px rgba(255,255,255,0.05);
  backdrop-filter:blur(20px);
}
.mockup-bar{
  padding:12px 20px;
  background:rgba(255,255,255,0.03);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:8px;
}
.dot-red{width:12px;height:12px;background:#ff5f57;border-radius:50%;}
.dot-yellow{width:12px;height:12px;background:#ffbd2e;border-radius:50%;}
.dot-green{width:12px;height:12px;background:#28c840;border-radius:50%;}
.mockup-url{margin-left:12px;background:rgba(255,255,255,0.05);border-radius:6px;padding:4px 14px;font-size:11.5px;color:var(--muted);}
.mockup-body{padding:24px;}
.mockup-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
.mockup-stat{background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center;}
.mockup-stat .num{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;}
.mockup-stat .lbl{font-size:10px;color:var(--muted);margin-top:2px;}
.mockup-row{
  display:flex;align-items:center;gap:12px;
  background:rgba(255,255,255,0.03);border:1px solid var(--border);
  border-radius:8px;padding:12px 16px;margin-bottom:8px;
  animation:slideIn 0.4s ease both;
}
.mockup-badge{padding:3px 10px;border-radius:100px;font-size:10px;font-weight:600;}
.badge-open{background:rgba(59,130,246,0.2);color:#60a5fa;}
.badge-inprog{background:rgba(245,158,11,0.2);color:#fbbf24;}
.badge-done{background:rgba(22,163,74,0.2);color:#4ade80;}
.badge-crit{background:rgba(239,68,68,0.2);color:#f87171;}

/* ── STATS SECTION ── */
.stats-section{
  position:relative;z-index:1;
  padding:80px 60px;
  display:grid;grid-template-columns:repeat(4,1fr);gap:2px;
  background:rgba(255,255,255,0.02);border-top:1px solid var(--border);border-bottom:1px solid var(--border);
}
.stat-item{padding:40px;text-align:center;position:relative;}
.stat-item:not(:last-child)::after{content:'';position:absolute;right:0;top:20%;height:60%;width:1px;background:var(--border);}
.stat-num{font-family:'Syne',sans-serif;font-size:52px;font-weight:800;line-height:1;letter-spacing:-2px;background:linear-gradient(135deg,#fff,rgba(255,255,255,0.5));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.stat-label{color:var(--muted);font-size:13px;margin-top:8px;}

/* ── FEATURES ── */
.features{position:relative;z-index:1;padding:100px 60px;max-width:1200px;margin:0 auto;}
.section-tag{font-size:11px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--accent);margin-bottom:16px;}
.section-title{font-family:'Syne',sans-serif;font-size:42px;font-weight:800;letter-spacing:-1.5px;margin-bottom:16px;line-height:1.1;}
.section-sub{color:var(--muted);font-size:16px;max-width:520px;line-height:1.6;margin-bottom:60px;}
.features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
.feat-card{
  background:var(--card);border:1px solid var(--border);
  border-radius:16px;padding:28px;
  transition:all 0.3s;position:relative;overflow:hidden;
}
.feat-card::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(circle at top left,rgba(59,130,246,0.08),transparent 60%);
  opacity:0;transition:opacity 0.3s;
}
.feat-card:hover{border-color:rgba(59,130,246,0.3);transform:translateY(-4px);}
.feat-card:hover::before{opacity:1;}
.feat-icon{
  width:48px;height:48px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  font-size:20px;margin-bottom:18px;
}
.feat-card h3{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:10px;}
.feat-card p{color:var(--muted);font-size:14px;line-height:1.6;}
.feat-card .feat-tag{
  display:inline-block;margin-top:16px;padding:4px 12px;
  background:rgba(0,229,204,0.1);border:1px solid rgba(0,229,204,0.2);
  border-radius:100px;font-size:11px;color:var(--accent);
}

/* ── ROLES SECTION ── */
.roles{position:relative;z-index:1;padding:60px;background:rgba(255,255,255,0.02);border-top:1px solid var(--border);}
.roles-inner{max-width:1100px;margin:0 auto;}
.roles-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:50px;}
.role-card{
  border-radius:20px;padding:36px;
  position:relative;overflow:hidden;
  transition:transform 0.3s;
}
.role-card:hover{transform:translateY(-5px);}
.role-card.admin{background:linear-gradient(135deg,#0f172a,#1e3a5f);border:1px solid rgba(59,130,246,0.3);}
.role-card.staff{background:linear-gradient(135deg,#0f172a,#1a3040);border:1px solid rgba(0,229,204,0.3);}
.role-card.user{background:linear-gradient(135deg,#0f172a,#2a1a3f);border:1px solid rgba(168,85,247,0.3);}
.role-card .role-icon{font-size:36px;margin-bottom:20px;}
.role-card h3{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;margin-bottom:12px;}
.role-card ul{list-style:none;padding:0;}
.role-card ul li{font-size:13.5px;color:var(--muted);padding:5px 0;display:flex;align-items:center;gap:8px;}
.role-card ul li::before{content:'→';color:var(--accent);font-size:12px;}

/* ── CTA ── */
.cta{
  position:relative;z-index:1;
  padding:100px 60px;text-align:center;
}
.cta-box{
  max-width:700px;margin:0 auto;
  background:linear-gradient(135deg,rgba(59,130,246,0.12),rgba(0,229,204,0.08));
  border:1px solid rgba(59,130,246,0.25);border-radius:28px;padding:70px;
  position:relative;overflow:hidden;
}
.cta-box::before{
  content:'';position:absolute;top:-80px;right:-80px;
  width:300px;height:300px;
  background:radial-gradient(circle,rgba(59,130,246,0.15),transparent 70%);
}
.cta-box h2{font-family:'Syne',sans-serif;font-size:42px;font-weight:800;letter-spacing:-1.5px;margin-bottom:16px;}
.cta-box p{color:var(--muted);font-size:16px;margin-bottom:36px;}

/* ── FOOTER ── */
footer{position:relative;z-index:1;padding:30px 60px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
footer p{color:var(--muted);font-size:13px;}

/* ── ANIMATIONS ── */
@keyframes fadeUp{from{opacity:0;transform:translateY(30px);}to{opacity:1;transform:translateY(0);}}
@keyframes slideIn{from{opacity:0;transform:translateX(-10px);}to{opacity:1;transform:translateX(0);}}
@keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-12px);}}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:0.3;}}
@keyframes ticker{0%{opacity:0;transform:translateY(10px);}10%{opacity:1;transform:translateY(0);}90%{opacity:1;transform:translateY(0);}100%{opacity:0;transform:translateY(-10px);}}

.floating{animation:float 4s ease-in-out infinite;}

/* Scroll reveal */
.reveal{opacity:0;transform:translateY(30px);transition:opacity 0.7s,transform 0.7s;}
.reveal.visible{opacity:1;transform:translateY(0);}

/* Live ticker */
.live-ticker{
  position:fixed;bottom:28px;left:28px;z-index:200;
  background:rgba(10,22,40,0.95);border:1px solid var(--border);
  border-radius:14px;padding:14px 20px;
  backdrop-filter:blur(20px);
  display:flex;align-items:center;gap:12px;
  font-size:13px;
  box-shadow:0 8px 32px rgba(0,0,0,0.4);
  animation:fadeUp 1s 2s ease both;
}
.ticker-dot{width:8px;height:8px;background:#22c55e;border-radius:50%;animation:pulse 2s infinite;}

@media(max-width:768px){
  nav{padding:16px 20px;}
  .stats-section{grid-template-columns:repeat(2,1fr);padding:40px 20px;}
  .features-grid,.roles-grid{grid-template-columns:1fr;}
  .features,.roles,.cta{padding:60px 20px;}
  .cta-box{padding:40px 24px;}
  .hero h1{font-size:40px;}
  footer{flex-direction:column;gap:10px;text-align:center;}
}
</style>
</head>
<body>

<!-- Custom cursor -->
<div class="cursor cursor-dot" id="cursor-dot"></div>
<div class="cursor cursor-ring" id="cursor-ring"></div>

<!-- Animated background canvas -->
<canvas id="bg-canvas"></canvas>

<!-- Live ticker -->
<div class="live-ticker">
  <div class="ticker-dot"></div>
  <span id="ticker-text">System Online — ICT Support Active</span>
</div>

<!-- NAV -->
<nav id="navbar">
  <div class="nav-logo">ICT<span>Desk</span></div>
  <div class="nav-links">
    <a href="#features">Features</a>
    <a href="#roles">Roles</a>
    <a href="#stats">Stats</a>
    <a href="index.php" class="btn-nav"><i class="fas fa-sign-in-alt me-2"></i>Sign In</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-badge">
    <div class="dot"></div>
    Enterprise ICT Support Platform — v3.0
  </div>
  <h1>
    Support that moves<br>
    <span class="line2">at the speed of now.</span>
  </h1>
  <p>A professional helpdesk system built for organisations that take ICT seriously. Tickets, SLAs, analytics — all in one powerful platform.</p>
  <div class="hero-actions">
    <a href="register.php" class="btn-hero primary">
      <i class="fas fa-rocket"></i> Get Started Free
    </a>
    <a href="index.php" class="btn-hero ghost">
      <i class="fas fa-sign-in-alt"></i> Sign In
    </a>
  </div>

  <!-- Mockup window -->
  <div class="hero-visual floating">
    <div class="mockup-window">
      <div class="mockup-bar">
        <div class="dot-red"></div><div class="dot-yellow"></div><div class="dot-green"></div>
        <div class="mockup-url">localhost/helpdesk/dashboard/admin.php</div>
      </div>
      <div class="mockup-body">
        <div class="mockup-grid">
          <div class="mockup-stat"><div class="num" style="color:#60a5fa;"><?= $totalTickets ?></div><div class="lbl">Total Tickets</div></div>
          <div class="mockup-stat"><div class="num" style="color:#4ade80;"><?= $resolved ?></div><div class="lbl">Resolved</div></div>
          <div class="mockup-stat"><div class="num" style="color:#fbbf24;"><?= $resRate ?>%</div><div class="lbl">SLA Rate</div></div>
          <div class="mockup-stat"><div class="num" style="color:#c084fc;"><?= $users ?></div><div class="lbl">Users</div></div>
        </div>
        <div class="mockup-row" style="animation-delay:0.1s">
          <span style="font-size:12px;color:var(--muted);font-family:monospace;">TKT-20260001</span>
          <span style="flex:1;font-size:13px;">Printer offline — 2nd floor</span>
          <span class="mockup-badge badge-crit">Critical</span>
          <span class="mockup-badge badge-inprog">In Progress</span>
        </div>
        <div class="mockup-row" style="animation-delay:0.2s">
          <span style="font-size:12px;color:var(--muted);font-family:monospace;">TKT-20260002</span>
          <span style="flex:1;font-size:13px;">Cannot access email account</span>
          <span class="mockup-badge badge-open">High</span>
          <span class="mockup-badge badge-open">Open</span>
        </div>
        <div class="mockup-row" style="animation-delay:0.3s">
          <span style="font-size:12px;color:var(--muted);font-family:monospace;">TKT-20260003</span>
          <span style="flex:1;font-size:13px;">Software installation request</span>
          <span class="mockup-badge" style="background:rgba(22,163,74,0.15);color:#4ade80;">Low</span>
          <span class="mockup-badge badge-done">Resolved</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- STATS SECTION -->
<section class="stats-section" id="stats">
  <div class="stat-item">
    <div class="stat-num" data-target="<?= max($totalTickets, 124) ?>">0</div>
    <div class="stat-label">Tickets Managed</div>
  </div>
  <div class="stat-item">
    <div class="stat-num" data-target="<?= $resRate ?>">0</div>
    <div class="stat-label">Resolution Rate %</div>
  </div>
  <div class="stat-item">
    <div class="stat-num" data-target="<?= max($articles, 12) ?>">0</div>
    <div class="stat-label">KB Articles</div>
  </div>
  <div class="stat-item">
    <div class="stat-num" data-target="<?= max($users, 48) ?>">0</div>
    <div class="stat-label">Active Users</div>
  </div>
</section>

<!-- FEATURES -->
<section class="features reveal" id="features">
  <div class="section-tag">What's Inside</div>
  <h2 class="section-title">Everything your IT team needs.</h2>
  <p class="section-sub">Built from the ground up with real workflows in mind. No bloat. Just powerful tools that work.</p>
  <div class="features-grid">
    <?php
    $features = [
      ['🎫','Ticket Lifecycle','Full workflow from submission to closure. Auto-generated ticket numbers, category classification, priority levels, and full reply threads.','Open → Resolved','#3b82f6'],
      ['⏱️','SLA Enforcement','Live countdown timers on every ticket. Colour-coded breach alerts. Four tiers: Critical (8h), High (24h), Medium (48h), Low (72h).','Real-time alerts','#ef4444'],
      ['📊','Advanced Analytics','Hourly heatmaps, monthly trends, department breakdowns, and a staff performance leaderboard. Data that tells a story.','Chart.js powered','#8b5cf6'],
      ['🔐','Role-Based Access','Three distinct user experiences: Administrator, IT Staff, End User. Pending approval workflow for new registrations.','RBAC security','#0ea5e9'],
      ['📚','Knowledge Base','Searchable self-service articles reduce repetitive tickets. View tracking, category filtering, and admin management.','Self-service','#10b981'],
      ['📤','Report Export','Download all ticket data as CSV or Excel with one click. Filterable by date, status, and priority. Ready for management review.','CSV + Excel','#f59e0b'],
      ['🔔','Smart Notifications','Email alerts on every ticket event: creation, reply, status change, assignment. Full notification log in the database.','Email alerts','#ec4899'],
      ['📢','Announcements','System-wide notices with expiry dates displayed on all dashboards. Four types: Info, Warning, Success, Urgent.','Broadcast tools','#14b8a6'],
      ['⭐','Satisfaction Ratings','Users rate IT support after ticket resolution. Star rating system builds accountability and drives service quality.','User feedback','#f97316'],
    ];
    foreach ($features as [$icon,$title,$desc,$tag,$color]):
    ?>
    <div class="feat-card reveal">
      <div class="feat-icon" style="background:<?= $color ?>22;"><?= $icon ?></div>
      <h3><?= $title ?></h3>
      <p><?= $desc ?></p>
      <span class="feat-tag"><?= $tag ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ROLES -->
<section class="roles" id="roles">
  <div class="roles-inner">
    <div class="section-tag reveal">User Roles</div>
    <h2 class="section-title reveal">Three roles. One platform.</h2>
    <div class="roles-grid">
      <div class="role-card admin reveal">
        <div class="role-icon">🛡️</div>
        <h3>Administrator</h3>
        <ul>
          <li>Full system oversight & control</li>
          <li>Approve/reject user registrations</li>
          <li>Assign tickets to IT staff</li>
          <li>Access all analytics & reports</li>
          <li>Manage knowledge base & announcements</li>
          <li>Export data to CSV & Excel</li>
        </ul>
      </div>
      <div class="role-card staff reveal">
        <div class="role-icon">🔧</div>
        <h3>IT Staff</h3>
        <ul>
          <li>Personal dashboard of assigned tickets</li>
          <li>Reply to and resolve tickets</li>
          <li>Update ticket status in real-time</li>
          <li>View analytics & performance stats</li>
          <li>Access knowledge base</li>
          <li>Performance tracked on leaderboard</li>
        </ul>
      </div>
      <div class="role-card user reveal">
        <div class="role-icon">👤</div>
        <h3>End User</h3>
        <ul>
          <li>Submit issues with priority levels</li>
          <li>Track ticket status & SLA timer</li>
          <li>View IT staff replies in real-time</li>
          <li>Search self-service knowledge base</li>
          <li>Rate support satisfaction</li>
          <li>Export ticket as PDF</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta reveal">
  <div class="cta-box">
    <div class="section-tag" style="margin-bottom:12px;">Ready?</div>
    <h2>Start managing ICT support<br>the right way.</h2>
    <p>Register your account — your administrator will approve it and you'll be ready to go.</p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
      <a href="register.php" class="btn-hero primary">
        <i class="fas fa-user-plus"></i> Create Account
      </a>
      <a href="index.php" class="btn-hero ghost">
        <i class="fas fa-sign-in-alt"></i> Already have an account
      </a>
    </div>
  </div>
</section>

<footer>
  <p>ICT Helpdesk System — ACS Final Year Project &copy; <?= date('Y') ?></p>
  <p style="color:var(--muted);font-size:12px;">Built with PHP + MySQL + Bootstrap 5</p>
</footer>

<script>
// ── CANVAS PARTICLE GRID ──────────────────────────────────────────────
const canvas = document.getElementById('bg-canvas');
const ctx = canvas.getContext('2d');
let W, H, particles = [], mouse = {x:null,y:null};

function resize(){ W=canvas.width=window.innerWidth; H=canvas.height=window.innerHeight; }
resize(); window.addEventListener('resize',resize);

class Particle {
  constructor(){ this.reset(); }
  reset(){
    this.x = Math.random()*W; this.y = Math.random()*H;
    this.vx = (Math.random()-0.5)*0.4; this.vy = (Math.random()-0.5)*0.4;
    this.r = Math.random()*1.5+0.5; this.alpha = Math.random()*0.5+0.1;
  }
  update(){
    this.x+=this.vx; this.y+=this.vy;
    if(this.x<0||this.x>W) this.vx*=-1;
    if(this.y<0||this.y>H) this.vy*=-1;
  }
  draw(){
    ctx.beginPath(); ctx.arc(this.x,this.y,this.r,0,Math.PI*2);
    ctx.fillStyle=`rgba(59,130,246,${this.alpha})`; ctx.fill();
  }
}

for(let i=0;i<120;i++) particles.push(new Particle());

function connectParticles(){
  for(let i=0;i<particles.length;i++){
    for(let j=i+1;j<particles.length;j++){
      const dx=particles[i].x-particles[j].x, dy=particles[i].y-particles[j].y;
      const d=Math.sqrt(dx*dx+dy*dy);
      if(d<130){
        ctx.beginPath();
        ctx.moveTo(particles[i].x,particles[i].y);
        ctx.lineTo(particles[j].x,particles[j].y);
        ctx.strokeStyle=`rgba(59,130,246,${0.12*(1-d/130)})`;
        ctx.lineWidth=0.5; ctx.stroke();
      }
    }
    // Connect to mouse
    if(mouse.x){
      const dx=particles[i].x-mouse.x, dy=particles[i].y-mouse.y;
      const d=Math.sqrt(dx*dx+dy*dy);
      if(d<180){
        ctx.beginPath();
        ctx.moveTo(particles[i].x,particles[i].y);
        ctx.lineTo(mouse.x,mouse.y);
        ctx.strokeStyle=`rgba(0,229,204,${0.2*(1-d/180)})`;
        ctx.lineWidth=0.8; ctx.stroke();
      }
    }
  }
}

function animate(){
  ctx.clearRect(0,0,W,H);
  particles.forEach(p=>{ p.update(); p.draw(); });
  connectParticles();
  requestAnimationFrame(animate);
}
animate();

window.addEventListener('mousemove',e=>{ mouse.x=e.clientX; mouse.y=e.clientY; });

// ── CUSTOM CURSOR ───────────────────────────────────────────────────────
const dot = document.getElementById('cursor-dot');
const ring = document.getElementById('cursor-ring');
let rx=0,ry=0;
document.addEventListener('mousemove',e=>{
  dot.style.left=e.clientX+'px'; dot.style.top=e.clientY+'px';
  rx+=(e.clientX-rx)*0.12; ry+=(e.clientY-ry)*0.12;
  ring.style.left=rx+'px'; ring.style.top=ry+'px';
});
document.querySelectorAll('a,button').forEach(el=>{
  el.addEventListener('mouseenter',()=>{ ring.style.width='56px'; ring.style.height='56px'; ring.style.opacity='0.5'; });
  el.addEventListener('mouseleave',()=>{ ring.style.width='36px'; ring.style.height='36px'; ring.style.opacity='1'; });
});

// Ring animation loop
function animRing(){
  rx+=(parseFloat(dot.style.left||0)-rx)*0.12;
  ry+=(parseFloat(dot.style.top||0)-ry)*0.12;
  ring.style.left=rx+'px'; ring.style.top=ry+'px';
  requestAnimationFrame(animRing);
}
animRing();

// ── NAVBAR SCROLL ───────────────────────────────────────────────────────
window.addEventListener('scroll',()=>{
  document.getElementById('navbar').classList.toggle('scrolled',window.scrollY>50);
});

// ── COUNTER ANIMATION ───────────────────────────────────────────────────
function animateCounter(el, target){
  let start=0, duration=2000, step=Math.ceil(target/60);
  const interval=setInterval(()=>{
    start+=step; if(start>=target){ start=target; clearInterval(interval); }
    el.textContent=start;
  },duration/60);
}
const io=new IntersectionObserver(entries=>{
  entries.forEach(e=>{
    if(e.isIntersecting){
      document.querySelectorAll('.stat-num').forEach(el=>{
        animateCounter(el,parseInt(el.dataset.target||0));
      });
      io.disconnect();
    }
  });
},{threshold:0.3});
io.observe(document.querySelector('.stats-section'));

// ── SCROLL REVEAL ───────────────────────────────────────────────────────
const ro=new IntersectionObserver(entries=>{
  entries.forEach((e,i)=>{ if(e.isIntersecting){ setTimeout(()=>e.target.classList.add('visible'),i*80); } });
},{threshold:0.1});
document.querySelectorAll('.reveal').forEach(el=>ro.observe(el));

// ── LIVE TICKER ─────────────────────────────────────────────────────────
const tickers=[
  '🎟 New ticket raised — Network connectivity issue',
  '✅ TKT-2026003 resolved in 2h 14m — Under SLA',
  '👤 New user registration pending approval',
  '🔥 Critical ticket assigned to IT Staff',
  '📊 Resolution rate this week: <?= $resRate ?>%',
  '🔔 SLA compliance holding strong',
];
let ti=0;
const tickerEl=document.getElementById('ticker-text');
setInterval(()=>{
  ti=(ti+1)%tickers.length;
  tickerEl.style.opacity=0;
  setTimeout(()=>{ tickerEl.textContent=tickers[ti]; tickerEl.style.opacity=1; },300);
},4000);
tickerEl.style.transition='opacity 0.3s';
</script>
</body>
</html>
