<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
$user=currentUser(); $uid=$user['id'];
$pageTitle='Raise a Ticket';
$categories=getCategories($conn);
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title=clean($_POST['title']??''); $desc=trim($_POST['description']??'');
    $cat=intval($_POST['category_id']??0); $pri=clean($_POST['priority']??'medium');
    if(empty($title)||empty($desc)){$error="Title and description are required.";}
    else {
        $tno=generateTicketNo($conn);
        $stmt=mysqli_prepare($conn,"INSERT INTO tickets (ticket_no,user_id,category_id,title,description,priority) VALUES (?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt,"siisss",$tno,$uid,$cat,$title,$desc,$pri);
        if(mysqli_stmt_execute($stmt)){
            $nid=mysqli_insert_id($conn);
            logActivity($conn,$uid,'ticket_created',"Raised ticket $tno");
            sendTicketNotification($conn,$nid,'new_ticket');
            header("Location: view.php?id=$nid&success=Ticket+raised+successfully"); exit();
        } else { $error="Failed to submit. Please try again."; }
    }
}
include '../includes/header.php';
?>
<div class="row justify-content-center">
<div class="col-lg-8">
<?php if($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>
<div class="form-card animate-in">
    <h5 style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:6px;"><i class="fas fa-plus-circle me-2" style="color:var(--primary);"></i>Raise a Support Ticket</h5>
    <p style="color:var(--muted);font-size:13px;margin-bottom:24px;">Be specific — the more detail you provide, the faster we can help you.</p>
    <form method="POST">
        <div class="mb-3"><label class="form-label">Issue Title *</label><input type="text" name="title" class="form-control" placeholder="e.g. Cannot connect to WiFi in Block B" required></div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Category *</label>
                <select name="category_id" class="form-select" required>
                    <option value="">— Select Category —</option>
                    <?php foreach($categories as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="low">🟢 Low — Not urgent</option>
                    <option value="medium" selected>🟡 Medium — Normal</option>
                    <option value="high">🔴 High — Affecting work</option>
                    <option value="critical">🚨 Critical — System down</option>
                </select>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label">Description *</label>
            <textarea name="description" class="form-control" rows="7" placeholder="Please describe:&#10;• What the issue is&#10;• When it started&#10;• What you were doing&#10;• Any error messages you saw&#10;• Steps you've already tried" required></textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-2"></i>Submit Ticket</button>
            <a href="../dashboard/user.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<div class="form-card mt-3 animate-in delay-2" style="background:rgba(59,130,246,0.05);border-color:rgba(59,130,246,0.15);">
    <h6 style="font-size:13px;font-weight:600;color:var(--primary);margin-bottom:10px;"><i class="fas fa-lightbulb me-2"></i>Tips for faster resolution</h6>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12.5px;color:var(--muted);">
        <div>✓ Include error codes or screenshots</div>
        <div>✓ Mention your device and OS version</div>
        <div>✓ Choose the right category</div>
        <div>✓ Set priority honestly — critical = system down</div>
    </div>
</div>
</div></div>
<?php include '../includes/footer.php'; ?>
