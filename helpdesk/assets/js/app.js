/* ============================================================
   ICT HELPDESK — App JS v5
   Dark mode · Toasts · Confetti · Counters · Live polling
   ============================================================ */

/* ── THEME ─────────────────────────────────────────────────── */
const THEME_KEY = 'ict_theme';
function applyTheme(t) {
  document.documentElement.setAttribute('data-theme', t);
  const icon = document.getElementById('theme-icon');
  if (icon) icon.className = t === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
}
(function init() {
  const saved = localStorage.getItem(THEME_KEY) || 'light';
  applyTheme(saved);
})();
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('theme-toggle');
  if (btn) btn.addEventListener('click', () => {
    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    localStorage.setItem(THEME_KEY, next);
    showToast(next === 'dark' ? '🌙 Dark mode on' : '☀️ Light mode on', 'info', 2000);
  });
});

/* ── TOASTS ─────────────────────────────────────────────────── */
function showToast(msg, type = 'info', duration = 4000) {
  let c = document.getElementById('toast-container');
  if (!c) {
    c = document.createElement('div');
    c.id = 'toast-container';
    document.body.appendChild(c);
  }
  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
  const t = document.createElement('div');
  t.className = `toast-item t-${type}`;
  t.innerHTML = `<span style="font-size:16px;flex-shrink:0;">${icons[type]||'ℹ️'}</span><div style="flex:1;">${msg}</div><button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;opacity:.4;font-size:18px;line-height:1;padding:0;color:inherit;">×</button>`;
  c.appendChild(t);
  setTimeout(() => {
    t.style.animation = 'toast-out .25s ease forwards';
    setTimeout(() => t.remove(), 260);
  }, duration);
}

/* ── ANIMATED COUNTERS ──────────────────────────────────────── */
function runCounter(el, target, ms = 1400) {
  const start = performance.now();
  (function frame(now) {
    const p = Math.min((now - start) / ms, 1);
    const eased = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(target * eased);
    if (p < 1) requestAnimationFrame(frame);
  })(start);
}
function initCounters() {
  const els = document.querySelectorAll('[data-count]');
  if (!els.length) return;
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) { runCounter(e.target, +e.target.dataset.count); io.unobserve(e.target); }
    });
  }, { threshold: .2 });
  els.forEach(el => { el.textContent = '0'; io.observe(el); });
}

/* ── CONFETTI ────────────────────────────────────────────────── */
function launchConfetti(ms = 3000) {
  let cv = document.getElementById('confetti-canvas');
  if (!cv) return;
  cv.style.display = 'block';
  cv.width = innerWidth; cv.height = innerHeight;
  const ctx = cv.getContext('2d');
  const cols = ['#3b82f6','#0ea5e9','#10b981','#f59e0b','#ec4899','#8b5cf6','#22c55e'];
  const particles = Array.from({length:180},() => ({
    x: Math.random()*cv.width, y: Math.random()*cv.height - cv.height,
    w: Math.random()*10+4, h: Math.random()*5+3,
    vx:(Math.random()-.5)*3, vy: Math.random()*4+2,
    rot: Math.random()*360, rv:(Math.random()-.5)*7,
    c: cols[Math.floor(Math.random()*cols.length)]
  }));
  const end = Date.now()+ms;
  (function draw() {
    ctx.clearRect(0,0,cv.width,cv.height);
    particles.forEach(p => {
      p.x+=p.vx; p.y+=p.vy; p.rot+=p.rv;
      if(p.y>cv.height){p.y=-20;p.x=Math.random()*cv.width;}
      ctx.save(); ctx.translate(p.x,p.y); ctx.rotate(p.rot*Math.PI/180);
      ctx.fillStyle=p.c; ctx.fillRect(-p.w/2,-p.h/2,p.w,p.h); ctx.restore();
    });
    if(Date.now()<end) requestAnimationFrame(draw); else cv.style.display='none';
  })();
}

/* ── SLA COUNTDOWN ───────────────────────────────────────────── */
function startSLACountdown(elId, secs) {
  const el = document.getElementById(elId);
  if (!el || secs <= 0) return;
  let s = secs;
  const fmt = n => String(Math.floor(n/3600)).padStart(2,'0')+'h '+String(Math.floor((n%3600)/60)).padStart(2,'0')+'m '+String(n%60).padStart(2,'0')+'s';
  const iv = setInterval(() => {
    s--;
    if (s <= 0) { el.textContent = 'SLA BREACHED'; el.style.color = 'var(--rose-500)'; clearInterval(iv); showToast('⚠️ SLA deadline breached!', 'error'); return; }
    el.textContent = fmt(s);
    el.style.color = s < 3600 ? 'var(--rose-500)' : s < 7200 ? 'var(--amber-500)' : 'var(--emerald-500)';
  }, 1000);
}

/* ── LIVE STATS POLLING ──────────────────────────────────────── */
let lastOpen = null;
function pollStats() {
  fetch('../api/stats.php').then(r=>r.json()).then(d => {
    ['total','open','in_progress','resolved','users','sla_breached'].forEach(k => {
      const el = document.getElementById('st-'+k.replace(/_/g,'-'));
      if (el && d[k] !== undefined) el.textContent = d[k];
    });
    const lu = document.getElementById('last-updated');
    const tag = document.getElementById('live-tag');
    if (lu) { lu.textContent = 'Live · '+new Date().toLocaleTimeString(); if(tag) tag.style.display='flex'; }
    if (lastOpen !== null && d.open > lastOpen)
      showToast(`🎟 ${d.open - lastOpen} new ticket(s) raised`, 'warning');
    lastOpen = d.open;
  }).catch(()=>{});
}

/* ── STAR RATING ─────────────────────────────────────────────── */
function initStars(containerId) {
  const c = document.getElementById(containerId);
  if (!c) return;
  const stars = c.querySelectorAll('.star');
  let selected = 0;
  const inp = document.getElementById('rating-input');
  stars.forEach((s,i) => {
    s.addEventListener('mouseenter', () => stars.forEach((x,j) => x.classList.toggle('lit', j <= i)));
    s.addEventListener('mouseleave', () => stars.forEach((x,j) => x.classList.toggle('lit', j < selected)));
    s.addEventListener('click', () => {
      selected = i+1;
      if (inp) inp.value = selected;
      stars.forEach((x,j) => x.classList.toggle('lit', j < selected));
      const msgs = ['','Sorry to hear that 😔','We\'ll try to do better','Thanks for the feedback 👍','Great to hear! 😊','Excellent! You made our day 🎉'];
      showToast(msgs[selected]||'Thanks!', selected >= 4 ? 'success' : 'info');
      const btn = document.getElementById('rating-submit');
      if (btn) btn.disabled = false;
    });
  });
}

/* ── CLOSE DROPDOWNS ON OUTSIDE CLICK ───────────────────────── */
document.addEventListener('click', e => {
  if (!e.target.closest('.dropdown')) {
    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
  }
});

/* ── AUTO DISMISS ALERTS ────────────────────────────────────── */
function initAlerts() {
  document.querySelectorAll('.alert .btn-close').forEach(btn => {
    btn.addEventListener('click', () => {
      const a = btn.closest('.alert');
      a.style.transition = 'opacity .2s';
      a.style.opacity = '0';
      setTimeout(() => a.remove(), 220);
    });
  });
  setTimeout(() => {
    document.querySelectorAll('.alert-success, .alert-info').forEach(a => {
      if (a.parentElement) { a.style.transition='opacity .3s'; a.style.opacity='0'; setTimeout(()=>a.remove(),320); }
    });
  }, 5000);
}

/* ── STAGGER TABLE ROWS ─────────────────────────────────────── */
function animateRows() {
  document.querySelectorAll('.table tbody tr').forEach((tr, i) => {
    tr.style.opacity = '0';
    tr.style.transform = 'translateY(8px)';
    tr.style.transition = 'opacity .3s ease, transform .3s ease';
    setTimeout(() => { tr.style.opacity = '1'; tr.style.transform = 'none'; }, i * 40);
  });
}

/* ── INIT ────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initCounters();
  initAlerts();
  initStars('star-rating-container');
  animateRows();
  if (document.getElementById('st-total')) { pollStats(); setInterval(pollStats, 30000); }
  if (document.getElementById('trigger-confetti')) launchConfetti();
});
