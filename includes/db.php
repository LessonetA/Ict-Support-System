<?php
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','ict_helpdesk');
$conn = mysqli_connect(DB_HOST,DB_USER,DB_PASS,DB_NAME);
if(!$conn) die("<div style='font-family:sans-serif;color:#ef4444;padding:40px;background:#060b18;min-height:100vh;'><h2>Database Connection Failed</h2><p>".mysqli_connect_error()."</p><p>Check <code>includes/db.php</code></p></div>");
mysqli_set_charset($conn,"utf8mb4");
?>
