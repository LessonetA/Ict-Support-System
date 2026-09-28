<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
$user = currentUser();
$tid  = intval($_GET['id'] ?? 0);

// Only ticket owner can rate
$r = mysqli_query($conn, "SELECT t.*, u.full_name as staff_name FROM tickets t LEFT JOIN assignments a ON a.ticket_id=t.id LEFT JOIN users u ON a.staff_id=u.id WHERE t.id=$tid AND t.user_id={$user['id']} AND t.status IN ('resolved','closed')");
$ticket = mysqli_fetch_assoc($r);
if (!$ticket) { header("Location: manage.php"); exit(); }

// Check if already rated
$rr = mysqli_query($conn, "SELECT * FROM ticket_ratings WHERE ticket_id=$tid");
$existing = mysqli_fetch_assoc($rr);

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    $rating  = intval($_POST['rating'] ?? 0);
    $comment = clean($_POST['comment'] ?? '');
    if ($rating >= 1 && $rating <= 5) {
        $stmt = mysqli_prepare($conn, "INSERT INTO ticket_ratings (ticket_id, user_id, rating, comment) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "iiis", $tid, $user['id'], $rating, $comment);
        mysqli_stmt_execute($stmt);
        $success = true;
        logActivity($conn, $user['id'], 'ticket_rated', "Rated ticket #{$ticket['ticket_no']} — {$rating}/5 stars");
    }
}

$pageTitle = 'Rate Your Support Experience';
include '../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-6">
<?php if ($success): ?>
<div id="trigger-confetti"></div>
<div class="form-card text-center" style="padding:50px 40px;">
    <div style="font-size:64px;margin-bottom:20px;">🎉</div>
    <h4 style="font-family:'Syne',sans-serif;font-weight:800;margin-bottom:12px;">Thank you!</h4>
    <p style="color:var(--muted);margin-bottom:28px;font-size:15px;">Your feedback helps us improve our IT support service for everyone.</p>
    <a href="view.php?id=<?= $tid ?>" class="btn btn-primary btn-lg"><i class="fas fa-arrow-left me-2"></i>Back to Ticket</a>
</div>
<?php elseif ($existing): ?>
<div class="form-card text-center" style="padding:50px 40px;">
    <div style="font-size:48px;margin-bottom:16px;">⭐</div>
    <h4 style="font-family:'Syne',sans-serif;font-weight:700;">Already Rated</h4>
    <p style="color:var(--muted);margin-top:10px;">You rated this ticket <strong><?= $existing['rating'] ?>/5 stars</strong>. Thank you!</p>
    <a href="view.php?id=<?= $tid ?>" class="btn btn-outline-secondary mt-4"><i class="fas fa-arrow-left me-2"></i>Back</a>
</div>
<?php else: ?>
<div class="form-card">
    <!-- Ticket summary -->
    <div style="background:var(--surface2);border-radius:10px;padding:16px;margin-bottom:28px;display:flex;gap:14px;align-items:flex-start;">
        <div style="width:44px;height:44px;background:rgba(22,163,74,0.12);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">✅</div>
        <div>
            <div style="font-weight:600;font-size:14px;margin-bottom:3px;"><?= htmlspecialchars($ticket['title']) ?></div>
            <code style="font-size:11.5px;"><?= $ticket['ticket_no'] ?></code>
            <?php if ($ticket['staff_name']): ?>
            <div style="font-size:12px;color:var(--muted);margin-top:4px;"><i class="fas fa-user-tie me-1"></i>Handled by <?= htmlspecialchars($ticket['staff_name']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <h5 style="font-family:'Syne',sans-serif;font-weight:700;text-align:center;margin-bottom:8px;">How was your experience?</h5>
    <p style="text-align:center;color:var(--muted);font-size:13.5px;margin-bottom:28px;">Rate the quality of IT support you received</p>

    <form method="POST">
        <input type="hidden" name="rating" id="rating-input" value="0">

        <!-- Star Rating -->
        <div id="star-rating-container" class="star-rating mb-3">
            <span class="star" data-val="1">★</span>
            <span class="star" data-val="2">★</span>
            <span class="star" data-val="3">★</span>
            <span class="star" data-val="4">★</span>
            <span class="star" data-val="5">★</span>
        </div>
        <div id="rating-label" style="text-align:center;font-size:13px;color:var(--muted);margin-bottom:24px;height:20px;"></div>

        <!-- Quick options -->
        <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-bottom:20px;" id="quick-tags">
            <?php foreach (['Issue resolved quickly','Very helpful staff','Clear communication','Went above and beyond','First-time fix'] as $tag): ?>
            <button type="button" class="quick-tag" onclick="toggleTag(this)" style="padding:6px 14px;border:1.5px solid var(--border);border-radius:100px;font-size:12.5px;background:var(--surface2);color:var(--text2);cursor:pointer;transition:all 0.15s;">
                <?= $tag ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="mb-4">
            <label class="form-label">Additional Comments (optional)</label>
            <textarea name="comment" id="comment-area" class="form-control" rows="4" placeholder="Tell us more about your experience..."></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-100 btn-lg" id="submit-btn" disabled>
            <i class="fas fa-paper-plane me-2"></i>Submit Rating
        </button>
        <a href="view.php?id=<?= $tid ?>" class="btn btn-outline-secondary w-100 mt-2">Maybe later</a>
    </form>
</div>
<?php endif; ?>
</div>
</div>

<script>
const labels = {1:'😞 Poor',2:'😐 Fair',3:'🙂 Good',4:'😊 Great',5:'🤩 Excellent!'};
const ratingInput = document.getElementById('rating-input');
const ratingLabel = document.getElementById('rating-label');
const submitBtn   = document.getElementById('submit-btn');
const tags        = [];

// Star rating logic
const stars = document.querySelectorAll('.star');
let currentRating = 0;
stars.forEach((star,i)=>{
    star.addEventListener('mouseenter',()=>{
        stars.forEach((s,j)=>s.style.color=j<=i?'#f59e0b':'var(--border)');
        ratingLabel.textContent=labels[i+1];
    });
    star.addEventListener('mouseleave',()=>{
        stars.forEach((s,j)=>s.style.color=j<currentRating?'#f59e0b':'var(--border)');
        ratingLabel.textContent=currentRating?labels[currentRating]:'';
    });
    star.addEventListener('click',()=>{
        currentRating=i+1;
        ratingInput.value=currentRating;
        stars.forEach((s,j)=>s.style.color=j<currentRating?'#f59e0b':'var(--border)');
        submitBtn.disabled=false;
        ratingLabel.style.fontWeight='600';
        ratingLabel.style.color=currentRating>=4?'var(--success)':'var(--text)';
    });
});

function toggleTag(btn) {
    const active = btn.style.background.includes('59,130,246');
    btn.style.background=active?'var(--surface2)':'rgba(59,130,246,0.1)';
    btn.style.borderColor=active?'var(--border)':'var(--primary)';
    btn.style.color=active?'var(--text2)':'var(--primary)';
    const ca=document.getElementById('comment-area');
    if(!active){ca.value+=(ca.value?'\n':'')+btn.textContent.trim();}
}
</script>
<?php include '../includes/footer.php'; ?>
