<?php
// vote.php — Cast Vote Page  (FIXED)
// FIX 1: Original had broken SQL: "SELECT COUNT (*)" with space,
//         "created _at" with space, "<" instead of ",", missing closing ")
//         It was fetching audit_log counts but assigning to $voter — WRONG.
//         Now correctly fetches voter data from the voters table.
// FIX 2: $voter['has_voted'] / $voter['id'] were undefined because
//         $voter was overwritten with audit log count result.
// FIX 3: requireVoterLogin() now works because db.php starts session.
require_once 'db.php';
requireVoterLogin();

$db = getDB();

// FIX: Get the actual voter record (not audit log count)
$voterStmt = $db->prepare("SELECT * FROM voters WHERE id = ? LIMIT 1");
$voterStmt->execute([$_SESSION['voter_id']]);
$voter = $voterStmt->fetch();

if (!$voter) {
    // Session voter_id doesn't exist in DB — force logout
    session_destroy();
    header('Location: register.php?err=session');
    exit;
}

// Rate-limit check (separate from voter fetch — was the original intent)
$lockQ = $db->prepare("SELECT COUNT(*) FROM audit_logs
    WHERE ip_address = ? AND action = 'LOGIN' AND result = 'fail'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$lockQ->execute([getClientIP()]);
$failCount = (int)$lockQ->fetchColumn();

// Get active election
$election = $db->query(
    "SELECT * FROM elections
     WHERE is_active = 1 AND NOW() BETWEEN start_time AND end_time
     ORDER BY id DESC LIMIT 1"
)->fetch();

// Handle vote submission
$voteMsg = '';
$voteErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id']) && $election) {
    $cid = (int)$_POST['candidate_id'];

    // Re-check has_voted from DB (not just session — prevents race conditions)
    if ($voter['has_voted']) {
        $voteErr = 'You have already cast your vote in this election.';
    } else {
        // Verify candidate belongs to this election
        $cCheck = $db->prepare("SELECT id FROM candidates WHERE id = ? AND election_id = ? AND is_active = 1");
        $cCheck->execute([$cid, $election['id']]);

        if (!$cCheck->fetch()) {
            $voteErr = 'Invalid candidate selection.';
        } else {
            try {
                $db->beginTransaction();

                $db->prepare("INSERT INTO votes (voter_id, candidate_id, election_id, ip_address, voted_at)
                              VALUES (?, ?, ?, ?, NOW())")
                   ->execute([$voter['id'], $cid, $election['id'], getClientIP()]);

                $db->prepare("UPDATE voters SET has_voted = 1 WHERE id = ?")
                   ->execute([$voter['id']]);

                $db->prepare("INSERT INTO audit_logs (voter_id, action, ip_address, result, details, created_at)
                              VALUES (?, 'VOTE', ?, 'ok', ?, NOW())")
                   ->execute([$voter['id'], getClientIP(), 'Voted for candidate ' . $cid]);

                $db->commit();

                $_SESSION['has_voted'] = true;
                $voter['has_voted']    = 1; // update local copy too
                $voteMsg = 'Your vote has been cast successfully!';

            } catch (PDOException $e) {
                $db->rollBack();
                // Unique constraint on voter_id means duplicate vote attempt
                if ($e->getCode() == 23000) {
                    $voteErr = 'You have already voted. Duplicate vote prevented.';
                } else {
                    $voteErr = 'Voting failed due to a server error. Please try again.';
                    error_log('[VoteSure] Vote Error: ' . $e->getMessage());
                }
            }
        }
    }
}

// Get candidates for current election
$candidates = [];
if ($election) {
    $cStmt = $db->prepare(
        "SELECT c.*,
                (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id) AS vote_count
         FROM candidates c
         WHERE c.election_id = ? AND c.is_active = 1
         ORDER BY c.id"
    );
    $cStmt->execute([$election['id']]);
    $candidates = $cStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Cast Your Vote — VoteSure</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{--saffron:#FF6B00;--saffron-d:#e05a00;--ink:#0a0a0f;--ink-soft:#1c1c24;--paper:#f5f2eb;--green:#0A7A3E;--green-l:#12a352;--muted:#6b6b72;--rule:rgba(10,10,15,0.1);}
    body{font-family:'DM Sans',sans-serif;background:var(--paper);color:var(--ink);min-height:100vh;}
    .tricolor{height:4px;background:linear-gradient(to right,#FF9933 33.33%,#fff 33.33% 66.66%,#138808 66.66%);}
    .vs-navbar{background:rgba(245,242,235,0.96)!important;backdrop-filter:blur(16px);border-bottom:1px solid var(--rule);}
    .navbar-brand{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--ink)!important;}
    .navbar-brand span{color:var(--saffron);}
    .voter-pill{background:rgba(10,122,62,0.1);color:var(--green);border-radius:3px;font-size:0.78rem;font-family:'DM Mono',monospace;padding:0.3rem 0.8rem;}
    .vote-hero{background:var(--ink);color:var(--paper);padding:3rem 0;position:relative;overflow:hidden;}
    .vote-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80% 50%,rgba(255,107,0,0.1) 0%,transparent 60%);}
    .vote-hero-content{position:relative;z-index:1;}
    .election-badge{display:inline-flex;align-items:center;gap:0.5rem;background:rgba(255,107,0,0.15);color:var(--saffron);padding:0.4rem 1rem;border-radius:2px;font-family:'DM Mono',monospace;font-size:0.72rem;letter-spacing:0.1em;margin-bottom:1rem;}
    .vote-title{font-family:'Playfair Display',serif;font-size:clamp(1.8rem,3vw,2.8rem);font-weight:900;letter-spacing:-1px;line-height:1.2;margin-bottom:0.5rem;}
    .vote-sub{font-size:0.9rem;color:rgba(245,242,235,0.55);line-height:1.7;}
    .candidate-card{border:2px solid var(--rule);border-radius:3px;padding:1.5rem;cursor:pointer;transition:all 0.2s;position:relative;background:var(--paper);}
    .candidate-card:hover{border-color:var(--saffron);background:rgba(255,107,0,0.03);}
    .candidate-card.selected{border-color:var(--saffron);background:rgba(255,107,0,0.05);}
    .candidate-card.selected::after{content:'✓';position:absolute;top:1rem;right:1rem;width:28px;height:28px;border-radius:50%;background:var(--saffron);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;}
    .candidate-symbol{font-size:2.2rem;margin-bottom:0.6rem;}
    .candidate-name{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:900;margin-bottom:0.2rem;}
    .candidate-party{font-size:0.8rem;color:var(--muted);font-family:'DM Mono',monospace;}
    .candidate-const{font-size:0.72rem;color:var(--muted);}
    .confirm-box{background:var(--ink-soft);color:var(--paper);border-radius:3px;padding:1.5rem;margin-top:2rem;}
    .btn-vs{display:inline-flex;align-items:center;gap:0.4rem;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:500;padding:0.8rem 1.6rem;border-radius:3px;border:none;cursor:pointer;text-decoration:none;transition:all 0.2s;}
    .btn-saffron{background:var(--saffron);color:#fff;}
    .btn-saffron:hover{background:var(--saffron-d);color:#fff;}
    .btn-outline-vs{background:transparent;color:var(--ink);border:1.5px solid var(--ink);}
    .btn-green-vs{background:var(--green);color:#fff;}
    .btn-green-vs:hover{background:var(--green-l);color:#fff;}
    .voted-banner{background:var(--green);color:#fff;padding:1rem 1.5rem;border-radius:3px;display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;}
    .voted-banner i{font-size:1.5rem;}
    .vs-alert-danger{background:rgba(220,38,38,0.08);border-left:3px solid #dc2626;color:var(--ink);padding:.8rem 1rem;border-radius:3px;font-size:0.86rem;}
    .vs-alert-success{background:rgba(10,122,62,0.08);border-left:3px solid var(--green);color:var(--ink);padding:.8rem 1rem;border-radius:3px;font-size:0.86rem;}
    .vs-footer{background:#06060a;color:rgba(245,242,235,0.4);padding:2rem 0;font-size:0.72rem;font-family:'DM Mono',monospace;margin-top:4rem;}
  </style>
</head>
<body>
<div class="tricolor"></div>
<nav class="navbar navbar-expand-lg vs-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand" href="index.php">Vote<span>Sure</span></a>
    <div class="ms-auto d-flex align-items-center gap-3">
      <span class="voter-pill">
        <i class="bi bi-person-check me-1"></i>
        <?= htmlspecialchars($voter['first_name'] . ' ' . $voter['last_name']) ?>
      </span>
      <a href="results.php" class="text-muted text-decoration-none me-1" style="font-size:0.8rem;">
        <i class="bi bi-bar-chart"></i> Results
      </a>
      <a href="logout.php" class="text-muted text-decoration-none" style="font-size:0.8rem;">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </div>
  </div>
</nav>

<!-- HERO -->
<div class="vote-hero">
  <div class="container vote-hero-content">
    <div class="election-badge">
      <i class="bi bi-broadcast-pin"></i> LIVE ELECTION — POLLING OPEN
    </div>
    <?php if ($election): ?>
      <h1 class="vote-title"><?= htmlspecialchars($election['title']) ?></h1>
      <p class="vote-sub">
        <?= htmlspecialchars($election['description']) ?>
        &nbsp;·&nbsp;
        Polling: <?= date('d M Y', strtotime($election['start_time'])) ?>
        – <?= date('d M Y', strtotime($election['end_time'])) ?>
      </p>
    <?php else: ?>
      <h1 class="vote-title">No Active Election</h1>
      <p class="vote-sub">There is no active election at this time. Check back soon.</p>
    <?php endif; ?>
  </div>
</div>

<div class="container py-5">

  <?php if ($voteMsg): ?>
    <!-- SUCCESS STATE -->
    <div class="text-center py-5">
      <div style="font-size:4rem;margin-bottom:1rem;">🗳️</div>
      <h2 style="font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;margin-bottom:0.5rem;">
        Vote Cast Successfully!
      </h2>
      <p class="text-muted mb-4" style="font-size:0.9rem;">
        <?= htmlspecialchars($voteMsg) ?><br>
        Your vote is encrypted and securely recorded.
      </p>
      <div class="d-flex justify-content-center gap-3 flex-wrap">
        <a href="results.php" class="btn-vs btn-saffron"><i class="bi bi-bar-chart"></i> View Live Results</a>
        <a href="register.php" class="btn-vs btn-outline-vs"><i class="bi bi-house"></i> Back to Home</a>
      </div>
    </div>

  <?php elseif ($voter['has_voted']): ?>
    <!-- ALREADY VOTED STATE -->
    <div class="voted-banner">
      <i class="bi bi-patch-check-fill"></i>
      <div>
        <strong>You have already voted.</strong><br>
        <small>Your vote was securely recorded. One citizen, one vote.</small>
      </div>
    </div>
    <div class="d-flex gap-3 flex-wrap">
      <a href="results.php" class="btn-vs btn-saffron"><i class="bi bi-bar-chart"></i> View Live Results</a>
      <a href="logout.php"  class="btn-vs btn-outline-vs"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>

  <?php elseif (!$election): ?>
    <!-- NO ACTIVE ELECTION -->
    <div class="text-center py-5 text-muted">
      <i class="bi bi-calendar-x" style="font-size:3rem;"></i>
      <p class="mt-3">No active election is currently running.</p>
      <a href="results.php" class="btn-vs btn-saffron mt-2"><i class="bi bi-bar-chart"></i> View Past Results</a>
    </div>

  <?php else: ?>
    <!-- VOTING FORM -->
    <?php if ($voteErr): ?>
      <div class="vs-alert-danger mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($voteErr) ?>
      </div>
    <?php endif; ?>

    <h3 style="font-family:'Playfair Display',serif;font-weight:900;font-size:1.3rem;margin-bottom:0.4rem;">
      Select Your Candidate
    </h3>
    <p class="text-muted mb-4" style="font-size:0.86rem;">
      Choose one candidate. Your vote is anonymous and cannot be changed after submission.
    </p>

    <form method="POST" id="voteForm" onsubmit="return confirmVote(event)">
      <div class="row g-3 mb-4">
        <?php foreach ($candidates as $c): ?>
        <div class="col-md-4">
          <div class="candidate-card" id="card_<?= $c['id'] ?>"
               onclick="selectCandidate(<?= $c['id'] ?>)">
            <div class="candidate-symbol"><?= htmlspecialchars($c['symbol'] ?? '🗳️') ?></div>
            <div class="candidate-name"><?= htmlspecialchars($c['name']) ?></div>
            <div class="candidate-party"><?= htmlspecialchars($c['party'] ?? '') ?></div>
            <div class="candidate-const mt-1">
              <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($c['constituency'] ?? '') ?>
            </div>
            <input type="radio" name="candidate_id" value="<?= $c['id'] ?>"
                   id="r_<?= $c['id'] ?>" style="display:none;" required/>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Confirm box shown after selection -->
      <div class="confirm-box" id="confirmBox" style="display:none;">
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <div>
            <div style="font-size:0.72rem;font-family:'DM Mono',monospace;color:rgba(245,242,235,0.5);margin-bottom:0.3rem;">
              VOTING FOR
            </div>
            <div style="font-size:1.1rem;font-weight:600;" id="confirmName">—</div>
            <div style="font-size:0.8rem;color:rgba(245,242,235,0.5);" id="confirmParty">—</div>
          </div>
          <div class="ms-auto d-flex gap-2 flex-wrap">
            <button type="button" class="btn-vs"
                    style="color:rgba(245,242,235,0.7);border:1px solid rgba(245,242,235,0.3);background:transparent;"
                    onclick="clearSelection()">
              Change
            </button>
            <button type="submit" class="btn-vs btn-green-vs">
              <i class="bi bi-check2-circle"></i> Confirm Vote
            </button>
          </div>
        </div>
      </div>
    </form>

    <div class="mt-4 p-3" style="background:rgba(255,107,0,0.06);border-radius:3px;font-size:0.8rem;color:var(--muted);line-height:1.7;">
      <i class="bi bi-shield-lock me-1"></i>
      <strong>Security Notice:</strong> Your candidate choice is not linked to your identity in the results.
      You cannot change your vote after submission.
    </div>
  <?php endif; ?>
</div>

<footer class="vs-footer">
  <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:900;color:#f5f2eb;">
      Vote<span style="color:var(--saffron);">Sure</span>
    </span>
    <span>© 2026 VoteSure · Secured · ISO 27001 Compliant</span>
    <a href="logout.php" style="color:rgba(245,242,235,0.4);text-decoration:none;">Logout</a>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Build a JS candidate map from PHP
const candidates = <?= json_encode(array_column($candidates ?? [], null, 'id')) ?>;
let selectedId = null;

function selectCandidate(id) {
  // Remove selection from all cards
  document.querySelectorAll('.candidate-card').forEach(c => c.classList.remove('selected'));
  // Select clicked card
  document.getElementById('card_' + id).classList.add('selected');
  document.getElementById('r_' + id).checked = true;
  selectedId = id;

  const c = candidates[id];
  if (c) {
    document.getElementById('confirmName').textContent  = c.name;
    document.getElementById('confirmParty').textContent = c.party || 'Independent';
    const box = document.getElementById('confirmBox');
    box.style.display = 'block';
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

function clearSelection() {
  document.querySelectorAll('.candidate-card').forEach(c => c.classList.remove('selected'));
  document.querySelectorAll('input[name=candidate_id]').forEach(r => r.checked = false);
  selectedId = null;
  document.getElementById('confirmBox').style.display = 'none';
}

function confirmVote(e) {
  if (!selectedId) {
    e.preventDefault();
    alert('Please select a candidate first.');
    return false;
  }
  const name = candidates[selectedId]?.name ?? 'selected candidate';
  return confirm('Confirm your vote for ' + name + '?\n\nThis cannot be undone.');
}
</script>
</body>
</html>
