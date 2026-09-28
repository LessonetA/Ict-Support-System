<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
global $conn;
$user  = currentUser(); $uid = $user['id'];
$pageTitle = 'My Profile';
$error = $success = $pwError = $pwSuccess = '';

$r = mysqli_query($conn,"SELECT * FROM users WHERE id=$uid");
$profile = mysqli_fetch_assoc($r);

if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['update_profile'])) {
    $name = clean($_POST['full_name']??''); $dept = clean($_POST['department']??''); $phone = clean($_POST['phone']??'');
    if (empty($name)) { $error="Name cannot be empty."; }
    else {
        $stmt = mysqli_prepare($conn,"UPDATE users SET full_name=?,department=?,phone=? WHERE id=?");
        mysqli_stmt_bind_param($stmt,"sssi",$name,$dept,$phone,$uid);
        if (mysqli_stmt_execute($stmt)) { $_SESSION['full_name']=$name; $success="Profile updated!"; $profile['full_name']=$name; $profile['department']=$dept; $profile['phone']=$phone; }
    }
}
if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['change_password'])) {
    $cur = $_POST['current_password']??''; $new = $_POST['new_password']??''; $con = $_POST['confirm_password']??'';
    if (!password_verify($cur,$profile['password'])) { $pwError="Current password incorrect."; }
    elseif (strlen($new)<6) { $pwError="New password must be at least 6 characters."; }
    elseif ($new!==$con) { $pwError="New passwords don't match."; }
    else {
        $hashed=password_hash($new,PASSWORD_DEFAULT);
        $stmt=mysqli_prepare($conn,"UPDATE users SET password=? WHERE id=?");
        mysqli_stmt_bind_param($stmt,"si",$hashed,$uid);
        mysqli_stmt_execute($stmt);
        $pwSuccess="Password changed successfully!";
        logActivity($conn,$uid,'password_change','Changed their password');
    }
}

$stats    = getUserStats($conn,$uid);
$actR     = mysqli_query($conn,"SELECT * FROM activity_log WHERE user_id=$uid ORDER BY created_at DESC LIMIT 8");
$acts     = mysqli_fetch_all($actR, MYSQLI_ASSOC);
$initials = strtoupper(substr($profile['full_name'],0,1));
include '../includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="form-card text-center mb-3 animate-in" style="padding:32px 20px;">
            <div style="width:80px;height:80px;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;margin:0 auto 16px;box-shadow:0 8px 24px rgba(15,76,129,0.3);"><?= $initials ?></div>
            <h5 style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($profile['full_name']) ?></h5>
            <p style="font-size:12.5px;color:var(--muted);margin-bottom:10px;"><?= htmlspecialchars($profile['email']) ?></p>
            <?php $rc=['admin'=>'danger','staff'=>'primary','user'=>'secondary'][$profile['role']]??'secondary'; ?>
            <span class="badge bg-<?= $rc ?>"><?= ucfirst($profile['role']) ?></span>
            <?php if($profile['department']): ?><p style="font-size:12.5px;color:var(--muted);margin-top:10px;"><i class="fas fa-building me-1"></i><?= htmlspecialchars($profile['department']) ?></p><?php endif; ?>
            <p style="font-size:11.5px;color:var(--muted);"><i class="fas fa-calendar me-1"></i>Member since <?= date('M Y',strtotime($profile['created_at'])) ?></p>
        </div>
        <div class="form-card animate-in delay-1" style="padding:18px;">
            <h6 style="font-size:13px;font-weight:700;margin-bottom:12px;"><i class="fas fa-chart-bar me-2" style="color:var(--primary);"></i>My Stats</h6>
            <?php foreach([['open','Open','#3b82f6'],['in_progress','In Progress','#f59e0b'],['resolved','Resolved','var(--success)'],['closed','Closed','var(--muted)']] as [$k,$l,$c]): ?>
            <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px;">
                <span style="color:var(--muted);"><?= $l ?></span><strong style="color:<?= $c ?>;"><?= $stats[$k] ?></strong>
            </div>
            <?php endforeach; ?>
            <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:13px;"><span style="font-weight:600;">Total</span><strong><?= $stats['total'] ?></strong></div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="form-card mb-4 animate-in delay-1">
            <h6 style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:18px;"><i class="fas fa-user-edit me-2" style="color:var(--primary);"></i>Edit Profile</h6>
            <?php if ($error):   echo "<div class='alert alert-danger'>$error</div>";   endif; ?>
            <?php if ($success): echo "<div class='alert alert-success'>$success</div>"; endif; ?>
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($profile['full_name']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Email (read-only)</label><input class="form-control" value="<?= htmlspecialchars($profile['email']) ?>" disabled style="opacity:0.6;"></div>
                    <div class="col-md-6"><label class="form-label">Department</label><input type="text" name="department" class="form-control" value="<?= htmlspecialchars($profile['department']??'') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($profile['phone']??'') ?>"></div>
                    <div class="col-12"><button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button></div>
                </div>
            </form>
        </div>
        <div class="form-card mb-4 animate-in delay-2" id="password">
            <h6 style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:18px;"><i class="fas fa-lock me-2" style="color:var(--primary);"></i>Change Password</h6>
            <?php if ($pwError):   echo "<div class='alert alert-danger'>$pwError</div>";   endif; ?>
            <?php if ($pwSuccess): echo "<div class='alert alert-success'>$pwSuccess</div>"; endif; ?>
            <form method="POST">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Current Password *</label><input type="password" name="current_password" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">New Password *</label><input type="password" name="new_password" class="form-control" id="newpw" placeholder="Min. 6 characters" required></div>
                    <div class="col-md-6"><label class="form-label">Confirm New Password *</label><input type="password" name="confirm_password" class="form-control" required></div>
                    <div class="col-12"><div id="pw-str" style="font-size:12px;margin-bottom:4px;"></div><button type="submit" name="change_password" class="btn btn-warning"><i class="fas fa-key me-2"></i>Change Password</button></div>
                </div>
            </form>
        </div>
        <?php if (!empty($acts)): ?>
        <div class="table-card animate-in delay-3">
            <div class="table-card-header"><h6><i class="fas fa-history me-2" style="color:var(--primary);"></i>Recent Activity</h6></div>
            <table class="table">
                <thead><tr><th>Action</th><th>Description</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($acts as $a): ?>
                <tr>
                    <td><span class="badge bg-secondary" style="font-size:11px;"><?= htmlspecialchars(str_replace('_',' ',$a['action'])) ?></span></td>
                    <td style="font-size:12.5px;color:var(--muted);"><?= htmlspecialchars($a['description']) ?></td>
                    <td style="font-size:12px;color:var(--muted);"><?= timeAgo($a['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
const pw=document.getElementById('newpw'),str=document.getElementById('pw-str');
if(pw)pw.addEventListener('input',()=>{
    const v=pw.value; let s=0;
    if(v.length>=6)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
    const l=['','Weak','Fair','Good','Strong 💪'],c=['','#ef4444','#f59e0b','#3b82f6','#16a34a'];
    str.innerHTML=v.length?'Strength: <strong style="color:'+c[s]+'">'+l[s]+'</strong>':'';
});
</script>
<?php include '../includes/footer.php'; ?>
