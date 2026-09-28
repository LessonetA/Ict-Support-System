<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$user = currentUser(); $uid = $user['id'];
$pageTitle = 'Manage Knowledge Base';
$error = $success = '';
if (isset($_GET['delete'])) { mysqli_query($conn,"DELETE FROM knowledge_base WHERE id=".intval($_GET['delete'])); header("Location: manage.php?msg=deleted"); exit(); }
$editing = null;
if (isset($_GET['edit'])) { $r = mysqli_query($conn,"SELECT * FROM knowledge_base WHERE id=".intval($_GET['edit'])); $editing = mysqli_fetch_assoc($r); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title = clean($_POST['title']??''); $content = trim($_POST['content']??''); $cat = clean($_POST['category']??'General'); $editId = intval($_POST['edit_id']??0);
    if (empty($title)||empty($content)) { $error="Title and content required."; }
    elseif ($editId>0) {
        $stmt = mysqli_prepare($conn,"UPDATE knowledge_base SET title=?,content=?,category=? WHERE id=?");
        mysqli_stmt_bind_param($stmt,"sssi",$title,$content,$cat,$editId);
        mysqli_stmt_execute($stmt); $success="Article updated!"; $editing=null;
    } else {
        $stmt = mysqli_prepare($conn,"INSERT INTO knowledge_base (title,content,category,created_by) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt,"sssi",$title,$content,$cat,$uid);
        mysqli_stmt_execute($stmt); $success="Article published!";
    }
}
$result = mysqli_query($conn,"SELECT * FROM knowledge_base ORDER BY created_at DESC");
$articles = mysqli_fetch_all($result, MYSQLI_ASSOC);
include '../includes/header.php';
?>
<?php if (isset($_GET['msg'])): ?><div class="alert alert-success animate-in"><i class="fas fa-check-circle me-2"></i>Article deleted.</div><?php endif; ?>
<?php if ($error):   echo "<div class='alert alert-danger animate-in'>$error</div>";   endif; ?>
<?php if ($success): echo "<div class='alert alert-success animate-in'>$success</div>"; endif; ?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="form-card animate-in">
            <h6 style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:18px;"><i class="fas fa-<?= $editing?'edit':'plus' ?> me-2" style="color:var(--primary);"></i><?= $editing?'Edit':'New' ?> Article</h6>
            <form method="POST">
                <?php if ($editing): ?><input type="hidden" name="edit_id" value="<?= $editing['id'] ?>"><?php endif; ?>
                <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editing['title']??'') ?>"></div>
                <div class="mb-3"><label class="form-label">Category</label><input type="text" name="category" class="form-control" placeholder="e.g. Hardware, Software" value="<?= htmlspecialchars($editing['category']??'General') ?>"></div>
                <div class="mb-4"><label class="form-label">Content *</label><textarea name="content" class="form-control" rows="10" required><?= htmlspecialchars($editing['content']??'') ?></textarea></div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= $editing?'Update':'Publish' ?></button>
                    <?php if ($editing): ?><a href="manage.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="table-card animate-in delay-1">
            <div class="table-card-header">
                <h6><i class="fas fa-book me-2" style="color:var(--primary);"></i>Articles <span class="badge bg-secondary ms-1"><?= count($articles) ?></span></h6>
                <a href="index.php" class="btn btn-sm btn-outline-primary">Public View</a>
            </div>
            <table class="table">
                <thead><tr><th>Title</th><th>Category</th><th>Views</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($articles as $a): ?>
                <tr>
                    <td style="font-weight:500;font-size:13px;"><?= htmlspecialchars($a['title']) ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($a['category']) ?></span></td>
                    <td style="font-size:12px;color:var(--muted);"><i class="fas fa-eye me-1"></i><?= $a['views'] ?></td>
                    <td>
                        <a href="manage.php?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <a href="manage.php?delete=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Delete?')">Del</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
