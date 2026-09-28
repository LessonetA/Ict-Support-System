  </main>
</div><!-- end main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= $base ?>assets/js/app.js"></script>
<?php if (isset($extraScript)) echo $extraScript; ?>
<!-- ═══════════════════════════════════════════════════════
     ICT HELPDESK AI CHATBOT WIDGET
     Drop this entire block into footer.php before </body>
     ═══════════════════════════════════════════════════════ -->
<?php if (isLoggedIn()): ?>

<!-- Chatbot styles -->
<style>
/* ── Floating bubble ───────────────────────────────── */
#ict-chat-bubble {
  position:fixed; bottom:28px; right:28px; z-index:9998;
  width:58px; height:58px; border-radius:50%;
  background:linear-gradient(135deg,#1e3a5f,#0ea5e9);
  box-shadow:0 6px 24px rgba(14,165,233,.45);
  border:none; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  transition:transform .2s, box-shadow .2s;
  animation:chat-bounce 3s ease-in-out infinite;
}
#ict-chat-bubble:hover { transform:scale(1.1); box-shadow:0 10px 32px rgba(14,165,233,.6); }
#ict-chat-bubble svg { width:26px; height:26px; fill:#fff; }
#ict-chat-badge {
  position:absolute; top:-3px; right:-3px;
  width:18px; height:18px; border-radius:50%;
  background:#ef4444; color:#fff;
  font-size:10px; font-weight:700;
  display:none; align-items:center; justify-content:center;
  border:2px solid var(--bg,#0f172a);
}
@keyframes chat-bounce {
  0%,100%{ transform:translateY(0);} 50%{ transform:translateY(-6px); }
}

/* ── Chat window ───────────────────────────────────── */
#ict-chat-window {
  position:fixed; bottom:100px; right:28px; z-index:9999;
  width:370px; max-height:560px;
  background:var(--surface,#1e293b);
  border:1px solid var(--border,#334155);
  border-radius:18px;
  box-shadow:0 20px 60px rgba(0,0,0,.5);
  display:none; flex-direction:column;
  font-family:'Plus Jakarta Sans',sans-serif;
  overflow:hidden;
  animation:chat-slide-in .25s ease;
}
@keyframes chat-slide-in {
  from{ opacity:0; transform:translateY(20px) scale(.95); }
  to  { opacity:1; transform:translateY(0)   scale(1); }
}
#ict-chat-window.open { display:flex; }

/* Header */
#ict-chat-header {
  background:linear-gradient(135deg,#1e3a5f,#0ea5e9);
  padding:14px 18px;
  display:flex; align-items:center; justify-content:space-between;
  flex-shrink:0;
}
.ict-chat-avatar {
  width:36px; height:36px; border-radius:10px;
  background:rgba(255,255,255,.2);
  display:flex; align-items:center; justify-content:center;
  font-size:18px; margin-right:10px; flex-shrink:0;
}
.ict-chat-title { color:#fff; font-size:14px; font-weight:700; line-height:1.2; }
.ict-chat-subtitle { color:rgba(255,255,255,.75); font-size:11px; margin-top:1px; }
#ict-chat-close {
  background:rgba(255,255,255,.15); border:none; color:#fff;
  width:28px; height:28px; border-radius:8px; cursor:pointer;
  display:flex; align-items:center; justify-content:center; font-size:15px;
  transition:background .15s;
}
#ict-chat-close:hover { background:rgba(255,255,255,.25); }

/* Online pill */
.ict-online-pill {
  display:flex; align-items:center; gap:5px;
  background:rgba(34,197,94,.15);
  border:1px solid rgba(34,197,94,.3);
  border-radius:99px; padding:2px 8px;
  font-size:11px; color:#4ade80; font-weight:600;
}
.ict-online-dot {
  width:6px; height:6px; border-radius:50%;
  background:#22c55e; animation:online-pulse 2s infinite;
}

/* Messages area */
#ict-chat-messages {
  flex:1; overflow-y:auto; padding:16px;
  display:flex; flex-direction:column; gap:10px;
  scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.1) transparent;
}
#ict-chat-messages::-webkit-scrollbar { width:4px; }
#ict-chat-messages::-webkit-scrollbar-thumb { background:rgba(255,255,255,.1); border-radius:4px; }

/* Message bubbles */
.ict-msg {
  max-width:88%; padding:10px 13px;
  border-radius:14px; font-size:13px; line-height:1.55;
  animation:msg-pop .18s ease;
}
@keyframes msg-pop { from{ opacity:0; transform:scale(.95); } to{ opacity:1; transform:scale(1); } }
.ict-msg.bot {
  background:var(--surface-2,#0f172a);
  border:1px solid var(--border,#334155);
  color:var(--text-primary,#f1f5f9);
  border-radius:4px 14px 14px 14px; align-self:flex-start;
}
.ict-msg.user {
  background:linear-gradient(135deg,#1e3a5f,#0ea5e9);
  color:#fff; border-radius:14px 14px 4px 14px; align-self:flex-end;
}
.ict-msg-time { font-size:10px; opacity:.5; margin-top:4px; display:block; }

/* Typing indicator */
#ict-typing {
  display:none; align-self:flex-start;
  background:var(--surface-2,#0f172a);
  border:1px solid var(--border,#334155);
  border-radius:4px 14px 14px 14px;
  padding:10px 14px;
}
.ict-dots { display:flex; gap:4px; }
.ict-dots span {
  width:7px; height:7px; border-radius:50%;
  background:var(--text-muted,#64748b);
  animation:dot-bounce .9s infinite;
}
.ict-dots span:nth-child(2){ animation-delay:.15s; }
.ict-dots span:nth-child(3){ animation-delay:.3s; }
@keyframes dot-bounce { 0%,80%,100%{ transform:scale(0); } 40%{ transform:scale(1); } }

/* Suggest-ticket panel */
#ict-ticket-suggest {
  display:none;
  background:rgba(14,165,233,.08);
  border-top:1px solid rgba(14,165,233,.2);
  padding:12px 16px;
  flex-shrink:0;
}
#ict-ticket-suggest p {
  font-size:12.5px; color:var(--text-secondary,#94a3b8);
  margin:0 0 10px; line-height:1.5;
}
.ict-raise-btn {
  display:flex; align-items:center; justify-content:center; gap:7px;
  width:100%; padding:10px;
  background:linear-gradient(135deg,#1e3a5f,#0ea5e9);
  color:#fff; border:none; border-radius:10px;
  font-size:13px; font-weight:600; cursor:pointer;
  text-decoration:none; transition:opacity .15s;
}
.ict-raise-btn:hover { opacity:.9; color:#fff; }

/* Quick suggestions */
#ict-suggestions {
  padding:8px 16px 0; display:flex; flex-wrap:wrap; gap:6px; flex-shrink:0;
}
.ict-chip {
  padding:5px 11px; border-radius:99px; font-size:11.5px; font-weight:500;
  background:var(--surface-2,#0f172a); border:1px solid var(--border,#334155);
  color:var(--text-secondary,#94a3b8); cursor:pointer; transition:all .15s;
  white-space:nowrap;
}
.ict-chip:hover { background:rgba(14,165,233,.15); border-color:rgba(14,165,233,.4); color:#0ea5e9; }

/* Input area */
#ict-chat-input-area {
  padding:12px 16px; border-top:1px solid var(--border,#334155);
  display:flex; gap:8px; align-items:flex-end; flex-shrink:0;
}
#ict-chat-input {
  flex:1; resize:none; border-radius:10px; padding:9px 12px;
  background:var(--surface-2,#0f172a);
  border:1px solid var(--border,#334155);
  color:var(--text-primary,#f1f5f9); font-size:13px;
  font-family:inherit; max-height:80px; line-height:1.4;
  transition:border-color .15s;
}
#ict-chat-input:focus { outline:none; border-color:rgba(14,165,233,.5); }
#ict-chat-input::placeholder { color:var(--text-muted,#64748b); }
#ict-chat-send {
  width:38px; height:38px; border-radius:10px; flex-shrink:0;
  background:linear-gradient(135deg,#1e3a5f,#0ea5e9);
  border:none; color:#fff; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  transition:opacity .15s;
}
#ict-chat-send:hover { opacity:.85; }
#ict-chat-send:disabled { opacity:.4; cursor:not-allowed; }
#ict-chat-send svg { width:16px; height:16px; }

/* Powered by */
.ict-powered {
  text-align:center; padding:6px; font-size:10px;
  color:var(--text-muted,#64748b); flex-shrink:0;
  border-top:1px solid var(--border,#334155);
}
</style>

<!-- Floating bubble button -->
<button id="ict-chat-bubble" onclick="ictChatToggle()" title="ICT Support Assistant" aria-label="Open ICT Support Chat">
  <div id="ict-chat-badge">1</div>
  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 9h-2V5h2v6zm0 4h-2v-2h2v2z"/>
  </svg>
</button>

<!-- Chat window -->
<div id="ict-chat-window" role="dialog" aria-label="ICT Support Chat">

  <!-- Header -->
  <div id="ict-chat-header">
    <div style="display:flex;align-items:center;">
      <div class="ict-chat-avatar">🤖</div>
      <div>
        <div class="ict-chat-title">ICT Support Assistant</div>
        <div class="ict-chat-subtitle">Powered by AI · Instant answers</div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
      <div class="ict-online-pill"><div class="ict-online-dot"></div>Online</div>
      <button id="ict-chat-close" onclick="ictChatToggle()" title="Close">✕</button>
    </div>
  </div>

  <!-- Messages -->
  <div id="ict-chat-messages">
    <!-- Welcome message injected by JS -->
  </div>

  <!-- Typing indicator -->
  <div id="ict-typing" style="display:none;padding:0 16px 8px;">
    <div class="ict-dots"><span></span><span></span><span></span></div>
  </div>

  <!-- Ticket suggestion panel -->
  <div id="ict-ticket-suggest">
    <p>🎫 It looks like you may need hands-on IT support. Our team is ready to help — raise a ticket and we'll respond within your SLA timeframe.</p>
    <a href="<?= $base ?>tickets/create.php" class="ict-raise-btn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg>
      Raise a Support Ticket
    </a>
  </div>

  <!-- Quick-reply chips -->
  <div id="ict-suggestions">
    <div class="ict-chip" onclick="ictSendChip(this)">🔑 Password reset</div>
    <div class="ict-chip" onclick="ictSendChip(this)">📶 WiFi problem</div>
    <div class="ict-chip" onclick="ictSendChip(this)">🖨️ Printer issue</div>
    <div class="ict-chip" onclick="ictSendChip(this)">📧 Email setup</div>
    <div class="ict-chip" onclick="ictSendChip(this)">💻 PC running slow</div>
    <div class="ict-chip" onclick="ictSendChip(this)">💿 Install software</div>
  </div>

  <!-- Input -->
  <div id="ict-chat-input-area">
    <textarea id="ict-chat-input" rows="1" placeholder="Describe your IT issue..." maxlength="500"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();ictSendMessage();}"
      oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px';"></textarea>
    <button id="ict-chat-send" onclick="ictSendMessage()" title="Send">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
    </button>
  </div>

  <div class="ict-powered">⚡ ICT Helpdesk AI · Available 24/7</div>
</div>

<script>
(function() {
  'use strict';

  const WIN      = document.getElementById('ict-chat-window');
  const MSGS     = document.getElementById('ict-chat-messages');
  const INPUT    = document.getElementById('ict-chat-input');
  const SEND_BTN = document.getElementById('ict-chat-send');
  const TYPING   = document.getElementById('ict-typing');
  const SUGGEST  = document.getElementById('ict-ticket-suggest');
  const CHIPS    = document.getElementById('ict-suggestions');
  const BADGE    = document.getElementById('ict-chat-badge');
  const BASE     = '<?= $base ?>';

  let isOpen    = false;
  let isLoading = false;
  let history   = [];      // [{role:'user'|'assistant', content:'...'}]
  let hasOpened = false;

  // Show welcome badge on load (after 3s)
  setTimeout(() => {
    if (!hasOpened) {
      BADGE.style.display = 'flex';
    }
  }, 3000);

  // ── Toggle window ────────────────────────────────────────
  window.ictChatToggle = function() {
    isOpen = !isOpen;
    WIN.classList.toggle('open', isOpen);
    if (isOpen) {
      hasOpened = true;
      BADGE.style.display = 'none';
      if (MSGS.children.length === 0) addWelcomeMessage();
      setTimeout(() => INPUT.focus(), 100);
    }
  };

  // ── Welcome message ──────────────────────────────────────
  function addWelcomeMessage() {
    const name = '<?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>';
    addBotMessage(
      `👋 Hi **${name}**! I'm your ICT Support Assistant.\n\nI can help you resolve common IT issues instantly. Describe your problem or choose a topic below — and if I can't solve it, I'll help you raise a ticket with our IT team.`
    );
  }

  // ── Send chip shortcut ───────────────────────────────────
  window.ictSendChip = function(el) {
    const text = el.textContent.replace(/^[^\w]+/, '').trim();
    INPUT.value = text;
    ictSendMessage();
    CHIPS.style.display = 'none';
  };

  // ── Send message ─────────────────────────────────────────
  window.ictSendMessage = async function() {
    const msg = INPUT.value.trim();
    if (!msg || isLoading) return;

    // Hide chips after first message
    CHIPS.style.display = 'none';
    SUGGEST.style.display = 'none';

    addUserMessage(msg);
    INPUT.value = '';
    INPUT.style.height = 'auto';
    setLoading(true);

    history.push({ role: 'user', content: msg });

    try {
      const res = await fetch(BASE + 'api/chatbot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: msg, history: history.slice(-8) })
      });

      if (!res.ok) throw new Error('Network error');
      const data = await res.json();

      const reply = data.reply || 'Sorry, I could not get a response. Please raise a ticket.';
      history.push({ role: 'assistant', content: reply });
      addBotMessage(reply);

      if (data.suggest_ticket) {
        setTimeout(() => { SUGGEST.style.display = 'block'; scrollToBottom(); }, 300);
      }

    } catch (e) {
      addBotMessage('⚠️ I\'m having trouble connecting right now. Please raise a ticket and our IT team will assist you directly.');
      setTimeout(() => { SUGGEST.style.display = 'block'; scrollToBottom(); }, 300);
    } finally {
      setLoading(false);
    }
  };

  // ── UI helpers ───────────────────────────────────────────
  function addUserMessage(text) {
    const d = document.createElement('div');
    d.className = 'ict-msg user';
    d.innerHTML = escapeHtml(text) + `<span class="ict-msg-time">${getTime()}</span>`;
    MSGS.appendChild(d);
    scrollToBottom();
  }

  function addBotMessage(text) {
    const d = document.createElement('div');
    d.className = 'ict-msg bot';
    d.innerHTML = formatMarkdown(text) + `<span class="ict-msg-time">${getTime()}</span>`;
    MSGS.appendChild(d);
    scrollToBottom();
  }

  function setLoading(state) {
    isLoading = state;
    SEND_BTN.disabled = state;
    TYPING.style.display = state ? 'block' : 'none';
    if (state) scrollToBottom();
  }

  function scrollToBottom() {
    setTimeout(() => { MSGS.scrollTop = MSGS.scrollHeight; }, 50);
  }

  function getTime() {
    return new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
  }

  function escapeHtml(t) {
    return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  // Light markdown: **bold**, numbered lists, newlines
  function formatMarkdown(text) {
    return escapeHtml(text)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/^(\d+)\.\s+/gm, '<br><strong>$1.</strong> ')
      .replace(/\n/g, '<br>');
  }
})();
</script>

<?php endif; ?>
<!-- ═══════════════ END CHATBOT WIDGET ═══════════════ -->

</body>
</html>
