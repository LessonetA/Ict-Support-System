<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
header('Content-Type: application/json');
$user=currentUser(); $uid=$user['id'];
if($user['role']==='admin'){$stats=getAllStats($conn);}
else{$stats=getUserStats($conn,$uid);}
$r=mysqli_query($conn,"SELECT COUNT(t.id) as cnt FROM tickets t LEFT JOIN sla_settings s ON s.priority=t.priority WHERE t.status NOT IN('resolved','closed') AND TIMESTAMPDIFF(HOUR,t.created_at,NOW())>s.hours_to_resolve");
$stats['sla_breached']=mysqli_fetch_assoc($r)['cnt']??0;
echo json_encode($stats);
