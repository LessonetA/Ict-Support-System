<?php
// ─────────────────────────────────────────────────────────
//  Session configuration — must happen BEFORE session_start()
// ─────────────────────────────────────────────────────────
define('SESSION_TIMEOUT', 30 * 60);    // 30 minutes of inactivity
define('SESSION_WARN_BEFORE', 2 * 60); // warn 2 minutes before expiry

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,  
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ─────────────────────────────────────────────────────────
//  AJAX keep-alive ping endpoint (called by JS, exits early)
// ─────────────────────────────────────────────────────────
if (!empty($_SERVER['HTTP_X_SESSION_PING'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'expired']);
    } else {
        $_SESSION['last_activity'] = time();
        echo json_encode(['status' => 'ok']);
    }
    exit();
}

// ─────────────────────────────────────────────────────────
//  Session timeout enforcement
// ─────────────────────────────────────────────────────────
function checkSessionTimeout() {
    if (!isset($_SESSION['user_id'])) return;
    $now = time();
    if (isset($_SESSION['last_activity'])) {
        if (($now - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            $base = defined('BASE_URL') ? BASE_URL : '/';
            header("Location: {$base}index.php?msg=Your+session+expired+due+to+inactivity.");
            exit();
        }
    }
    $_SESSION['last_activity'] = $now;
}

checkSessionTimeout();

// ─────────────────────────────────────────────────────────
//  Auth helpers
// ─────────────────────────────────────────────────────────
function isLoggedIn()  { return isset($_SESSION['user_id']); }

function requireLogin() {
    if (!isLoggedIn()) { header("Location: " . BASE_URL . "index.php?msg=Please+login"); exit(); }
}
function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role && $_SESSION['role'] !== 'admin') {
        header("Location: " . BASE_URL . "index.php?error=Access+denied"); exit();
    }
}
function requireAnyRole($roles = []) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header("Location: " . BASE_URL . "index.php?error=Access+denied"); exit();
    }
}
function currentUser() {
    return ['id'=>$_SESSION['user_id'],'name'=>$_SESSION['full_name'],'email'=>$_SESSION['email'],'role'=>$_SESSION['role']];
}
function redirectByRole() {
    switch ($_SESSION['role']) {
        case 'admin': header("Location: dashboard/admin.php"); break;
        case 'staff': header("Location: dashboard/staff.php"); break;
        default:      header("Location: dashboard/user.php");  break;
    }
    exit();
}
