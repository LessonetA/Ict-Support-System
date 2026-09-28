<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
if (isLoggedIn()) redirectByRole();
$error = $success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name=$_POST['full_name']??''; $email=clean($_POST['email']??'');
    $dept=clean($_POST['department']??''); $phone=clean($_POST['phone']??'');
    $pw=$_POST['password']??''; $cpw=$_POST['confirm_password']??'';
    if(empty($name)||empty($email)||empty($pw)){$error="Fill in all required fields.";}
    elseif($pw!==$cpw){$error="Passwords do not match.";}
    elseif(strlen($pw)<6){$error="Password needs at least 6 characters.";}
    else{
        $s=mysqli_prepare($conn,"SELECT id FROM users WHERE email=?");
        mysqli_stmt_bind_param($s,"s",$email); mysqli_stmt_execute($s); mysqli_stmt_store_result($s);
        if(mysqli_stmt_num_rows($s)>0){$error="Email already registered.";}
        else{
            $h=password_hash($pw,PASSWORD_DEFAULT);
            $s2=mysqli_prepare($conn,"INSERT INTO users (full_name,email,password,role,department,phone,status) VALUES(?,?,?,'user',?,?,'pending')");
            mysqli_stmt_bind_param($s2,"sssss",$name,$email,$h,$dept,$phone);
            $success = mysqli_stmt_execute($s2);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — ICT Helpdesk</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script>(function(){const t=localStorage.getItem('ict_theme')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body>
<div class="login-page" style="align-items:center;justify-content:center;">
  <div class="orb1" style="position:fixed;top:5%;left:10%;width:400px;height:400px;background:radial-gradient(circle,rgba(59,130,246,.1) 0%,transparent 70%);"></div>
  <div class="orb2" style="position:fixed;bottom:10%;right:5%;width:300px;height:300px;background:radial-gradient(circle,rgba(14,165,233,.08) 0%,transparent 70%);"></div>

  <div style="position:relative;z-index:1;width:100%;max-width:500px;padding:32px 24px;">

  <?php if ($success): ?>
    <div style="text-align:center;padding:40px;">
      <div style="width:72px;height:72px;background:rgba(217,119,6,.12);border:1px solid rgba(217,119,6,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 20px;">⏳</div>
      <h3 style="font-family:var(--font-display);font-weight:800;color:#fff;margin-bottom:10px;">Registration Submitted!</h3>
      <p style="color:rgba(255,255,255,.45);font-size:14px;line-height:1.7;margin-bottom:28px;">An ICT Administrator will review and approve your account shortly. You'll be able to log in once activated.</p>
      <a href="index.php" class="btn btn-primary btn-lg" style="background:linear-gradient(135deg,var(--blue-600),var(--teal-500));border:none;">
        <i class="fas fa-arrow-left me-2"></i>Back to Login
      </a>
    </div>
  <?php else: ?>

    <div class="login-brand" style="justify-content:center;margin-bottom:28px;">
      <div class="l-icon"><i class="fas fa-user-plus"></i></div>
      <div><div class="l-name">Create Account</div><div class="l-sub">ICT HELPDESK REGISTRATION</div></div>
    </div>

    <div style="background:rgba(14,165,233,.08);border:1px solid rgba(14,165,233,.2);border-radius:10px;padding:12px 16px;margin-bottom:22px;font-size:13px;color:rgba(56,189,248,.9);display:flex;align-items:center;gap:10px;">
      <i class="fas fa-shield-alt"></i>
      New accounts require <strong style="color:#fff;">admin approval</strong> before login
    </div>

    <?php if($error): ?>
    <div class="login-alert-danger"><i class="fas fa-exclamation-circle"></i><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="row g-3" style="margin-bottom:0;">
        <div class="col-12"><div class="form-group"><label class="form-label" style="color:rgba(255,255,255,.5);">Full Name *</label><div class="input-group"><div class="input-group-prefix"><i class="fas fa-user"></i></div><input type="text" name="full_name" class="form-control" placeholder="John Doe" required></div></div></div>
        <div class="col-12"><div class="form-group"><label class="form-label" style="color:rgba(255,255,255,.5);">Work Email *</label><div class="input-group"><div class="input-group-prefix"><i class="fas fa-envelope"></i></div><input type="email" name="email" class="form-control" placeholder="john@company.com" required></div></div></div>
        <div class="col-6"><div class="form-group"><label class="form-label" style="color:rgba(255,255,255,.5);">Department</label>
          <select name="department" class="form-select" style="background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.1);color:#fff;">
            <option value="">— Select —</option>
            <?php foreach(['Finance','Human Resources','ICT','Administration','Procurement','Marketing','Operations','Other'] as $d):?>
            <option><?=$d?></option><?php endforeach;?>
          </select>
        </div></div>
        <div class="col-6"><div class="form-group"><label class="form-label" style="color:rgba(255,255,255,.5);">Phone</label><input type="text" name="phone" class="form-control" placeholder="+254..."></div></div>
        <div class="col-6"><div class="form-group"><label class="form-label" style="color:rgba(255,255,255,.5);">Password *</label><input type="password" name="password" id="pw" class="form-control" placeholder="Min. 6 chars" required></div></div>
        <div class="col-6"><div class="form-group"><label class="form-label" style="color:rgba(255,255,255,.5);">Confirm *</label><input type="password" name="confirm_password" class="form-control" placeholder="Repeat" required></div></div>
        <div class="col-12" style="margin-top:4px;">
          <div id="pw-str" style="font-size:11.5px;margin-bottom:12px;min-height:18px;"></div>
          <button type="submit" class="btn btn-primary btn-xl w-100" style="background:linear-gradient(135deg,var(--blue-600),var(--teal-500));border:none;justify-content:center;">
            <i class="fas fa-paper-plane me-2"></i>Submit Registration
          </button>
        </div>
      </div>
    </form>
    <div class="login-divider"></div>
    <div style="text-align:center;font-size:13px;color:rgba(255,255,255,.35);">Already approved? <a href="index.php" class="login-footer-link" style="margin-left:4px;">Sign in</a></div>
  <?php endif; ?>
  </div>
</div>
<script>
const pw=document.getElementById('pw'),str=document.getElementById('pw-str');
if(pw&&str)pw.addEventListener('input',()=>{
  const v=pw.value; let s=0;
  if(v.length>=6)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
  const l=['','Weak','Fair','Good','Strong 💪'],c=['','#f87171','#fbbf24','#60a5fa','#34d399'];
  str.innerHTML=v?`<span style="color:${c[s]};font-size:11px;">Password strength: <strong>${l[s]}</strong></span>`:'';
});
</script>
</body>
</html>
