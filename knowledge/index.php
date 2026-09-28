<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
$pageTitle='Knowledge Base';
$search=clean($_GET['search']??''); $cat=clean($_GET['category']??'');
$where=[]; if($search) $where[]="(title LIKE '%$search%' OR content LIKE '%$search%')"; if($cat) $where[]="category='$cat'";
$wSQL=$where?'WHERE '.implode(' AND ',$where):'';
$result=mysqli_query($conn,"SELECT * FROM knowledge_base $wSQL ORDER BY views DESC,created_at DESC");
$articles=mysqli_fetch_all($result,MYSQLI_ASSOC);
$catR=mysqli_query($conn,"SELECT DISTINCT category FROM knowledge_base ORDER BY category");
$cats=mysqli_fetch_all($catR,MYSQLI_ASSOC);
include '../includes/header.php';
?>
<div class="row g-4">
<div class="col-lg-3">
<div class="form-card mb-3 animate-in">
    <h6 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-search me-2" style="color:var(--primary);"></i>Search Articles</h6>
    <form method="GET">
        <input type="text" name="search" class="form-control mb-2" placeholder="Search..." value="<?=htmlspecialchars($search)?>">
        <select name="category" class="form-select mb-2"><option value="">All Categories</option><?php foreach($cats as $c): ?><option value="<?=htmlspecialchars($c['category'])?>" <?=$cat===$c['category']?'selected':''?>><?=htmlspecialchars($c['category'])?></option><?php endforeach; ?></select>
        <button type="submit" class="btn btn-primary w-100 mb-2">Search</button>
        <?php if($search||$cat): ?><a href="index.php" class="btn btn-outline-secondary w-100">Clear</a><?php endif; ?>
    </form>
</div>
<div class="form-card animate-in delay-1" style="background:rgba(59,130,246,0.05);border-color:rgba(59,130,246,0.15);">
    <h6 style="font-size:13px;font-weight:600;color:var(--primary);margin-bottom:8px;"><i class="fas fa-lightbulb me-2"></i>Can't find an answer?</h6>
    <p style="font-size:12.5px;color:var(--muted);margin-bottom:12px;">Raise a ticket and our IT team will help you directly.</p>
    <a href="../tickets/create.php" class="btn btn-primary w-100">Raise a Ticket</a>
</div>
</div>
<div class="col-lg-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 style="font-weight:700;margin:0;"><?=$search?"Results for \"$search\"":'All Articles'?> <span class="badge bg-secondary ms-2"><?=count($articles)?></span></h6>
        <?php if(currentUser()['role']==='admin'): ?><a href="manage.php" class="btn btn-sm btn-primary"><i class="fas fa-cog me-1"></i>Manage</a><?php endif; ?>
    </div>
    <?php if(empty($articles)): ?>
    <div style="text-align:center;padding:60px;color:var(--muted);"><i class="fas fa-book-open" style="font-size:40px;display:block;margin-bottom:12px;opacity:0.3;"></i>No articles found.</div>
    <?php else: ?>
    <div class="row g-3">
    <?php foreach($articles as $i=>$a): ?>
    <div class="col-md-6">
        <a href="view.php?id=<?=$a['id']?>" style="text-decoration:none;">
        <div class="form-card animate-in" style="height:100%;transition:transform 0.2s,box-shadow 0.2s;cursor:pointer;animation-delay:<?=$i*0.06?>s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 30px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div style="display:flex;justify-content:space-between;margin-bottom:10px;"><span class="badge bg-light"><?=htmlspecialchars($a['category'])?></span><span style="font-size:11px;color:var(--muted);"><i class="fas fa-eye me-1"></i><?=$a['views']?></span></div>
            <h6 style="font-weight:600;font-size:14px;margin-bottom:8px;"><?=htmlspecialchars($a['title'])?></h6>
            <p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.5;"><?=htmlspecialchars(substr($a['content'],0,110))?>...</p>
            <div style="margin-top:12px;font-size:12px;color:var(--primary);font-weight:600;">Read more →</div>
        </div></a>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div></div>
<?php include '../includes/footer.php'; ?>
