<?php
// results.php — Public Live Results Page
require_once 'db.php';

$db = getDB();
$results = $db->query("SELECT c.name,c.party,c.symbol,c.constituency,e.title as election_title,e.id as eid,e.start_time,e.end_time,e.is_active,COUNT(v.id) as votes
    FROM candidates c LEFT JOIN votes v ON v.candidate_id=c.id LEFT JOIN elections e ON c.election_id=e.id
    GROUP BY c.id ORDER BY e.id,votes DESC")->fetchAll();

$byElection = [];
foreach($results as $r) $byElection[$r['eid']][] = $r;

$totalVoters = (int)$db->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$totalVotes  = (int)$db->query("SELECT COUNT(*) FROM votes")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Live Results — VoteSure 2026</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{--saffron:#FF6B00;--saffron-d:#e05a00;--ink:#0a0a0f;--ink-soft:#1c1c24;--paper:#f5f2eb;--green:#0A7A3E;--green-l:#12a352;--muted:#6b6b72;--rule:rgba(10,10,15,0.1);}
    body{font-family:'DM Sans',sans-serif;background:var(--paper);color:var(--ink);}
    .tricolor{height:4px;background:linear-gradient(to right,#FF9933 33.33%,#fff 33.33% 66.66%,#138808 66.66%);}
    .vs-navbar{background:rgba(245,242,235,0.95)!important;backdrop-filter:blur(16px);border-bottom:1px solid var(--rule);}
    .navbar-brand{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--ink)!important;}
    .navbar-brand span{color:var(--saffron);}
    .nav-link{font-size:0.78rem;font-weight:500;letter-spacing:0.07em;text-transform:uppercase;color:var(--muted)!important;}
    .nav-link:hover,.nav-link.active{color:var(--ink)!important;}
    .result-hero{background:var(--ink);color:var(--paper);padding:3rem 0;position:relative;overflow:hidden;}
    .result-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80% 50%,rgba(255,107,0,0.1) 0%,transparent 60%);}
    .stat-pill{background:rgba(245,242,235,0.08);border:1px solid rgba(245,242,235,0.12);padding:.5rem 1.2rem;border-radius:3px;font-family:'DM Mono',monospace;font-size:0.78rem;color:rgba(245,242,235,0.7);}
    .stat-pill strong{color:var(--paper);display:block;font-size:1.2rem;font-family:'Playfair Display',serif;font-weight:900;}
    .live-dot{width:8px;height:8px;background:#22c55e;border-radius:50%;animation:pulse 2s infinite;display:inline-block;}
    @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.5;transform:scale(1.3)}}
    .result-card{background:var(--paper);border:1px solid var(--rule);border-radius:4px;overflow:hidden;margin-bottom:2rem;}
    .result-card-head{background:var(--ink-soft);color:var(--paper);padding:1.2rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
    .result-card-title{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:900;}
    .candidate-result{padding:1.2rem 1.5rem;border-bottom:1px solid var(--rule);}
    .candidate-result:last-child{border-bottom:none;}
    .candidate-result.winner{background:rgba(10,122,62,0.04);}
    .cand-name{font-size:0.95rem;font-weight:600;}
    .cand-party{font-size:0.78rem;color:var(--muted);font-family:'DM Mono',monospace;}
    .vote-bar{height:8px;background:var(--rule);border-radius:4px;overflow:hidden;margin-top:6px;}
    .vote-bar-fill{height:100%;border-radius:4px;transition:width 1s ease;}
    .winner-crown{color:#f59e0b;font-size:1rem;}
    .vs-footer{background:#06060a;color:rgba(245,242,235,0.4);padding:2rem 0;font-size:0.72rem;font-family:'DM Mono',monospace;margin-top:4rem;}
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
      <ul class="navbar-nav ms-auto gap-2">
        <li><a class="nav-link" href="index.php">Home</a></li>
        <li><a class="nav-link" href="register.php">Register / Login</a></li>
        <li><a class="nav-link active" href="results.php">Live Results</a></li>
        <?php if(empty($_SESSION['logged_in'])): ?>
        <li><a class="nav-link" href="register.php" style="background:var(--saffron);color:#fff!important;border-radius:3px;padding:.4rem 1rem;">Vote Now</a></li>
        <?php else: ?>
        <li><a class="nav-link" href="vote.php" style="background:var(--saffron);color:#fff!important;border-radius:3px;padding:.4rem 1rem;">Voting Booth</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="result-hero">
  <div class="container" style="position:relative;z-index:1;">
    <div class="d-flex align-items-center gap-2 mb-3">
      <span class="live-dot"></span>
      <span style="font-family:'DM Mono',monospace;font-size:0.72rem;letter-spacing:0.15em;color:rgba(245,242,235,0.5);">LIVE RESULTS · UPDATING IN REAL TIME</span>
    </div>
    <h1 style="font-family:'Playfair Display',serif;font-size:clamp(1.8rem,3vw,2.8rem);font-weight:900;letter-spacing:-1px;margin-bottom:1rem;">Election Results Dashboard</h1>
    <div class="d-flex flex-wrap gap-3">
      <div class="stat-pill"><strong><?= number_format($totalVotes) ?></strong> Votes Cast</div>
      <div class="stat-pill"><strong><?= number_format($totalVoters) ?></strong> Registered Voters</div>
      <div class="stat-pill"><strong><?= $totalVoters>0?round($totalVotes/$totalVoters*100,1):0 ?>%</strong> Turnout</div>
    </div>
  </div>
</div>

<div class="container py-5">
  <?php if(empty($byElection)): ?>
    <div class="text-center py-5 text-muted"><i class="bi bi-bar-chart" style="font-size:3rem;"></i><p class="mt-3">No election results to display yet.</p></div>
  <?php else: ?>
  <?php foreach($byElection as $eid => $cands):
    $total   = array_sum(array_column($cands,'votes'));
    $etitle  = $cands[0]['election_title'];
    $isLive  = $cands[0]['is_active'];
    $winner  = $cands[0];
  ?>
  <div class="result-card">
    <div class="result-card-head">
      <div>
        <div class="result-card-title"><?= htmlspecialchars($etitle) ?></div>
        <div style="font-size:0.72rem;color:rgba(245,242,235,0.4);font-family:'DM Mono',monospace;margin-top:0.2rem;">
          <?= date('d M Y',strtotime($cands[0]['start_time'])) ?> – <?= date('d M Y',strtotime($cands[0]['end_time'])) ?>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <?= $isLive?'<span style="background:rgba(34,197,94,0.15);color:#22c55e;padding:.25rem .7rem;border-radius:2px;font-family:\'DM Mono\',monospace;font-size:0.65rem;letter-spacing:0.1em;"><span class=\'live-dot\' style=\'margin-right:5px;\'></span>LIVE</span>':'<span style="background:rgba(245,242,235,0.1);color:rgba(245,242,235,0.4);padding:.25rem .7rem;border-radius:2px;font-family:\'DM Mono\',monospace;font-size:0.65rem;">CLOSED</span>' ?>
        <span style="font-family:'DM Mono',monospace;font-size:0.78rem;color:rgba(245,242,235,0.5);"><?= $total ?> votes</span>
      </div>
    </div>
    <?php foreach($cands as $i=>$c): $pct=$total>0?round($c['votes']/$total*100,1):0; ?>
    <div class="candidate-result <?= $i===0&&$total>0?'winner':'' ?>">
      <div class="d-flex align-items-start justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
          <div style="font-size:2rem;"><?= htmlspecialchars($c['symbol']??'🗳️') ?></div>
          <div>
            <div class="cand-name">
              <?= $i===0&&$total>0?'<span class="winner-crown">👑</span> ':'' ?>
              <?= htmlspecialchars($c['name']) ?>
            </div>
            <div class="cand-party"><?= htmlspecialchars($c['party']) ?></div>
            <div style="font-size:0.72rem;color:var(--muted);margin-top:0.2rem;"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($c['constituency']) ?></div>
          </div>
        </div>
        <div class="text-end flex-shrink-0">
          <div style="font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;line-height:1;"><?= $c['votes'] ?></div>
          <div style="font-family:'DM Mono',monospace;font-size:0.8rem;color:var(--muted);"><?= $pct ?>%</div>
        </div>
      </div>
      <div class="vote-bar mt-2">
        <div class="vote-bar-fill" style="width:<?= $pct ?>%;background:<?= $i===0?'var(--green)':'var(--saffron)' ?>;"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<footer class="vs-footer">
  <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:900;color:#f5f2eb;">Vote<span style="color:var(--saffron);">Sure</span></span>
    <span>© 2026 VoteSure · Results refresh every 60s · ECI Compliant</span>
    <a href="admin_login.php" style="color:rgba(245,242,235,0.3);text-decoration:none;font-size:0.68rem;">Admin</a>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh every 60 seconds
setTimeout(()=>location.reload(), 60000);
</script>
</body>
</html>
