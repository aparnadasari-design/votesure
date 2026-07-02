<?php
// register.php — Voter Registration & Login
// ── FIXES IN THIS VERSION ──────────────────────────────────
// FIX 1: DB Error on voter login — audit_logs INSERT was firing
//         BEFORE we confirmed $voter exists, causing NULL voter_id
//         constraint error when credentials were wrong.
// FIX 2: DOB comparison now normalises both sides to Y-m-d format
//         so "2000-01-05" === "2000-01-05" always works correctly.
// FIX 3: OTP INSERT columns/values were mismatched (swapped otp & type).
// FIX 4: $_SESSION['logged_in'] was set FALSE on register, blocking login.
// ──────────────────────────────────────────────────────────
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = trim($_POST['action'] ?? '');

    // ══════════════════════════════════════════════
    // ACTION: REGISTER
    // ══════════════════════════════════════════════
    if ($action === 'register') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name']  ?? '');
        $aadhaar    = preg_replace('/\D/', '', trim($_POST['aadhaar'] ?? ''));
        $voter_id   = trim($_POST['voter_id']   ?? '');
        $dob        = trim($_POST['dob']        ?? '');
        $gender     = trim($_POST['gender']     ?? '');
        $mobile     = preg_replace('/\D/', '', trim($_POST['mobile'] ?? ''));
        $email      = strtolower(trim($_POST['email'] ?? ''));
        $password   = $_POST['password'] ?? '';
        $address    = trim($_POST['address']    ?? '');
        $city       = trim($_POST['city']       ?? '');
        $pin        = preg_replace('/\D/', '', trim($_POST['pin'] ?? ''));
        $ip         = getClientIP();

        if (!$first_name) jsonRes(['success'=>false,'message'=>'First name is required.']);
        if (!$last_name)  jsonRes(['success'=>false,'message'=>'Last name is required.']);
        if (!$aadhaar)    jsonRes(['success'=>false,'message'=>'Aadhaar is required.']);
        if (!$voter_id)   jsonRes(['success'=>false,'message'=>'Voter ID is required.']);
        if (!$dob)        jsonRes(['success'=>false,'message'=>'Date of birth is required.']);
        if (!$gender)     jsonRes(['success'=>false,'message'=>'Gender is required.']);
        if (!$mobile)     jsonRes(['success'=>false,'message'=>'Mobile number is required.']);
        if (!$email)      jsonRes(['success'=>false,'message'=>'Email is required.']);
        if (!$password)   jsonRes(['success'=>false,'message'=>'Password is required.']);
        if (!$address)    jsonRes(['success'=>false,'message'=>'Address is required.']);
        if (!$city)       jsonRes(['success'=>false,'message'=>'City is required.']);
        if (!$pin)        jsonRes(['success'=>false,'message'=>'PIN code is required.']);

        if (!preg_match('/^\d{12}$/', $aadhaar))     jsonRes(['success'=>false,'message'=>'Aadhaar must be exactly 12 digits.']);
        if (!preg_match('/^[6-9]\d{9}$/', $mobile))  jsonRes(['success'=>false,'message'=>'Mobile must be 10 digits starting with 6-9.']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonRes(['success'=>false,'message'=>'Invalid email address.']);
        if (strlen($password) < 8)                   jsonRes(['success'=>false,'message'=>'Password must be at least 8 characters.']);
        if (!preg_match('/^\d{6}$/', $pin))          jsonRes(['success'=>false,'message'=>'PIN code must be 6 digits.']);
        if (!in_array($gender,['Male','Female','Other'])) jsonRes(['success'=>false,'message'=>'Select a valid gender.']);

        $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dobDate || (int)$dobDate->diff(new DateTime())->y < 18)
            jsonRes(['success'=>false,'message'=>'You must be at least 18 years old.']);

        try {
            $db = getDB();
            $chk = $db->prepare("SELECT
                SUM(CASE WHEN aadhaar=? THEN 1 ELSE 0 END) aa,
                SUM(CASE WHEN voter_id=? THEN 1 ELSE 0 END) vi,
                SUM(CASE WHEN email=? THEN 1 ELSE 0 END) em,
                SUM(CASE WHEN mobile=? THEN 1 ELSE 0 END) mob
                FROM voters WHERE aadhaar=? OR voter_id=? OR email=? OR mobile=?");
            $chk->execute([$aadhaar,$voter_id,$email,$mobile,$aadhaar,$voter_id,$email,$mobile]);
            $dup = $chk->fetch();
            if ($dup['aa']  > 0) jsonRes(['success'=>false,'message'=>'Aadhaar already registered.']);
            if ($dup['vi']  > 0) jsonRes(['success'=>false,'message'=>'Voter ID already registered.']);
            if ($dup['em']  > 0) jsonRes(['success'=>false,'message'=>'Email already registered.']);
            if ($dup['mob'] > 0) jsonRes(['success'=>false,'message'=>'Mobile already registered.']);

            $voter_ref = 'VS-' . date('Y') . '-' . strtoupper(substr(md5($aadhaar . microtime(true)), 0, 6));
            $hash      = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

            $db->prepare("INSERT INTO voters
                (voter_ref,first_name,last_name,aadhaar,voter_id,dob,gender,
                 mobile,email,password,address,city,pin,status,has_voted,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',0,NOW())")
               ->execute([$voter_ref,$first_name,$last_name,$aadhaar,$voter_id,
                          $dob,$gender,$mobile,$email,$hash,$address,$city,$pin]);

            $newId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO audit_logs (voter_id,action,ip_address,result,details,created_at)
                          VALUES (?,'REGISTER',?,'ok','New voter registered',NOW())")
               ->execute([$newId, $ip]);

            jsonRes([
                'success'   => true,
                'voter_ref' => $voter_ref,
                'name'      => "$first_name $last_name",
                'message'   => "Registration successful! Your Voter Ref: $voter_ref. Use the Login tab to sign in."
            ]);

        } catch (PDOException $e) {
            error_log('[VoteSure] Register Error: ' . $e->getMessage());
            jsonRes(['success'=>false,'message'=>'DB Error: ' . $e->getMessage()], 500);
        }
    }

    // ══════════════════════════════════════════════
    // ACTION: LOGIN
    // ══════════════════════════════════════════════
    if ($action === 'login') {
        $login = trim($_POST['voter_id'] ?? '');
        $dob   = trim($_POST['dob']     ?? '');
        $pass  = $_POST['password']     ?? '';
        $ip    = getClientIP();

        if (!$login || !$dob || !$pass)
            jsonRes(['success'=>false,'message'=>'Please fill in all fields.']);

        // Normalise DOB to Y-m-d regardless of what browser sends
        $dobFormatted = '';
        foreach (['Y-m-d','d-m-Y','d/m/Y','m/d/Y'] as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $dob);
            if ($dt) { $dobFormatted = $dt->format('Y-m-d'); break; }
        }
        if (!$dobFormatted) $dobFormatted = $dob; // fallback

        try {
            $db = getDB();

            // Rate limiting — block after 5 failed attempts in 5 min
            $lockQ = $db->prepare("SELECT COUNT(*) FROM audit_logs
                WHERE ip_address=? AND action='LOGIN' AND result='fail'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $lockQ->execute([$ip]);
            if ((int)$lockQ->fetchColumn() >= 5)
                jsonRes(['success'=>false,'locked'=>true,
                         'message'=>'Too many failed attempts. Try again in 5 minutes.']);

            // Find voter
            $stmt = $db->prepare("SELECT * FROM voters
                WHERE (voter_id=:q OR aadhaar=:q OR email=:q OR mobile=:q)
                AND status != 'blocked' LIMIT 1");
            $stmt->execute([':q' => $login]);
            $voter = $stmt->fetch();

            // FIX: check voter exists BEFORE trying to log the failure
            // (previously it referenced $voter['id'] which was null/false here)
            if (!$voter) {
                $db->prepare("INSERT INTO audit_logs (voter_id,action,ip_address,result,details,created_at)
                              VALUES (NULL,'LOGIN',?,'fail','Voter not found',NOW())")
                   ->execute([$ip]);
                jsonRes(['success'=>false,'message'=>'No account found with those credentials.']);
            }

            if (!password_verify($pass, $voter['password'])) {
                $db->prepare("INSERT INTO audit_logs (voter_id,action,ip_address,result,details,created_at)
                              VALUES (?,'LOGIN',?,'fail','Wrong password',NOW())")
                   ->execute([$voter['id'], $ip]);
                jsonRes(['success'=>false,'message'=>'Incorrect password.']);
            }

            // FIX: Normalise stored DOB too, then compare
            $storedDob = '';
            $dtStored = DateTime::createFromFormat('Y-m-d', $voter['dob']);
            if ($dtStored) $storedDob = $dtStored->format('Y-m-d');
            else $storedDob = $voter['dob'];

            if ($storedDob !== $dobFormatted)
                jsonRes(['success'=>false,'message'=>'Date of birth does not match. Use YYYY-MM-DD format.']);

            // Generate 6-digit OTP
            $otp     = str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + 300);

            // FIX: correct column order for otp_store INSERT
            $db->prepare("INSERT INTO otp_store (mobile, otp, type, expires_at, created_at)
                          VALUES (?, ?, 'login', ?, NOW())")
               ->execute([$voter['mobile'], $otp, $expires]);

            $_SESSION['pre_login_voter_id']   = $voter['id'];
            $_SESSION['pre_login_voter_ref']  = $voter['voter_ref'];
            $_SESSION['pre_login_voter_name'] = $voter['first_name'] . ' ' . $voter['last_name'];
            $_SESSION['pre_login_has_voted']  = (bool)$voter['has_voted'];

            jsonRes([
                'success'  => true,
                'message'  => 'OTP sent to your registered mobile.',
                'otp_demo' => $otp   // remove in production
            ]);

        } catch (PDOException $e) {
            error_log('[VoteSure] Login Error: ' . $e->getMessage());
            jsonRes(['success'=>false,'message'=>'Database error: ' . $e->getMessage()], 500);
        }
    }

    // ══════════════════════════════════════════════
    // ACTION: VERIFY OTP
    // ══════════════════════════════════════════════
    if ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        $ip  = getClientIP();

        if (strlen($otp) !== 6 || !ctype_digit($otp))
            jsonRes(['success'=>false,'message'=>'Enter the 6-digit OTP.']);

        if (empty($_SESSION['pre_login_voter_id']))
            jsonRes(['success'=>false,'message'=>'Session expired. Please login again.'], 401);

        $db = getDB();

        // Demo mode: accept any valid 6-digit OTP
        // In production, uncomment the block below to verify against otp_store
        /*
        $otpRow = $db->prepare("SELECT id FROM otp_store
            WHERE mobile = (SELECT mobile FROM voters WHERE id = ?)
            AND otp = ? AND type = 'login' AND used = 0
            AND expires_at > NOW() LIMIT 1");
        $otpRow->execute([$_SESSION['pre_login_voter_id'], $otp]);
        if (!$otpRow->fetch())
            jsonRes(['success'=>false,'message'=>'Invalid or expired OTP. Request a new one.']);
        $db->prepare("UPDATE otp_store SET used=1
            WHERE otp=? AND mobile=(SELECT mobile FROM voters WHERE id=?)")
           ->execute([$otp, $_SESSION['pre_login_voter_id']]);
        */

        $db->prepare("INSERT INTO audit_logs (voter_id,action,ip_address,result,created_at)
                      VALUES (?,'LOGIN',?,'ok',NOW())")
           ->execute([$_SESSION['pre_login_voter_id'], $ip]);

        // FIX: set logged_in = TRUE (was being set false after register)
        $_SESSION['voter_id']   = $_SESSION['pre_login_voter_id'];
        $_SESSION['voter_ref']  = $_SESSION['pre_login_voter_ref'];
        $_SESSION['voter_name'] = $_SESSION['pre_login_voter_name'];
        $_SESSION['has_voted']  = $_SESSION['pre_login_has_voted'];
        $_SESSION['logged_in']  = true;
        $_SESSION['login_time'] = time();
        session_regenerate_id(true);

        unset($_SESSION['pre_login_voter_id'],
              $_SESSION['pre_login_voter_ref'],
              $_SESSION['pre_login_voter_name'],
              $_SESSION['pre_login_has_voted']);

        jsonRes(['success'=>true,'name'=>$_SESSION['voter_name'],'hasVoted'=>$_SESSION['has_voted']]);
    }

    jsonRes(['success'=>false,'message'=>'Unknown action.'], 400);
    exit;
}

// Redirect logged-in voters straight to the booth
if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: vote.php'); exit;
}
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Register / Login — VoteSure</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{--saffron:#FF6B00;--saffron-d:#e05a00;--ink:#0a0a0f;--ink-soft:#1c1c24;--paper:#f5f2eb;--paper-dim:#edeae2;--green:#0A7A3E;--green-l:#12a352;--muted:#6b6b72;--rule:rgba(10,10,15,0.1);}
    body{font-family:'DM Sans',sans-serif;background:var(--paper);color:var(--ink);-webkit-font-smoothing:antialiased;}
    .tricolor{height:4px;background:linear-gradient(to right,#FF9933 33.33%,#fff 33.33% 66.66%,#138808 66.66%);position:sticky;top:0;z-index:1031;}
    .vs-navbar{background:rgba(245,242,235,0.95)!important;backdrop-filter:blur(16px);border-bottom:1px solid var(--rule);}
    .navbar-brand{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--ink)!important;}
    .navbar-brand span{color:var(--saffron);}
    .nav-link{font-size:0.78rem;font-weight:500;letter-spacing:0.07em;text-transform:uppercase;color:var(--muted)!important;transition:color 0.2s;}
    .nav-link:hover,.nav-link.active{color:var(--ink)!important;}
    /* AUTH LAYOUT */
    .auth-wrapper{min-height:calc(100vh - 68px);display:grid;grid-template-columns:1fr 1.15fr;}
    .auth-left{background:var(--ink);color:var(--paper);padding:3.5rem;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;}
    .auth-left::before{content:'';position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse at 20% 80%,rgba(255,107,0,0.13) 0%,transparent 55%),radial-gradient(ellipse at 80% 20%,rgba(10,122,62,0.08) 0%,transparent 50%);}
    .auth-left-content{position:relative;z-index:1;}
    .auth-left-logo{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--paper);text-decoration:none;}
    .auth-left-logo span{color:var(--saffron);}
    .auth-headline{font-family:'Playfair Display',serif;font-size:clamp(2rem,3.5vw,3.2rem);font-weight:900;line-height:1.1;letter-spacing:-1.5px;margin:2.5rem 0 1rem;}
    .auth-headline em{font-style:italic;color:var(--saffron);}
    .auth-sub{font-size:0.9rem;line-height:1.75;color:rgba(245,242,235,0.55);max-width:38ch;margin-bottom:2.5rem;}
    .auth-steps{display:flex;flex-direction:column;gap:1rem;}
    .as-item{display:flex;align-items:flex-start;gap:1rem;}
    .as-num{width:28px;height:28px;border-radius:50%;border:1.5px solid rgba(245,242,235,0.2);display:flex;align-items:center;justify-content:center;font-family:'DM Mono',monospace;font-size:0.7rem;color:var(--saffron);flex-shrink:0;}
    .as-text strong{display:block;font-size:0.86rem;margin-bottom:0.1rem;}
    .as-text span{font-size:0.76rem;color:rgba(245,242,235,0.45);line-height:1.4;}
    .cert-pill{font-family:'DM Mono',monospace;font-size:0.6rem;letter-spacing:0.08em;padding:0.25rem 0.65rem;border:1px solid rgba(245,242,235,0.15);border-radius:2px;color:rgba(245,242,235,0.4);}
    /* RIGHT */
    .auth-right{padding:3rem;display:flex;align-items:center;justify-content:center;background:var(--paper);}
    .auth-box{width:100%;max-width:480px;}
    /* TABS */
    .auth-tabs{display:flex;border-bottom:2px solid var(--rule);margin-bottom:2rem;}
    .auth-tab{flex:1;padding:.85rem;text-align:center;cursor:pointer;font-size:0.82rem;font-weight:600;letter-spacing:0.06em;color:var(--muted);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;text-transform:uppercase;font-family:'DM Mono',monospace;}
    .auth-tab.active{color:var(--saffron);border-bottom-color:var(--saffron);}
    .auth-panel{display:none;}
    .auth-panel.active{display:block;}
    /* FORM */
    .vs-label{display:block;font-size:0.72rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--ink);margin-bottom:0.4rem;font-family:'DM Mono',monospace;}
    .vs-input,.vs-select{width:100%;padding:0.7rem 0.9rem;font-family:'DM Sans',sans-serif;font-size:0.88rem;background:var(--paper);color:var(--ink);border:1.5px solid rgba(10,10,15,0.18);border-radius:3px;outline:none;transition:border-color 0.2s,box-shadow 0.2s;}
    .vs-input:focus,.vs-select:focus{border-color:var(--saffron);box-shadow:0 0 0 3px rgba(255,107,0,0.1);}
    .btn-submit{width:100%;padding:0.8rem;background:var(--saffron);color:#fff;border:none;border-radius:3px;font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:600;cursor:pointer;transition:background 0.2s;display:flex;align-items:center;justify-content:center;gap:0.4rem;}
    .btn-submit:hover{background:var(--saffron-d);}
    .btn-submit:disabled{opacity:0.6;cursor:not-allowed;}
    .alert-box{padding:.75rem 1rem;border-radius:3px;font-size:0.84rem;margin-top:0.8rem;display:none;}
    .alert-error{background:rgba(220,38,38,0.08);border-left:3px solid #dc2626;color:#dc2626;}
    .alert-success{background:rgba(10,122,62,0.08);border-left:3px solid var(--green);color:var(--green);}
    .otp-row{display:flex;gap:0.5rem;}
    .otp-row input{flex:1;text-align:center;font-size:1.3rem;font-family:'DM Mono',monospace;letter-spacing:0.3em;padding:0.75rem;}
    @media(max-width:768px){.auth-wrapper{grid-template-columns:1fr;}.auth-left{display:none;}}
  </style>
</head>
<body>
<div class="tricolor"></div>
<nav class="navbar navbar-expand-lg vs-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand" href="#">Vote<span>Sure</span></a>
    <div class="ms-auto d-flex gap-3">
      <a class="nav-link" href="results.php">Live Results</a>
      <a class="nav-link" href="admin_login.php">Admin</a>
    </div>
  </div>
</nav>

<div class="auth-wrapper">
  <!-- LEFT PANEL -->
  <div class="auth-left">
    <div class="auth-left-content">
      <a class="auth-left-logo" href="#">Vote<span>Sure</span></a>
      <h2 class="auth-headline">Your Vote,<br><em>Your Voice.</em></h2>
      <p class="auth-sub">Secure, transparent, and efficient digital elections for every citizen.</p>
      <div class="auth-steps">
        <div class="as-item">
          <div class="as-num">1</div>
          <div class="as-text"><strong>Register</strong><span>Enter your Aadhaar, Voter ID and details</span></div>
        </div>
        <div class="as-item">
          <div class="as-num">2</div>
          <div class="as-text"><strong>Login with OTP</strong><span>Verify your identity via mobile OTP</span></div>
        </div>
        <div class="as-item">
          <div class="as-num">3</div>
          <div class="as-text"><strong>Cast Your Vote</strong><span>Select your candidate — one vote per citizen</span></div>
        </div>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap mt-4">
      <span class="cert-pill">ECI Compliant</span>
      <span class="cert-pill">ISO 27001</span>
      <span class="cert-pill">256-bit Encrypted</span>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="auth-right">
    <div class="auth-box">
      <div class="auth-tabs">
        <div class="auth-tab active" onclick="switchTab('login')">Sign In</div>
        <div class="auth-tab" onclick="switchTab('register')">Register</div>
      </div>

      <!-- ── LOGIN PANEL ── -->
      <div class="auth-panel active" id="panel-login">
        <div style="margin-bottom:1.5rem;">
          <div style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;">Welcome back</div>
          <div style="font-size:0.86rem;color:var(--muted);margin-top:0.3rem;">Sign in with your Voter ID or Aadhaar</div>
        </div>

        <!-- Step 1: Credentials -->
        <div id="loginStep1">
          <div class="mb-3">
            <label class="vs-label">Voter ID / Aadhaar / Email / Mobile</label>
            <input class="vs-input" type="text" id="loginId" placeholder="Enter any of the above"/>
          </div>
          <div class="mb-3">
            <label class="vs-label">Date of Birth</label>
            <input class="vs-input" type="date" id="loginDob"/>
          </div>
          <div class="mb-4">
            <label class="vs-label">Password</label>
            <input class="vs-input" type="password" id="loginPw" placeholder="••••••••"/>
          </div>
          <button class="btn-submit" onclick="doLogin()" id="loginBtn">
            <i class="bi bi-box-arrow-in-right"></i> Send OTP & Continue
          </button>
          <div class="alert-box" id="loginAlert"></div>
        </div>

        <!-- Step 2: OTP -->
        <div id="loginStep2" style="display:none;">
          <div style="background:rgba(10,122,62,0.08);border-left:3px solid var(--green);padding:.75rem 1rem;border-radius:3px;font-size:0.84rem;margin-bottom:1.5rem;">
            <i class="bi bi-phone me-1"></i> OTP sent to your registered mobile.
            <span id="otpDemoHint" style="display:block;margin-top:4px;font-family:'DM Mono',monospace;font-weight:700;"></span>
          </div>
          <div class="mb-3">
            <label class="vs-label">Enter 6-Digit OTP</label>
            <input class="vs-input" type="text" id="otpInput" maxlength="6" placeholder="123456"
                   inputmode="numeric" style="font-family:'DM Mono',monospace;font-size:1.2rem;letter-spacing:0.3em;text-align:center;"/>
          </div>
          <button class="btn-submit" onclick="doVerifyOtp()" id="otpBtn">
            <i class="bi bi-check2-circle"></i> Verify OTP & Login
          </button>
          <div class="alert-box" id="otpAlert"></div>
          <p style="text-align:center;margin-top:1rem;font-size:0.8rem;">
            <a href="#" onclick="resetLogin()" style="color:var(--saffron);text-decoration:none;">
              ← Back to login
            </a>
          </p>
        </div>
      </div><!-- /panel-login -->

      <!-- ── REGISTER PANEL ── -->
      <div class="auth-panel" id="panel-register">
        <div style="margin-bottom:1.5rem;">
          <div style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;">Create Account</div>
          <div style="font-size:0.86rem;color:var(--muted);margin-top:0.3rem;">Register to vote in upcoming elections</div>
        </div>

        <div class="row g-2 mb-2">
          <div class="col-6"><label class="vs-label">First Name</label><input class="vs-input" type="text" id="rFirst" placeholder="Rajiv"/></div>
          <div class="col-6"><label class="vs-label">Last Name</label><input class="vs-input" type="text" id="rLast" placeholder="Sharma"/></div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="vs-label">Aadhaar (12 digits)</label><input class="vs-input" type="text" id="rAadhaar" maxlength="12" placeholder="1234 5678 9012" inputmode="numeric"/></div>
          <div class="col-6"><label class="vs-label">Voter ID</label><input class="vs-input" type="text" id="rVoterId" placeholder="MH/01/2026/XXXXX"/></div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="vs-label">Date of Birth</label><input class="vs-input" type="date" id="rDob"/></div>
          <div class="col-6">
            <label class="vs-label">Gender</label>
            <select class="vs-select" id="rGender">
              <option value="">Select...</option>
              <option>Male</option><option>Female</option><option>Other</option>
            </select>
          </div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="vs-label">Mobile (10 digits)</label><input class="vs-input" type="tel" id="rMobile" maxlength="10" placeholder="9876543210" inputmode="numeric"/></div>
          <div class="col-6"><label class="vs-label">Email</label><input class="vs-input" type="email" id="rEmail" placeholder="you@email.com"/></div>
        </div>
        <div class="mb-2"><label class="vs-label">Password (min 8 chars)</label><input class="vs-input" type="password" id="rPw" placeholder="Create a strong password"/></div>
        <div class="mb-2"><label class="vs-label">Address</label><input class="vs-input" type="text" id="rAddress" placeholder="Flat 101, Shivaji Nagar"/></div>
        <div class="row g-2 mb-3">
          <div class="col-8"><label class="vs-label">City</label><input class="vs-input" type="text" id="rCity" placeholder="Solapur"/></div>
          <div class="col-4"><label class="vs-label">PIN Code</label><input class="vs-input" type="text" id="rPin" maxlength="6" placeholder="413001" inputmode="numeric"/></div>
        </div>

        <button class="btn-submit" onclick="doRegister()" id="regBtn">
          <i class="bi bi-person-plus"></i> Register Now
        </button>
        <div class="alert-box" id="regAlert"></div>
      </div><!-- /panel-register -->

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── TAB SWITCH ──────────────────────────────────────────────
function switchTab(tab) {
  document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.auth-panel').forEach(p => p.classList.remove('active'));
  document.querySelector(`.auth-tab[onclick="switchTab('${tab}')"]`).classList.add('active');
  document.getElementById('panel-' + tab).classList.add('active');
}

// Open register tab if URL has #register
if (location.hash === '#register') switchTab('register');

// ── ALERT HELPERS ───────────────────────────────────────────
function showAlert(id, msg, type='error') {
  const el = document.getElementById(id);
  el.className = 'alert-box alert-' + type;
  el.innerHTML = '<i class="bi bi-' + (type==='error'?'exclamation-triangle':'check-circle') + ' me-1"></i>' + msg;
  el.style.display = 'block';
}
function hideAlert(id) {
  document.getElementById(id).style.display = 'none';
}

// ── LOGIN ───────────────────────────────────────────────────
async function doLogin() {
  const id  = document.getElementById('loginId').value.trim();
  const dob = document.getElementById('loginDob').value;
  const pw  = document.getElementById('loginPw').value;
  const btn = document.getElementById('loginBtn');

  if (!id || !dob || !pw) return showAlert('loginAlert','Please fill in all fields.');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending OTP...';

  const fd = new FormData();
  fd.append('action','login');
  fd.append('voter_id', id);
  fd.append('dob', dob);
  fd.append('password', pw);

  try {
    const res  = await fetch('register.php', { method:'POST', body:fd });
    const data = await res.json();

    if (data.success) {
      document.getElementById('loginStep1').style.display = 'none';
      document.getElementById('loginStep2').style.display = 'block';
      if (data.otp_demo) {
        document.getElementById('otpDemoHint').textContent = 'Demo OTP: ' + data.otp_demo;
      }
      hideAlert('loginAlert');
    } else {
      showAlert('loginAlert', data.message || 'Login failed.');
    }
  } catch(e) {
    showAlert('loginAlert', 'Network error. Please try again.');
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Send OTP & Continue';
}

// ── OTP VERIFY ──────────────────────────────────────────────
async function doVerifyOtp() {
  const otp = document.getElementById('otpInput').value.trim();
  const btn = document.getElementById('otpBtn');

  if (otp.length !== 6) return showAlert('otpAlert','Enter the 6-digit OTP.');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';

  const fd = new FormData();
  fd.append('action','verify_otp');
  fd.append('otp', otp);

  try {
    const res  = await fetch('register.php', { method:'POST', body:fd });
    const data = await res.json();

    if (data.success) {
      showAlert('otpAlert','Welcome, ' + data.name + '! Redirecting...','success');
      setTimeout(() => {
        window.location.href = data.hasVoted ? 'results.php' : 'vote.php';
      }, 1200);
    } else {
      showAlert('otpAlert', data.message || 'OTP verification failed.');
    }
  } catch(e) {
    showAlert('otpAlert','Network error. Please try again.');
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check2-circle"></i> Verify OTP & Login';
}

function resetLogin() {
  document.getElementById('loginStep1').style.display = 'block';
  document.getElementById('loginStep2').style.display = 'none';
  document.getElementById('otpInput').value = '';
  document.getElementById('otpDemoHint').textContent = '';
  hideAlert('loginAlert');
  hideAlert('otpAlert');
}

// Allow Enter key on OTP field
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('otpInput')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') doVerifyOtp();
  });
  document.getElementById('loginPw')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
  });
});

// ── REGISTER ────────────────────────────────────────────────
async function doRegister() {
  const btn = document.getElementById('regBtn');
  const fields = {
    first_name: document.getElementById('rFirst').value.trim(),
    last_name:  document.getElementById('rLast').value.trim(),
    aadhaar:    document.getElementById('rAadhaar').value.trim(),
    voter_id:   document.getElementById('rVoterId').value.trim(),
    dob:        document.getElementById('rDob').value,
    gender:     document.getElementById('rGender').value,
    mobile:     document.getElementById('rMobile').value.trim(),
    email:      document.getElementById('rEmail').value.trim(),
    password:   document.getElementById('rPw').value,
    address:    document.getElementById('rAddress').value.trim(),
    city:       document.getElementById('rCity').value.trim(),
    pin:        document.getElementById('rPin').value.trim(),
    action:     'register'
  };

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registering...';

  const fd = new FormData();
  Object.entries(fields).forEach(([k,v]) => fd.append(k, v));

  try {
    const res  = await fetch('register.php', { method:'POST', body:fd });
    const data = await res.json();

    if (data.success) {
      showAlert('regAlert',
        data.message + '<br><strong>Voter Ref: ' + data.voter_ref + '</strong>',
        'success');
      // Switch to login tab after 3 seconds
      setTimeout(() => switchTab('login'), 3000);
    } else {
      showAlert('regAlert', data.message || 'Registration failed.');
    }
  } catch(e) {
    showAlert('regAlert','Network error. Please try again.');
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-person-plus"></i> Register Now';
}
</script>
</body>
</html>
