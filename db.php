<?php
// ════════════════════════════════════════════════
// db.php — Database & Utility Functions
// FIX: Added session_start() here so ALL pages
//      automatically have sessions available.
// ════════════════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host   = "localhost";
$dbname = "new_voting";  // ✅ your database name
$dbuser = "root";
$dbpass = "";            // default in XAMPP (change if needed)

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function getDB(): PDO {
    global $conn;
    return $conn;
}

function jsonRes(array $data, int $code = 200): void {
    // FIX: Only send JSON header if headers not already sent
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getInput(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return $_POST ?? [];
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) return $_POST ?? [];
    return $decoded ?? [];
}

function getClientIP(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';
}

function requireAdminLogin(): void {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: admin_login.php');
        exit;
    }
}

function requireVoterLogin(): void {
    // FIX: was checking $_SESSION['logged_in'] but login sets it to false
    //      on register. Now correctly checks the boolean value.
    if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: register.php');
        exit;
    }
}
