<?php
// admin_login.php — Admin Login Page  (FIXED)
// FIX 1: Added require_once 'db.php' which starts the session
// FIX 2: Redirect check was AFTER form processing, now BEFORE
require_once 'db.php';

// FIX: Redirect FIRST if already logged in (was at the bottom, too late)
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = getClientIP();

    if ($username && $password) {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // SUCCESS — set session
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $admin['id'];
                $_SESSION['admin_username']  = $admin['username'];
                session_regenerate_id(true); // security: regenerate session ID on login

                $db->prepare("INSERT INTO audit_logs (action, ip_address, result, details, created_at)
                              VALUES ('ADMIN_LOGIN', ?, 'ok', ?, NOW())")
                   ->execute([$ip, 'Admin login: ' . $username]);

                header('Location: admin_dashboard.php');
                exit;
            } else {
                // FAIL
                $db->prepare("INSERT INTO audit_logs (action, ip_address, result, details, created_at)
                              VALUES ('ADMIN_LOGIN', ?, 'fail', ?, NOW())")
                   ->execute([$ip, 'Failed admin login: ' . $username]);
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please check your DB connection in db.php.';
            error_log('[VoteSure] Admin Login DB Error: ' . $e->getMessage());
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admin Login — VoteSure</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{--saffron:#FF6B00;--ink:#0a0a0f;--paper:#f5f2eb;--green:#0A7A3E;--muted:#6b6b72;}
    body{font-family:'DM Sans',sans-serif;background:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;}
    body::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 30% 70%,rgba(255,107,0,0.08) 0%,transparent 55%),radial-gradient(ellipse at 70% 30%,rgba(10,122,62,0.06) 0%,transparent 50%);}
    .login-card{background:var(--paper);border-radius:6px;padding:2.5rem;width:100%;max-width:420px;position:relative;z-index:1;box-shadow:0 24px 80px rgba(0,0,0,0.4);}
    .tricolor-bar{height:4px;background:linear-gradient(to right,#FF9933 33.33%,#fff 33.33% 66.66%,#138808 66.66%);border-radius:4px 4px 0 0;margin:-2.5rem -2.5rem 2rem;width:calc(100% + 5rem);}
    .brand{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--ink);text-align:center;margin-bottom:0.3rem;}
    .brand span{color:var(--saffron);}
    .admin-badge{display:inline-flex;align-items:center;gap:0.4rem;background:rgba(255,107,0,0.1);color:var(--saffron);padding:0.3rem 0.8rem;border-radius:2px;font-family:'DM Mono',monospace;font-size:0.68rem;letter-spacing:0.1em;margin-bottom:1.8rem;}
    .vs-label{display:block;font-size:0.72rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--ink);margin-bottom:0.4rem;font-family:'DM Mono',monospace;}
    .vs-input{width:100%;padding:0.75rem 1rem;font-family:'DM Sans',sans-serif;font-size:0.88rem;background:var(--paper);color:var(--ink);border:1.5px solid rgba(10,10,15,0.18);border-radius:3px;outline:none;transition:border-color 0.2s,box-shadow 0.2s;}
    .vs-input:focus{border-color:var(--saffron);box-shadow:0 0 0 3px rgba(255,107,0,0.1);}
    .btn-login{width:100%;padding:0.85rem;background:var(--saffron);color:#fff;border:none;border-radius:3px;font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:600;cursor:pointer;transition:background 0.2s;}
    .btn-login:hover{background:#e05a00;}
    .error-box{background:rgba(220,38,38,0.08);border-left:3px solid #dc2626;padding:0.75rem 1rem;border-radius:3px;font-size:0.84rem;color:#dc2626;margin-bottom:1.2rem;}
    .vs-hint{font-size:0.72rem;color:var(--muted);text-align:center;margin-top:1.2rem;}
    .pw-toggle{position:relative;}
    .pw-toggle .toggle-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--muted);background:none;border:none;padding:0;}
  </style>
</head>
<body>
<div class="login-card">
  <div class="tricolor-bar"></div>
  <div class="text-center">
    <div class="brand">Vote<span>Sure</span></div>
    <div class="d-flex justify-content-center mb-4">
      <div class="admin-badge"><i class="bi bi-shield-lock"></i> ADMIN PANEL</div>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="error-box"><i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="mb-3">
      <label class="vs-label">Username</label>
      <input class="vs-input" type="text" name="username" placeholder="admin" required
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"/>
    </div>
    <div class="mb-4">
      <label class="vs-label">Password</label>
      <div class="pw-toggle">
        <input class="vs-input" type="password" name="password" id="adminPw" placeholder="••••••••" required/>
        <button type="button" class="toggle-eye" onclick="togglePw()">
          <i class="bi bi-eye" id="eyeIcon"></i>
        </button>
      </div>
    </div>
    <button class="btn-login" type="submit">
      <i class="bi bi-shield-check me-1"></i> Sign In to Admin Panel
    </button>
  </form>

  <p class="vs-hint">
    Default credentials: <strong>admin</strong> / <strong>Admin@123</strong><br>
    Change after first login.
  </p>
  <p class="vs-hint mt-2">
    <a href="register.php" style="color:var(--saffron);text-decoration:none;">← Back to VoteSure</a>
  </p>
</div>

<script>
function togglePw() {
  const pw = document.getElementById('adminPw');
  const ic = document.getElementById('eyeIcon');
  if (pw.type === 'password') {
    pw.type = 'text';
    ic.className = 'bi bi-eye-slash';
  } else {
    pw.type = 'password';
    ic.className = 'bi bi-eye';
  }
}
</script>
</body>
</html>
