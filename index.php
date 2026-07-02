<?php
// index.php — VoteSure Landing / Home Page
require_once 'db.php';

$db = getDB();
$totalVoters   = (int)$db->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$totalVotes    = (int)$db->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$activeElections = $db->query("SELECT * FROM elections WHERE is_active=1 AND NOW() BETWEEN start_time AND end_time ORDER BY id DESC")->fetchAll();
$upcomingElections = $db->query("SELECT * FROM elections WHERE is_active=1 AND start_time > NOW() ORDER BY start_time ASC LIMIT 3")->fetchAll();

// Redirect if already logged in
if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: vote.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>VoteSure — Secure Digital Elections</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{--saffron:#FF6B00;--saffron-d:#e05a00;--ink:#0a0a0f;--paper:#f5f2eb;--green:#0A7A3E;--muted:#6b6b72;--rule:rgba(10,10,15,0.1);}
    body{font-family:'DM Sans',sans-serif;background:var(--paper);color:var(--ink);}
    .tricolor{height:4px;background:linear-gradient(to right,#FF9933 33.33%,#fff 33.33% 66.66%,#138808 66.66%);position:sticky;top:0;z-index:1031;}
    .vs-navbar{background:rgba(245,242,235,0.95)!important;backdrop-filter:blur(16px);border-bottom:1px solid var(--rule);}
    .navbar-brand{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--ink)!important;}
    .navbar-brand span{color:var(--saffron);}
    .nav-link{font-size:0.78rem;font-weight:500;letter-spacing:0.07em;text-transform:uppercase;color:var(--muted)!important;}
    .nav-link:hover{color:var(--ink)!important;}
    /* HERO */
    .hero{background:var(--ink);color:var(--paper);min-height:90vh;display:flex;align-items:center;position:relative;overflow:hidden;}
    .hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80% 50%,rgba(255,107,0,0.08) 0%,transparent 60%),radial-gradient(ellipse at 20% 80%,rgba(10,122,62,0.07) 0%,transparent 50%);}
    .hero-content{position:relative;z-index:1;}
    .hero-tag{display:inline-flex;align-items:center;gap:0.5rem;background:rgba(255,107,0,0.15);color:var(--saffron);padding:0.4rem 1rem;border-radius:2px;font-family:'DM Mono',monospace;font-size:0.72rem;letter-spacing:0.1em;margin-bottom:1.5rem;}
    .hero-title{font-family:'Playfair Display',serif;font-size:clamp(2.5rem,6vw,5rem);font-weight:900;letter-spacing:-2px;line-height:1.05;margin-bottom:1.2rem;}
    .hero-title em{font-style:italic;color:var(--saffron);}
    .hero-sub{font-size:1rem;color:rgba(245,242,235,0.55);line-height:1.8;max-width:46ch;margin-bottom:2rem;}
    .btn-hero{display:inline-flex;align-items:center;gap:0.5rem;padding:0.85rem 1.8rem;border-radius:3px;font-family:'DM Sans',sans-serif;font-size:0.92rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;border:none;}
    .btn-saffron{background:var(--saffron);color:#fff;}.btn-saffron:hover{background:var(--saffron-d);color:#fff;transform:translateY(-1px);}
    .btn-outline-paper{background:transparent;color:rgba(245,242,235,0.8);border:1.5px solid rgba(245,242,235,0.25);}.btn-outline-paper:hover{border-color:var(--paper);color:var(--paper);}
    /* STATS */
    .stats-band{background:var(--ink);border-top:1px solid rgba(245,242,235,0.08);padding:2rem 0;}
    .stat-pill{text-align:center;}
    .stat-pill-num{font-family:'Playfair Display',serif;font-size:2.8rem;font-weight:900;color:var(--saffron);}
    .stat-pill-label{font-family:'DM Mono',monospace;font-size:0.65rem;letter-spacing:0.12em;text-transform:uppercase;color:rgba(245,242,235,0.4);margin-top:0.2rem;}
    /* FEATURES */
    .features-section{padding:5rem 0;}
    .feat-card{border:1px solid var(--rule);border-radius:4px;padding:2rem;height:100%;transition:border-color 0.2s,box-shadow 0.2s;}
    .feat-card:hover{border-color:var(--saffron);box-shadow:0 4px 20px rgba(255,107,0,0.08);}
    .feat-icon{font-size:2rem;margin-bottom:1rem;}
    .feat-title{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:900;margin-bottom:0.6rem;}
    .feat-desc{font-size:0.86rem;color:var(--muted);line-height:1.7;}
    /* HOW IT WORKS */
    .how-section{background:var(--ink);color:var(--paper);padding:5rem 0;}
    .step-num{font-family:'Playfair Display',serif;font-size:4rem;font-weight:900;color:rgba(255,107,0,0.15);line-height:1;}
    .step-title{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:900;}
    .step-desc{font-size:0.86rem;color:rgba(245,242,235,0.5);line-height:1.7;margin-top:0.5rem;}
    /* ELECTION CARDS */
    .election-card{border:1px solid var(--rule);border-radius:4px;padding:1.5rem;margin-bottom:1rem;}
    .live-dot{width:8px;height:8px;background:#22c55e;border-radius:50%;animation:pulse 2s infinite;display:inline-block;margin-right:6px;}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
    /* CTA */
    .cta-section{padding:5rem 0;text-align:center;}
    .cta-title{font-family:'Playfair Display',serif;font-size:clamp(1.8rem,3.5vw,3rem);font-weight:900;margin-bottom:1rem;}
    /* FOOTER */
    .vs-footer{background:#06060a;color:rgba(245,242,235,0.35);padding:2.5rem 0;font-size:0.72rem;font-family:'DM Mono',monospace;}
  </style>
</head>
<body>
<div class="tricolor"></div>
<nav class="navbar navbar-expand-lg vs-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand" href="index.php">Vote<span>Sure</span></a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-2">
        <li><a class="nav-link" href="results.php">Live Results</a></li>
        <li><a class="nav-link" href="admin_login.php">Admin</a></li>
        <li><a class="nav-link" href="register.php">Login</a></li>
        <li>
          <a class="nav-link" href="register.php#register"
             style="background:var(--saffron);color:#fff!important;border-radius:3px;padding:.4rem 1rem;">
            Register to Vote
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="container hero-content">
    <div class="row align-items-center">
      <div class="col-lg-7">
        <div class="hero-tag"><i class="bi bi-shield-check"></i> SECURE DIGITAL ELECTIONS</div>
        <h1 class="hero-title">Your Vote,<br>Your <em>Voice.</em></h1>
        <p class="hero-sub">VoteSure delivers transparent, secure, and efficient digital elections. Verified identities, encrypted ballots, and real-time results — all in one platform.</p>
        <div class="d-flex gap-3 flex-wrap">
          <a href="register.php" class="btn-hero btn-saffron"><i class="bi bi-person-plus"></i> Register to Vote</a>
          <a href="results.php" class="btn-hero btn-outline-paper"><i class="bi bi-bar-chart"></i> View Live Results</a>
        </div>
      </div>
      <div class="col-lg-5 mt-5 mt-lg-0">
        <?php if (!empty($activeElections)): ?>
        <div style="border:1px solid rgba(245,242,235,0.1);border-radius:4px;padding:1.5rem;background:rgba(245,242,235,0.04);">
          <div style="font-family:'DM Mono',monospace;font-size:0.68rem;letter-spacing:0.12em;color:rgba(245,242,235,0.4);margin-bottom:1rem;">ELECTIONS LIVE NOW</div>
          <?php foreach ($activeElections as $el): ?>
          <div class="mb-3 p-3" style="background:rgba(255,107,0,0.08);border-radius:3px;border-left:3px solid var(--saffron);">
            <div style="font-size:0.72rem;margin-bottom:0.5rem;color:rgba(245,242,235,0.5);">
              <span class="live-dot"></span>LIVE · Ends <?= date('d M Y', strtotime($el['end_time'])) ?>
            </div>
            <div style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:900;"><?= htmlspecialchars($el['title']) ?></div>
            <?php if($el['description']): ?>
              <div style="font-size:0.82rem;color:rgba(245,242,235,0.5);margin-top:.4rem;"><?= htmlspecialchars(substr($el['description'],0,80)) ?></div>
            <?php endif; ?>
            <a href="register.php" class="btn-hero btn-saffron mt-3" style="padding:.55rem 1.2rem;font-size:.82rem;">
              <i class="bi bi-check2-circle"></i> Vote Now
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="border:1px solid rgba(245,242,235,0.1);border-radius:4px;padding:2rem;background:rgba(245,242,235,0.03);text-align:center;">
          <i class="bi bi-calendar-event" style="font-size:3rem;color:rgba(245,242,235,0.2);display:block;margin-bottom:1rem;"></i>
          <div style="font-size:0.86rem;color:rgba(245,242,235,0.4);">No elections live right now.</div>
          <?php if (!empty($upcomingElections)): ?>
          <div style="margin-top:1rem;font-size:0.82rem;color:rgba(255,107,0,0.7);">
            Next: <strong><?= htmlspecialchars($upcomingElections[0]['title']) ?></strong><br>
            Starts <?= date('d M Y', strtotime($upcomingElections[0]['start_time'])) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- STATS BAND -->
<div class="stats-band">
  <div class="container">
    <div class="row g-4 justify-content-center">
      <div class="col-6 col-md-3"><div class="stat-pill"><div class="stat-pill-num"><?= number_format($totalVoters) ?></div><div class="stat-pill-label">Registered Voters</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-pill"><div class="stat-pill-num"><?= number_format($totalVotes) ?></div><div class="stat-pill-label">Votes Cast</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-pill"><div class="stat-pill-num"><?= count($activeElections) ?></div><div class="stat-pill-label">Live Elections</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-pill"><div class="stat-pill-num">100%</div><div class="stat-pill-label">Encrypted & Secure</div></div></div>
    </div>
  </div>
</div>

<!-- FEATURES -->
<section class="features-section">
  <div class="container">
    <div class="text-center mb-5">
      <div style="font-family:'DM Mono',monospace;font-size:0.72rem;letter-spacing:0.15em;color:var(--saffron);margin-bottom:0.8rem;">PLATFORM FEATURES</div>
      <h2 style="font-family:'Playfair Display',serif;font-size:clamp(1.8rem,3vw,2.5rem);font-weight:900;">Built for Trust &amp; Transparency</h2>
    </div>
    <div class="row g-4">
      <div class="col-md-4"><div class="feat-card"><div class="feat-icon">🔐</div><div class="feat-title">Secure Authentication</div><div class="feat-desc">Multi-factor login with Aadhaar verification and mobile OTP. Each voter's identity is cryptographically verified before access.</div></div></div>
      <div class="col-md-4"><div class="feat-card"><div class="feat-icon">🗳️</div><div class="feat-title">One Person, One Vote</div><div class="feat-desc">Database-level constraints guarantee every voter can cast exactly one ballot per election. No duplicates, no exceptions.</div></div></div>
      <div class="col-md-4"><div class="feat-card"><div class="feat-icon">📊</div><div class="feat-title">Real-Time Results</div><div class="feat-desc">Watch live vote tallies update instantly. Transparent bar charts, percentage breakdowns, and winner detection — all public.</div></div></div>
      <div class="col-md-4"><div class="feat-card"><div class="feat-icon">🛡️</div><div class="feat-title">Complete Audit Trail</div><div class="feat-desc">Every login, vote attempt, and admin action is logged with timestamp and IP. Full accountability from end to end.</div></div></div>
      <div class="col-md-4"><div class="feat-card"><div class="feat-icon">⚙️</div><div class="feat-title">Admin Control Panel</div><div class="feat-desc">Manage elections, candidates, and voters from a comprehensive dashboard. Add, activate, deactivate, and monitor everything.</div></div></div>
      <div class="col-md-4"><div class="feat-card"><div class="feat-icon">📱</div><div class="feat-title">Mobile Friendly</div><div class="feat-desc">Fully responsive design works on any device. Voters can register and cast their ballot from a phone, tablet, or desktop.</div></div></div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section">
  <div class="container">
    <div class="text-center mb-5">
      <div style="font-family:'DM Mono',monospace;font-size:0.72rem;letter-spacing:0.15em;color:var(--saffron);margin-bottom:0.8rem;">HOW IT WORKS</div>
      <h2 style="font-family:'Playfair Display',serif;font-size:clamp(1.8rem,3vw,2.5rem);font-weight:900;">Three Steps to Your Vote</h2>
    </div>
    <div class="row g-5">
      <div class="col-md-4">
        <div class="step-num">01</div>
        <div class="step-title">Register Your Identity</div>
        <div class="step-desc">Enter your Aadhaar, Voter ID, date of birth, and contact details. Your identity is verified against our secure records.</div>
      </div>
      <div class="col-md-4">
        <div class="step-num">02</div>
        <div class="step-title">Login &amp; Verify OTP</div>
        <div class="step-desc">Log in with your credentials and confirm via a one-time password sent to your registered mobile number.</div>
      </div>
      <div class="col-md-4">
        <div class="step-num">03</div>
        <div class="step-title">Cast Your Vote</div>
        <div class="step-desc">Select your candidate, confirm your choice, and your encrypted vote is permanently recorded. Results update instantly.</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="container">
    <h2 class="cta-title">Ready to make your voice heard?</h2>
    <p style="color:var(--muted);margin-bottom:2rem;font-size:0.95rem;">Join <?= number_format($totalVoters) ?> registered voters on VoteSure.</p>
    <div class="d-flex justify-content-center gap-3 flex-wrap">
      <a href="register.php" class="btn-hero btn-saffron"><i class="bi bi-person-plus"></i> Register Now — It's Free</a>
      <a href="results.php" class="btn-hero" style="border:1.5px solid var(--rule);color:var(--ink);background:transparent;"><i class="bi bi-bar-chart"></i> View Results</a>
    </div>
  </div>
</section>

<footer class="vs-footer">
  <div class="container">
    <div class="row align-items-center g-3">
      <div class="col-md-4">
        <span style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:900;color:#f5f2eb;">Vote<span style="color:var(--saffron);">Sure</span></span>
      </div>
      <div class="col-md-4 text-md-center">
        © 2026 VoteSure · All rights reserved
      </div>
      <div class="col-md-4 text-md-end d-flex justify-content-md-end gap-3">
        <a href="register.php" style="color:inherit;text-decoration:none;">Voter Login</a>
        <a href="results.php" style="color:inherit;text-decoration:none;">Results</a>
        <a href="admin_login.php" style="color:inherit;text-decoration:none;">Admin</a>
      </div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
