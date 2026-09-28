<?php
// Global Smart Search API
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
header('Content-Type: application/json');

$q    = clean($_GET['q'] ?? '');
$user = currentUser();
$uid  = $user['id'];
$base = '/helpdesk/';
$results = [];

if (strlen($q) < 2) { echo '[]'; exit; }
$sq = mysqli_real_escape_string($conn, $q);

// Search tickets
$where = $user['role']==='user' ? "AND t.user_id=$uid" : "";
$tR = mysqli_query($conn, "SELECT t.id, t.ticket_no, t.title, t.status, t.priority FROM tickets t WHERE (t.ticket_no LIKE '%$sq%' OR t.title LIKE '%$sq%') $where LIMIT 4");
while ($row = mysqli_fetch_assoc($tR)) {
    $pColors = ['low'=>'#4ade80','medium'=>'#ffd700','high'=>'#f59e0b','critical'=>'#ef4444'];
    $results[] = [
        'title'  => $row['ticket_no'] . ' — ' . htmlspecialchars($row['title']),
        'sub'    => ucfirst($row['priority']) . ' priority · ' . ucfirst(str_replace('_',' ',$row['status'])),
        'icon'   => '🎫',
        'iconBg' => 'rgba(0,229,255,0.15)',
        'url'    => $base . 'tickets/view.php?id=' . $row['id'],
    ];
}

// Search knowledge base
$kR = mysqli_query($conn, "SELECT id, title, category FROM knowledge_base WHERE title LIKE '%$sq%' OR content LIKE '%$sq%' LIMIT 3");
while ($row = mysqli_fetch_assoc($kR)) {
    $results[] = [
        'title'  => htmlspecialchars($row['title']),
        'sub'    => '📚 Knowledge Base · ' . htmlspecialchars($row['category']),
        'icon'   => '📚',
        'iconBg' => 'rgba(167,139,250,0.15)',
        'url'    => $base . 'knowledge/view.php?id=' . $row['id'],
    ];
}

// Search users (admin only)
if ($user['role'] === 'admin') {
    $uR = mysqli_query($conn, "SELECT id, full_name, email, role FROM users WHERE full_name LIKE '%$sq%' OR email LIKE '%$sq%' LIMIT 2");
    while ($row = mysqli_fetch_assoc($uR)) {
        $results[] = [
            'title'  => htmlspecialchars($row['full_name']),
            'sub'    => '👤 ' . ucfirst($row['role']) . ' · ' . htmlspecialchars($row['email']),
            'icon'   => '👤',
            'iconBg' => 'rgba(74,222,128,0.15)',
            'url'    => $base . 'admin/users.php',
        ];
    }
}

echo json_encode($results);
