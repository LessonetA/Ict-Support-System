<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
global $conn;
requireRole('admin');
$user = currentUser(); $uid = $user['id'];
$pageTitle = 'Manage Users';
$error = $success = '';

if (isset($_GET['approve'])) {
    $aid = intval($_GET['approve']);
    $stmt = mysqli_prepare($conn, "UPDATE users SET status='active', approved_by=?, approved_at=NOW() WHERE id=?");
    mysqli_stmt_bind_param($stmt, "ii", $uid, $aid);
    mysqli_stmt_execute($stmt);
    logActivity($conn, $uid, 'user_approved', "Approved user ID $aid");
    $success = "✅ User approved — they can now log in.";
}
if (isset($_GET['suspend'])) {
    $sid = intval($_GET['suspend']);
    if ($sid !== $uid) { mysqli_query($conn, "UPDATE users SET status='suspended' WHERE id=$sid"); $success = "User suspended."; }
    else { $error = "You cannot suspend yourself."; }
}
if (isset($_GET['activate'])) {
    mysqli_query($conn, "UPDATE users SET status='active' WHERE id=".intval($_GET['activate']));
    $success = "User reactivated.";
}
if (isset($_GET['delete'])) {
    $did = intval($_GET['delete']);
    if ($did !== $uid) { mysqli_query($conn, "DELETE FROM users WHERE id=$did"); $success = "User deleted."; }
    else { $error = "You cannot delete yourself."; }
}
if (isset($_GET['role']) && isset($_GET['uid'])) {
    $nr = clean($_GET['role']); $ci = intval($_GET['uid']);
    if (in_array($nr,['user','staff','admin']) && $ci !== $uid) {
        mysqli_query($conn, "UPDATE users SET role='$nr' WHERE id=$ci"); $success = "Role updated.";
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name  = clean($_POST['full_name']??''); $email = clean($_POST['email']??'');
    $role  = clean($_POST['role']??'user'); $dept  = clean($_POST['department']??'');
    $pass  = $_POST['password']??'';
    if (empty($name)||empty($email)||empty($pass)) { $error = "Name, email and password required."; }
    else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name,email,password,role,department,status,approved_by,approved_at) VALUES (?,?,?,?,?,'active',?,NOW())");
        mysqli_stmt_bind_param($stmt, "sssssi", $name, $email, $hashed, $role, $dept, $uid);
        if (mysqli_stmt_execute($stmt)) $success = "User added and activated.";
        else $error = "Email already exists.";
    }
}

$pendR     = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE status='pending'");
$pendCount = mysqli_fetch_assoc($pendR)['cnt'] ?? 0;
$filter    = clean($_GET['filter'] ?? 'all');
$filterSQL = match($filter) { 'pending'=>"WHERE u.status='pending'", 'active'=>"WHERE u.status='active'", 'suspended'=>"WHERE u.status='suspended'", default=>'' };
$result = mysqli_query($conn, "SELECT u.*, (SELECT COUNT(*) FROM tickets WHERE user_id=u.id) as ticket_count FROM users u $filterSQL ORDER BY u.status='pending' DESC, u.created_at DESC");
$users  = mysqli_fetch_all($result, MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($error):   echo "<div class='alert alert-danger animate-in'><i class='fas fa-exclamation-circle me-2'></i>$error</div>";   endif; ?>
<?php if ($success): echo "<div class='alert alert-success animate-in'><i class='fas fa-check-circle me-2'></i>$success</div>"; endif; ?>

<?php if ($pendCount > 0): ?>
<div class="alert alert-warning animate-in">
    <span style="font-size:18px;">⏳</span>
    <div><strong><?= $pendCount ?> pending registration<?= $pendCount>1?'s':'' ?> awaiting your approval.</strong>
    <a href="?filter=pending" style="color:inherit;font-weight:700;margin-left:8px;">Review now →</a></div>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="form-card animate-in">
            <h6 style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:6px;"><i class="fas fa-user-plus me-2" style="color:var(--primary);"></i>Add New User</h6>
            <p style="font-size:12px;color:var(--muted);margin-bottom:18px;">Admin-created accounts are immediately active.</p>
            <form method="POST">
                <div class="mb-3"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Role *</label>
                    <select name="role" class="form-select">
                        <option value="user">End User</option><option value="staff">IT Staff</option><option value="admin">Admin</option>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Department</label><input type="text" name="department" class="form-control" placeholder="e.g. Finance"></div>
                <div class="mb-4"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required></div>
                <button type="submit" name="add_user" class="btn btn-primary w-100"><i class="fas fa-plus me-2"></i>Add User</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
            <?php foreach(['all'=>'All','pending'=>'Pending ⏳','active'=>'Active','suspended'=>'Suspended'] as $k=>$l): ?>
            <a href="?filter=<?= $k ?>" class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-outline-secondary' ?>">
                <?= $l ?><?php if($k==='pending'&&$pendCount>0): ?> <span style="background:var(--danger);color:#fff;border-radius:100px;padding:1px 6px;font-size:10px;margin-left:4px;"><?= $pendCount ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="table-card animate-in delay-1">
            <div class="table-card-header">
                <h6><i class="fas fa-users me-2" style="color:var(--primary);"></i><?= ucfirst($filter) ?> Users <span class="badge bg-secondary ms-2"><?= count($users) ?></span></h6>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>User</th><th>Role</th><th>Department</th><th>Status</th><th>Tickets</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $sc = match($u['status']??'active') {'pending'=>'warning','suspended'=>'danger',default=>'success'};
                        $sl = ucfirst($u['status']??'active');
                        $rc = match($u['role']) {'admin'=>'danger','staff'=>'primary',default=>'secondary'};
                    ?>
                    <tr style="<?= ($u['status']??'')=='pending'?'background:rgba(245,158,11,0.05);':'' ?>">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="avatar" style="width:32px;height:32px;font-size:12px;<?= ($u['status']??'')==='pending'?'background:linear-gradient(135deg,#f59e0b,#fcd34d);':'' ?>">
                                    <?= strtoupper(substr($u['full_name'],0,1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($u['full_name']) ?></div>
                                    <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-<?= $rc ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td style="font-size:12.5px;"><?= htmlspecialchars($u['department']??'—') ?></td>
                        <td>
                            <span class="badge bg-<?= $sc ?>"><?= $sl ?></span>
                            <?php if (($u['status']??'')=='pending'): ?>
                            <div style="font-size:10.5px;color:var(--muted);margin-top:2px;"><?= timeAgo($u['created_at']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= $u['ticket_count'] ?></span></td>
                        <td>
                            <?php if (($u['status']??'')==='pending'): ?>
                                <a href="?approve=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-sm btn-success me-1" onclick="return confirm('Approve this user?')"><i class="fas fa-check me-1"></i>Approve</a>
                                <a href="?delete=<?= $u['id'] ?>&filter=<?= $filter ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this registration?')"><i class="fas fa-times me-1"></i>Reject</a>
                            <?php else: ?>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if ($u['role']!=='admin'): ?><li><a class="dropdown-item" href="?role=admin&uid=<?= $u['id'] ?>&filter=<?= $filter ?>"><i class="fas fa-shield-alt me-2"></i>Make Admin</a></li><?php endif; ?>
                                    <?php if ($u['role']!=='staff'): ?><li><a class="dropdown-item" href="?role=staff&uid=<?= $u['id'] ?>&filter=<?= $filter ?>"><i class="fas fa-user-tie me-2"></i>Make Staff</a></li><?php endif; ?>
                                    <?php if ($u['role']!=='user'): ?><li><a class="dropdown-item" href="?role=user&uid=<?= $u['id'] ?>&filter=<?= $filter ?>"><i class="fas fa-user me-2"></i>Make User</a></li><?php endif; ?>
                                    <div class="dropdown-divider"></div>
                                    <?php if (($u['status']??'')==='active'&&$u['id']!==$uid): ?><li><a class="dropdown-item" style="color:var(--warning);" href="?suspend=<?= $u['id'] ?>&filter=<?= $filter ?>"><i class="fas fa-ban me-2"></i>Suspend</a></li><?php endif; ?>
                                    <?php if (($u['status']??'')==='suspended'): ?><li><a class="dropdown-item" style="color:var(--success);" href="?activate=<?= $u['id'] ?>&filter=<?= $filter ?>"><i class="fas fa-check-circle me-2"></i>Reactivate</a></li><?php endif; ?>
                                    <?php if ($u['id']!==$uid): ?><li><a class="dropdown-item" style="color:var(--danger);" href="?delete=<?= $u['id'] ?>&filter=<?= $filter ?>" onclick="return confirm('Delete this user permanently?')"><i class="fas fa-trash me-2"></i>Delete</a></li><?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
