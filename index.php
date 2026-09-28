<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) redirectByRole();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = "Please fill in both fields.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($user && password_verify($password, $user['password'])) {
            $status = $user['status'] ?? 'active';
            if ($status === 'pending') {
                $error = "pending";
            } elseif ($status === 'suspended') {
                $error = "suspended";
            } else {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['role'];
                logActivity($conn, $user['id'], 'login', 'User logged in');
                redirectByRole();
            }
        } else {
            $error = "Invalid email or password. Please try again.";
        }
    }
}

$pendingR = mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE status='pending'");
$pendingCount = mysqli_fetch_assoc($pendingR)['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — ICT Helpdesk</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script>(function(){const t=localStorage.getItem('ict_theme')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body>
<div class="login-page">

  <!-- LEFT: Visual panel -->
  <div class="login-visual">
    <div class="grid-overlay"></div>
    <div class="orb1"></div>
    <div class="orb2"></div>
    <div class="visual-content">
      <div class="visual-title">ICT Support,<br><span>reimagined.</span></div>
      <p class="visual-sub">A precision-built helpdesk platform that brings order, accountability and data-driven insight to every support interaction.</p>
      <div class="feature-chips">
        <div class="feature-chip">
          <div class="chip-icon" style="background:rgba(59,130,246,.15);color:#60a5fa;">⏱</div>
          <div class="chip-text"><strong>SLA Enforcement</strong>Live countdown timers — never miss a deadline</div>
        </div>
        <div class="feature-chip">
          <div class="chip-icon" style="background:rgba(14,165,233,.15);color:#38bdf8;">📊</div>
          <div class="chip-text"><strong>Advanced Analytics</strong>Heatmaps, trends & staff performance</div>
        </div>
        <div class="feature-chip">
          <div class="chip-icon" style="background:rgba(5,150,105,.15);color:#34d399;">🔐</div>
          <div class="chip-text"><strong>Role-Based Access</strong>Admin · IT Staff · End User workflows</div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: Form panel -->
  <div class="login-form-panel">
    <div class="login-brand">
      <div class="l-icon"><i class="fas fa-headset"></i></div>
      <div>
        <div class="l-name">ICT Helpdesk</div>
        <div class="l-sub">SUPPORT SYSTEM v5</div>
      </div>
    </div>

    <div class="login-heading">Welcome back</div>
    <p class="login-sub">Sign in to your account to continue</p>

    <?php if ($error === 'pending'): ?>
    <div class="login-alert-info">
      <i class="fas fa-clock"></i>
      <div><strong>Account Pending Approval</strong><br>An administrator will review your registration shortly.</div>
    </div>
    <?php elseif ($error === 'suspended'): ?>
    <div class="login-alert-danger">
      <i class="fas fa-ban"></i>
      <div><strong>Account Suspended</strong><br>Contact the ICT department for assistance.</div>
    </div>
    <?php elseif ($error): ?>
    <div class="login-alert-danger">
      <i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
    <div class="login-alert-info">
      <i class="fas fa-info-circle"></i><?= htmlspecialchars($_GET['msg']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="login-form">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <div class="input-group">
          <div class="input-group-prefix"><i class="fas fa-envelope"></i></div>
          <input type="email" name="email" class="form-control"
            placeholder="you@organisation.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:24px;">
        <label class="form-label">Password</label>
        <div class="input-group">
          <div class="input-group-prefix"><i class="fas fa-lock"></i></div>
          <input type="password" name="password" id="pw-field" class="form-control" placeholder="••••••••" required>
          <div class="input-group-suffix" onclick="togglePw()" title="Show/hide password">
            <i class="fas fa-eye" id="pw-eye"></i>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-xl w-100" id="submit-btn">
        <i class="fas fa-sign-in-alt me-2"></i>Sign In
      </button>
    </form>

    <div class="login-divider"></div>
    <div style="text-align:center;font-size:13px;color:rgba(255,255,255,.35);">
      Don't have an account?
      <a href="register.php" class="login-footer-link" style="margin-left:5px;">Register here</a>
    </div>

    <?php if ($pendingCount > 0): ?>
    <div class="login-pending-badge">
      <i class="fas fa-user-clock"></i>
      <span><strong><?= $pendingCount ?></strong> registration<?= $pendingCount>1?'s':'' ?> awaiting admin approval</span>
    </div>
    <?php endif; ?>
  </div>

</div>
<script>
function togglePw() {
  const f = document.getElementById('pw-field');
  const e = document.getElementById('pw-eye');
  const visible = f.type === 'text';
  f.type = visible ? 'password' : 'text';
  e.className = visible ? 'fas fa-eye' : 'fas fa-eye-slash';
}
document.getElementById('login-form').addEventListener('submit', () => {
  const btn = document.getElementById('submit-btn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing in...';
  btn.disabled = true;
});
</script>
</body>
</html>
