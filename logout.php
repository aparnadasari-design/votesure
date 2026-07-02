<?php
// logout.php — Logout  (FIXED)
// FIX: session_start() was missing — session_destroy() on an
//      unstarted session causes a PHP warning / does nothing.
//      Now it's handled properly via db.php.
require_once 'db.php'; // db.php already calls session_start()

session_unset();
session_destroy();

// Destroy cookie as well
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header('Location: register.php');
exit;
