<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAnyRole(['admin','staff']);
$type = clean($_GET['type']??'csv');
$from = clean($_GET['from']??date('Y-m-01'));
$to   = clean($_GET['to']??date('Y-m-d'));
$stat = clean($_GET['status']??'');
$prio = clean($_GET['priority']??'');
$where = ["t.created_at BETWEEN '$from 00:00:00' AND '$to 23:59:59'"];
if ($stat) $where[] = "t.status='$stat'";
if ($prio) $where[] = "t.priority='$prio'";
$whereSQL = 'WHERE '.implode(' AND ',$where);
$result = mysqli_query($conn,"SELECT t.ticket_no,t.title,t.description,t.priority,t.status,t.created_at,t.resolved_at,TIMESTAMPDIFF(HOUR,t.created_at,IFNULL(t.resolved_at,NOW())) as hours_open,c.name as category,u.full_name as raised_by,u.email,u.department,su.full_name as assigned_to,(SELECT COUNT(*) FROM ticket_replies WHERE ticket_id=t.id) as replies,(SELECT AVG(rating) FROM ticket_ratings WHERE ticket_id=t.id) as avg_rating FROM tickets t LEFT JOIN categories c ON t.category_id=c.id LEFT JOIN users u ON t.user_id=u.id LEFT JOIN assignments a ON a.ticket_id=t.id LEFT JOIN users su ON a.staff_id=su.id $whereSQL ORDER BY t.created_at DESC");
$rows = mysqli_fetch_all($result,MYSQLI_ASSOC);
$headers = ['Ticket #','Title','Category','Priority','Status','Department','Raised By','Email','Assigned To','Hours Open','Replies','Avg Rating','Date Created','Date Resolved'];
if ($type==='excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="helpdesk_report_'.date('Ymd').'.xls"');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\">\n";
    echo "<Styles><Style ss:ID=\"h\"><Font ss:Bold=\"1\" ss:Color=\"#FFFFFF\"/><Interior ss:Color=\"#0F4C81\" ss:Pattern=\"Solid\"/></Style></Styles>\n";
    echo "<Worksheet ss:Name=\"Helpdesk Report\"><Table>\n<Row>";
    foreach ($headers as $h) echo "<Cell ss:StyleID=\"h\"><Data ss:Type=\"String\">".htmlspecialchars($h)."</Data></Cell>";
    echo "</Row>\n";
    foreach ($rows as $r) {
        echo "<Row>";
        $cells=[$r['ticket_no'],$r['title'],$r['category'],ucfirst($r['priority']),ucfirst(str_replace('_',' ',$r['status'])),$r['department']??'',$r['raised_by'],$r['email'],$r['assigned_to']??'Unassigned',$r['hours_open'].'h',$r['replies'],$r['avg_rating']?round($r['avg_rating'],1).'★':'No rating',$r['created_at'],$r['resolved_at']??''];
        foreach ($cells as $c) echo "<Cell><Data ss:Type=\"String\">".htmlspecialchars($c??(string)$c)."</Data></Cell>";
        echo "</Row>\n";
    }
    echo "</Table></Worksheet></Workbook>";
} else {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="helpdesk_report_'.date('Ymd').'.csv"');
    $out = fopen('php://output','w');
    fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ICT HELPDESK SYSTEM — TICKET EXPORT']);
    fputcsv($out,['Generated:',date('d M Y H:i'),'Period:',"$from to $to",'Total Records:',count($rows)]);
    fputcsv($out,[]);
    fputcsv($out,$headers);
    foreach ($rows as $r) {
        fputcsv($out,[$r['ticket_no'],$r['title'],$r['category'],ucfirst($r['priority']),ucfirst(str_replace('_',' ',$r['status'])),$r['department']??'',$r['raised_by'],$r['email'],$r['assigned_to']??'Unassigned',$r['hours_open'].'h',$r['replies'],$r['avg_rating']?round($r['avg_rating'],1).'/5':'No rating',$r['created_at'],$r['resolved_at']??'Not resolved']);
    }
    fclose($out);
}
exit();
