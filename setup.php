<?php
// ════════════════════════════════════════════════════════
// setup.php — ONE-CLICK INSTALLER for VoteSure
// ════════════════════════════════════════════════════════
// Open this in your browser ONCE:
//   http://localhost/votesure/setup.php
//
// It will:
//   1. Create all database tables
//   2. Insert sample election + candidates
//   3. Create admin account with CORRECT bcrypt hash
//   4. Show you the login credentials
//
// DELETE this file after setup is complete!
// ════════════════════════════════════════════════════════

// ── CONFIG — change these if needed ──────────────────────
$DB_HOST = 'localhost';
$DB_NAME = 'new_voting';
$DB_USER = 'root';
$DB_PASS = '';          // blank for XAMPP default
$ADMIN_USERNAME = 'admin';
$ADMIN_PASSWORD = 'Admin@123';
$ADMIN_EMAIL    = 'admin@votesure.com';
// ─────────────────────────────────────────────────────────

$steps = [];
$hasError = false;

function ok($msg)  { global $steps; $steps[] = ['ok',  $msg]; }
function err($msg) { global $steps, $hasError; $steps[] = ['err', $msg]; $hasError = true; }
function info($msg){ global $steps; $steps[] = ['info',$msg]; }

// ── STEP 1: Connect ──────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    ok("Connected to MySQL server");
} catch (PDOException $e) {
    err("Cannot connect to MySQL: " . $e->getMessage());
    err("Check DB_USER / DB_PASS in setup.php and make sure XAMPP MySQL is running.");
    goto render;
}

// ── STEP 2: Create database ──────────────────────────────
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$DB_NAME`");
    ok("Database '$DB_NAME' ready");
} catch (PDOException $e) {
    err("Failed to create database: " . $e->getMessage());
    goto render;
}

// ── STEP 3: Create tables ─────────────────────────────────
$tables = [
"voters" => "CREATE TABLE IF NOT EXISTS voters (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    voter_ref    VARCHAR(20)  UNIQUE NOT NULL,
    first_name   VARCHAR(80)  NOT NULL,
    last_name    VARCHAR(80)  NOT NULL,
    aadhaar      VARCHAR(12)  UNIQUE NOT NULL,
    voter_id     VARCHAR(30)  UNIQUE NOT NULL,
    dob          DATE         NOT NULL,
    gender       ENUM('Male','Female','Other') NOT NULL,
    mobile       VARCHAR(10)  UNIQUE NOT NULL,
    email        VARCHAR(150) UNIQUE NOT NULL,
    password     VARCHAR(255) NOT NULL,
    address      TEXT         NOT NULL,
    city         VARCHAR(80)  NOT NULL,
    pin          VARCHAR(6)   NOT NULL,
    status       ENUM('pending','verified','blocked') DEFAULT 'pending',
    has_voted    TINYINT(1)   DEFAULT 0,
    constituency VARCHAR(100),
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_aadhaar  (aadhaar),
    INDEX idx_voter_id (voter_id),
    INDEX idx_mobile   (mobile),
    INDEX idx_email    (email)
)",

"elections" => "CREATE TABLE IF NOT EXISTS elections (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    start_time  DATETIME     NOT NULL,
    end_time    DATETIME     NOT NULL,
    is_active   TINYINT(1)   DEFAULT 1,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
)",

"candidates" => "CREATE TABLE IF NOT EXISTS candidates (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    election_id  INT          NOT NULL,
    name         VARCHAR(150) NOT NULL,
    party        VARCHAR(150),
    symbol       VARCHAR(10),
    constituency VARCHAR(100),
    is_active    TINYINT(1)   DEFAULT 1,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
)",

"votes" => "CREATE TABLE IF NOT EXISTS votes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    voter_id     INT      NOT NULL UNIQUE,
    candidate_id INT      NOT NULL,
    election_id  INT      NOT NULL,
    ip_address   VARCHAR(45),
    voted_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voter_id)     REFERENCES voters(id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    FOREIGN KEY (election_id)  REFERENCES elections(id)
)",

"otp_store" => "CREATE TABLE IF NOT EXISTS otp_store (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    mobile     VARCHAR(10) NOT NULL,
    otp        VARCHAR(6)  NOT NULL,
    type       ENUM('register','login') DEFAULT 'login',
    expires_at DATETIME    NOT NULL,
    used       TINYINT(1)  DEFAULT 0,
    created_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mobile_otp (mobile, otp)
)",

"audit_logs" => "CREATE TABLE IF NOT EXISTS audit_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    voter_id   INT,
    action     VARCHAR(30)       NOT NULL,
    ip_address VARCHAR(45),
    result     ENUM('ok','fail') DEFAULT 'ok',
    details    TEXT,
    created_at DATETIME          DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action (ip_address, action, created_at)
)",

"admins" => "CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    email      VARCHAR(150),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)"
];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        ok("Table '$name' created / already exists");
    } catch (PDOException $e) {
        err("Failed to create table '$name': " . $e->getMessage());
    }
}

// ── STEP 4: Sample election ───────────────────────────────
try {
    $exists = $pdo->query("SELECT COUNT(*) FROM elections")->fetchColumn();
    if ($exists == 0) {
        $pdo->exec("INSERT INTO elections (title, description, start_time, end_time, is_active)
            VALUES (
                '2026 Municipal Election',
                'Ward 14 – Shivaji Nagar Municipal Corporation Election',
                NOW(),
                DATE_ADD(NOW(), INTERVAL 7 DAY),
                1
            )");
        $eid = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO candidates (election_id, name, party, symbol, constituency) VALUES (?,?,?,?,?)")
            ->execute([$eid, 'Rajiv Sharma', 'National Progress Party', '🌟', 'Ward 14']);
        $pdo->prepare("INSERT INTO candidates (election_id, name, party, symbol, constituency) VALUES (?,?,?,?,?)")
            ->execute([$eid, 'Priya Nair', 'United Democratic Front', '🌿', 'Ward 14']);
        $pdo->prepare("INSERT INTO candidates (election_id, name, party, symbol, constituency) VALUES (?,?,?,?,?)")
            ->execute([$eid, 'Arjun Mehta', "People's Rights Alliance", '⚖️', 'Ward 14']);

        ok("Sample election and 3 candidates inserted");
    } else {
        info("Elections already exist — skipping sample data");
    }
} catch (PDOException $e) {
    err("Failed to insert sample data: " . $e->getMessage());
}

// ── STEP 5: Create admin (THE KEY FIX) ───────────────────
// We generate the hash HERE in PHP — no copy-paste issues,
// no dollar-sign mangling in SQL editors.
try {
    // Remove any broken existing admin rows
    $pdo->exec("DELETE FROM admins WHERE username = 'admin'");
    $pdo->exec("DELETE FROM admins WHERE username = 'admin2'");

    // Generate hash FRESH — guaranteed to match PASSWORD_BCRYPT
    $hash = password_hash($ADMIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => 10]);

    // Verify it immediately
    if (!password_verify($ADMIN_PASSWORD, $hash)) {
        err("CRITICAL: Hash verification failed — PHP bcrypt may be broken on this server.");
    } else {
        $stmt = $pdo->prepare("INSERT INTO admins (username, password, email) VALUES (?, ?, ?)");
        $stmt->execute([$ADMIN_USERNAME, $hash, $ADMIN_EMAIL]);
        ok("Admin account created. Username: <strong>$ADMIN_USERNAME</strong> | Password: <strong>$ADMIN_PASSWORD</strong>");
        info("Hash stored: " . substr($hash, 0, 30) . "... (verified ✅)");
    }
} catch (PDOException $e) {
    err("Failed to create admin: " . $e->getMessage());
}

render:
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>VoteSure Setup</title>
<style>
  body{font-family:system-ui,sans-serif;background:#0a0a0f;color:#f5f2eb;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
  .card{background:#13121a;border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:2.5rem;max-width:680px;width:100%;}
  h1{font-size:1.6rem;font-weight:900;margin-bottom:0.3rem;}
  h1 span{color:#FF6B00;}
  .sub{color:#6b6b7a;font-size:0.85rem;margin-bottom:2rem;}
  .step{display:flex;align-items:flex-start;gap:1rem;padding:0.7rem 0;border-bottom:1px solid rgba(255,255,255,0.05);}
  .step:last-child{border-bottom:none;}
  .icon{font-size:1.1rem;flex-shrink:0;margin-top:1px;}
  .msg{font-size:0.88rem;line-height:1.5;}
  .ok   .icon::before{content:'✅'}
  .err  .icon::before{content:'❌'}
  .info .icon::before{content:'ℹ️'}
  .banner{margin-top:2rem;padding:1.2rem 1.5rem;border-radius:6px;font-size:0.9rem;line-height:1.8;}
  .banner.success{background:rgba(10,122,62,0.15);border:1px solid rgba(10,122,62,0.4);color:#6ee;} 
  .banner.fail{background:rgba(220,38,38,0.12);border:1px solid rgba(220,38,38,0.4);color:#f88;}
  .creds{background:rgba(255,107,0,0.1);border:1px solid rgba(255,107,0,0.3);border-radius:6px;padding:1.2rem 1.5rem;margin-top:1.5rem;}
  .creds table{width:100%;border-collapse:collapse;}
  .creds td{padding:0.4rem 0;font-size:0.9rem;}
  .creds td:first-child{color:#6b6b7a;width:40%;}
  .creds td strong{color:#FF6B00;font-family:monospace;font-size:1rem;}
  .links{display:flex;gap:1rem;margin-top:1.5rem;flex-wrap:wrap;}
  .link-btn{display:inline-flex;align-items:center;gap:0.4rem;padding:0.7rem 1.4rem;border-radius:4px;text-decoration:none;font-size:0.88rem;font-weight:600;transition:all 0.2s;}
  .link-btn.primary{background:#FF6B00;color:#fff;}.link-btn.primary:hover{background:#e05a00;}
  .link-btn.secondary{background:rgba(255,255,255,0.07);color:#f5f2eb;border:1px solid rgba(255,255,255,0.1);}.link-btn.secondary:hover{background:rgba(255,255,255,0.12);}
  .warn{background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);border-radius:4px;padding:0.8rem 1rem;margin-top:1rem;font-size:0.82rem;color:#fcd34d;}
</style>
</head>
<body>
<div class="card">
  <h1>Vote<span>Sure</span> Setup</h1>
  <p class="sub">Database installer — run once, then delete this file.</p>

  <div class="steps">
    <?php foreach ($steps as [$type, $msg]): ?>
    <div class="step <?= $type ?>">
      <div class="icon"></div>
      <div class="msg"><?= $msg ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$hasError): ?>
  <div class="banner success">
    ✅ <strong>Setup complete!</strong> Database is ready. You can now log in.
  </div>

  <div class="creds">
    <table>
      <tr><td>Admin URL</td><td><strong>admin_login.php</strong></td></tr>
      <tr><td>Username</td><td><strong><?= htmlspecialchars($ADMIN_USERNAME) ?></strong></td></tr>
      <tr><td>Password</td><td><strong><?= htmlspecialchars($ADMIN_PASSWORD) ?></strong></td></tr>
      <tr><td>Voter Login URL</td><td><strong>register.php</strong></td></tr>
      <tr><td>Results URL</td><td><strong>results.php</strong></td></tr>
    </table>
  </div>

  <div class="links">
    <a class="link-btn primary" href="admin_login.php">→ Go to Admin Login</a>
    <a class="link-btn secondary" href="register.php">→ Voter Login</a>
    <a class="link-btn secondary" href="results.php">→ Live Results</a>
  </div>

  <div class="warn">
    ⚠️ <strong>Security:</strong> Delete <code>setup.php</code> from your server after logging in successfully. Anyone with access to this file can reset your admin password.
  </div>

  <?php else: ?>
  <div class="banner fail">
    ❌ <strong>Setup failed.</strong> Fix the errors above and refresh this page.
    <br><br>
    Common fixes:<br>
    • Make sure <strong>XAMPP MySQL is running</strong> (green in XAMPP Control Panel)<br>
    • Check <code>DB_USER</code> / <code>DB_PASS</code> at the top of <code>setup.php</code><br>
    • If you set a MySQL root password, enter it in <code>DB_PASS</code>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
