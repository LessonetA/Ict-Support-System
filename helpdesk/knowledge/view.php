<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
$id = intval($_GET['id']??0);
$r  = mysqli_query($conn,"SELECT k.*, u.full_name as author FROM knowledge_base k LEFT JOIN users u ON k.created_by=u.id WHERE k.id=$id");
$article = mysqli_fetch_assoc($r);
if (!$article) { header("Location: index.php"); exit(); }
mysqli_query($conn,"UPDATE knowledge_base SET views=views+1 WHERE id=$id");
$pageTitle = $article['title'];
include '../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8">
    <div class="mb-3"><a href="index.php" style="font-size:13px;color:var(--primary);text-decoration:none;"><i class="fas fa-arrow-left me-1"></i>Back to Knowledge Base</a></div>
    <div class="form-card animate-in">
        <span class="badge bg-light mb-3"><?= htmlspecialchars($article['category']) ?></span>
        <h4 style="font-family:'Syne',sans-serif;font-weight:800;margin-bottom:16px;"><?= htmlspecialchars($article['title']) ?></h4>
        <div style="display:flex;gap:16px;font-size:12px;color:var(--muted);margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--border);">
            <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($article['author']??'ICT Team') ?></span>
            <span><i class="fas fa-calendar me-1"></i><?= date('d M Y',strtotime($article['created_at'])) ?></span>
            <span><i class="fas fa-eye me-1"></i><?= $article['views'] ?> views</span>
        </div>
        <div style="line-height:1.9;font-size:14px;white-space:pre-wrap;"><?= nl2br(htmlspecialchars($article['content'])) ?></div>
    </div>
    <div class="form-card mt-3 animate-in delay-1" style="background:rgba(59,130,246,0.06);border-color:rgba(59,130,246,0.2);text-align:center;">
        <h6 style="font-size:13px;font-weight:600;color:var(--primary);margin-bottom:8px;"><i class="fas fa-question-circle me-2"></i>Still need help?</h6>
        <p style="font-size:12.5px;color:var(--muted);margin-bottom:12px;">Our IT team is ready to assist you with any issue.</p>
        <a href="../tickets/create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Raise a Ticket</a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm ms-2">Browse More</a>
    </div>
    <?php if (currentUser()['role']==='admin'): ?>
    <div class="text-end mt-2"><a href="manage.php?edit=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit me-1"></i>Edit Article</a></div>
    <?php endif; ?>
</div></div>
<?php include '../includes/footer.php'; ?>
