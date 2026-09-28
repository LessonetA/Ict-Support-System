<?php
// ============================================================
// session.php — Session management with auto-timeout
// ============================================================

// Session timeout in seconds
// 30 minutes for staff/admin, 20 minutes for regular users
define('SESSION_TIMEOUT_ADMIN', 1800); // 30 min
define('SESSION_TIMEOUT_USER',  1200); // 20 min

// Warning shown X seconds before timeout
define('SESSION_WARN_BEFORE', 120); // 2 min warning

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,           // Session cookie (expires on browser close)
            'path'     => '/',
            'secure'   => false,       // Set true if HTTPS
            'httponly' => true,         // JS can't access cookie
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function checkSessionTimeout(): void {
    if (!isset($_SESSION['user_id'])) return;

    $timeout = in_array($_SESSION['role'] ?? '', ['admin','staff'])
        ? SESSION_TIMEOUT_ADMIN
        : SESSION_TIMEOUT_USER;

    $now = time();

    // First visit — set last activity
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $now;
        $_SESSION['session_start'] = $now;
        return;
    }

    $idle = $now - $_SESSION['last_activity'];

    if ($idle > $timeout) {
        // Session expired — destroy and redirect
        $role = $_SESSION['role'] ?? 'user';
        session_unset();
        session_destroy();
        $base = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/', strlen($_SERVER['DOCUMENT_ROOT'])) - 1);
        header("Location: " . ($base ?: '/') . "index.php?msg=Session+expired.+Please+sign+in+again.");
        exit();
    }

    // Update last activity
    $_SESSION['last_activity'] = $now;
    $_SESSION['time_remaining'] = $timeout - $idle;
}

function getSessionTimeRemaining(): int {
    if (!isset($_SESSION['user_id'])) return 0;
    $timeout = in_array($_SESSION['role'] ?? '', ['admin','staff'])
        ? SESSION_TIMEOUT_ADMIN : SESSION_TIMEOUT_USER;
    return max(0, $timeout - (time() - ($_SESSION['last_activity'] ?? time())));
}
