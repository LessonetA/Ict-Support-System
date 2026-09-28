<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$user = currentUser(); $uid = $user['id'];
$pageTitle = 'Announcements';
$success = $error = '';
if (isset($_GET['delete'])) { mysqli_query($conn,"DELETE FROM announcements WHERE id=".intval($_GET['delete'])); $success="Announcement deleted."; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title = clean($_POST['title']??''); $msg = clean($_POST['message']??'');
    $type  = clean($_POST['type']??'info'); $exp = clean($_POST['expires_at']??'') ?: null;
    if (empty($title)||empty($msg)) { $error="Title and message required."; }
    else {
        $stmt = mysqli_prepare($conn,"INSERT INTO announcements (title,message,type,admin_id,expires_at) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt,"sssii",$title,$msg,$type,$uid,$uid);
        // rebind correctly
        $stmt = mysqli_prepare($conn,"INSERT INTO announcements (title,message,type,admin_id,expires_at) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt,"sssis",$title,$msg,$type,$uid,$exp);
        mysqli_stmt_execute($stmt); $success="Announcement posted!";
    }
}
$result = mysqli_query($conn,"SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON a.admin_id=u.id ORDER BY a.created_at DESC");
$announcements = mysqli_fetch_all($result, MYSQLI_ASSOC);
include '../includes/header.php';
?>
<?php if ($error):   echo "<div class='alert alert-danger animate-in'>$error</div>";   endif; ?>
<?php if ($success): echo "<div class='alert alert-success animate-in'>$success</div>"; endif; ?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="form-card animate-in">
            <h6 style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:18px;"><i class="fas fa-bullhorn me-2" style="color:var(--primary);"></i>Post Announcement</h6>
            <form method="POST">
                <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required placeholder="e.g. Scheduled Maintenance"></div>
                <div class="mb-3"><label class="form-label">Message *</label><textarea name="message" class="form-control" rows="4" required></textarea></div>
                <div class="row g-2 mb-4">
                    <div class="col"><label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="info">ℹ️ Info</option><option value="warning">⚠️ Warning</option>
                            <option value="success">✅ Success</option><option value="danger">🚨 Urgent</option>
                        </select>
                    </div>
                    <div class="col"><label class="form-label">Expires On</label><input type="date" name="expires_at" class="form-control" min="<?= date('Y-m-d') ?>"></div>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-bullhorn me-2"></i>Post Announcement</button>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="table-card animate-in delay-1">
            <div class="table-card-header"><h6><i class="fas fa-bullhorn me-2" style="color:var(--primary);"></i>All Announcements</h6></div>
            <?php if (empty($announcements)): ?>
                <div style="text-align:center;padding:40px;color:var(--muted);font-size:13px;">No announcements yet.</div>
            <?php else: foreach ($announcements as $ann):
                $expired = ($ann['expires_at']&&strtotime($ann['expires_at'])<time());
                $icons = ['info'=>'ℹ️','warning'=>'⚠️','success'=>'✅','danger'=>'🚨'];
            ?>
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);<?= $expired?'opacity:0.5;':'' ?>">
                <div style="display:flex;justify-content:space-between;gap:10px;">
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:13px;margin-bottom:4px;"><?= $icons[$ann['type']]??'ℹ️' ?> <?= htmlspecialchars($ann['title']) ?>
                            <?php if($expired): ?><span class="badge bg-secondary ms-2" style="font-size:10px;">Expired</span><?php endif; ?>
                        </div>
                        <p style="font-size:12.5px;color:var(--muted);margin:0 0 6px;"><?= htmlspecialchars($ann['message']) ?></p>
                        <span style="font-size:11px;color:var(--muted);">By <?= htmlspecialchars($ann['full_name']??'Admin') ?> · <?= timeAgo($ann['created_at']) ?><?php if($ann['expires_at']): ?> · Expires: <?= $ann['expires_at'] ?><?php endif; ?></span>
                    </div>
                    <a href="?delete=<?= $ann['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
