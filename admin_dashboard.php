<?php
// admin_dashboard.php (FIXED)
// FIX 1: Logout moved to TOP (was at line 698 — after HTML output, redirect never fired)
// FIX 2: Vote counts use prepared statements (was raw SQL injection risk)
// FIX 3: Reset votes only resets voters who voted in THAT election
// FIX 4: Added toggle candidate, colored action badges, live election status
require_once 'db.php';

if (isset($_GET['logout'])) { session_unset(); session_destroy(); header('Location: admin_login.php'); exit; }
requireAdminLogin();

$db = getDB(); $page = $_GET['page'] ?? 'dashboard';
$success = $_GET['success'] ?? ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'add_election') {
        $title=$_POST['title']??''; $desc=$_POST['description']??''; $start=$_POST['start_time']??''; $end=$_POST['end_time']??'';
        if(!$title||!$start||!$end){$err='Title, start and end time required.';}
        elseif($end<=$start){$err='End time must be after start time.';}
        else{$db->prepare("INSERT INTO elections(title,description,start_time,end_time,is_active,created_at)VALUES(?,?,?,?,1,NOW())")->execute([$title,$desc,$start,$end]);header('Location: admin_dashboard.php?page=elections&success=Election+created');exit;}
    }
    if ($act === 'toggle_election') { $id=(int)$_POST['id']; $db->prepare("UPDATE elections SET is_active=1-is_active WHERE id=?")->execute([$id]); header('Location: admin_dashboard.php?page=elections&success=Election+updated');exit; }
    if ($act === 'delete_election') { $id=(int)$_POST['id']; $db->prepare("DELETE FROM votes WHERE election_id=?")->execute([$id]); $db->prepare("DELETE FROM candidates WHERE election_id=?")->execute([$id]); $db->prepare("DELETE FROM elections WHERE id=?")->execute([$id]); header('Location: admin_dashboard.php?page=elections&success=Election+deleted');exit; }
    if ($act === 'add_candidate') {
        $eid=(int)$_POST['election_id']; $name=trim($_POST['name']??''); $party=trim($_POST['party']??''); $sym=trim($_POST['symbol']??'🗳️'); $const=trim($_POST['constituency']??'');
        if(!$eid||!$name){$err='Election and name required.';}
        else{$db->prepare("INSERT INTO candidates(election_id,name,party,symbol,constituency,is_active)VALUES(?,?,?,?,?,1)")->execute([$eid,$name,$party,$sym,$const]);header('Location: admin_dashboard.php?page=candidates&success=Candidate+added');exit;}
    }
    if ($act === 'edit_candidate') { $id=(int)$_POST['id']; $db->prepare("UPDATE candidates SET name=?,party=?,symbol=?,constituency=? WHERE id=?")->execute([trim($_POST['name']??''),trim($_POST['party']??''),trim($_POST['symbol']??''),trim($_POST['constituency']??''),$id]); header('Location: admin_dashboard.php?page=candidates&success=Candidate+updated');exit; }
    if ($act === 'toggle_candidate') { $id=(int)$_POST['id']; $db->prepare("UPDATE candidates SET is_active=1-is_active WHERE id=?")->execute([$id]); header('Location: admin_dashboard.php?page=candidates&success=Candidate+updated');exit; }
    if ($act === 'delete_candidate') { $id=(int)$_POST['id']; $db->prepare("DELETE FROM candidates WHERE id=?")->execute([$id]); header('Location: admin_dashboard.php?page=candidates&success=Candidate+deleted');exit; }
    if ($act === 'voter_status') { $id=(int)$_POST['id']; $st=$_POST['status']??'pending'; if(!in_array($st,['pending','verified','blocked']))$st='pending'; $db->prepare("UPDATE voters SET status=? WHERE id=?")->execute([$st,$id]); header('Location: admin_dashboard.php?page=voters&success=Voter+updated');exit; }
    if ($act === 'reset_votes') {
        $eid=(int)$_POST['election_id'];
        $db->prepare("UPDATE voters v INNER JOIN votes vt ON vt.voter_id=v.id SET v.has_voted=0 WHERE vt.election_id=?")->execute([$eid]);
        $db->prepare("DELETE FROM votes WHERE election_id=?")->execute([$eid]);
        header('Location: admin_dashboard.php?page=results&success=Votes+reset');exit;
    }
}

$stats=[
    'total_voters'=>(int)$db->query("SELECT COUNT(*) FROM voters")->fetchColumn(),
    'verified_voters'=>(int)$db->query("SELECT COUNT(*) FROM voters WHERE status='verified'")->fetchColumn(),
    'pending_voters'=>(int)$db->query("SELECT COUNT(*) FROM voters WHERE status='pending'")->fetchColumn(),
    'blocked_voters'=>(int)$db->query("SELECT COUNT(*) FROM voters WHERE status='blocked'")->fetchColumn(),
    'votes_cast'=>(int)$db->query("SELECT COUNT(*) FROM votes")->fetchColumn(),
    'active_elections'=>(int)$db->query("SELECT COUNT(*) FROM elections WHERE is_active=1")->fetchColumn(),
    'total_elections'=>(int)$db->query("SELECT COUNT(*) FROM elections")->fetchColumn(),
];
$elections=$db->query("SELECT e.*,(SELECT COUNT(*) FROM votes v WHERE v.election_id=e.id) as vote_count,(SELECT COUNT(*) FROM candidates c WHERE c.election_id=e.id) as cand_count FROM elections e ORDER BY e.id DESC")->fetchAll();
$candidates=$db->query("SELECT c.*,e.title AS election_title,(SELECT COUNT(*) FROM votes v WHERE v.candidate_id=c.id) AS vote_count FROM candidates c LEFT JOIN elections e ON c.election_id=e.id ORDER BY c.election_id,c.id")->fetchAll();
$voters=$db->query("SELECT * FROM voters ORDER BY id DESC LIMIT 200")->fetchAll();
$audit_logs=$db->query("SELECT al.*,CONCAT(IFNULL(v.first_name,''),' ',IFNULL(v.last_name,'')) AS voter_name FROM audit_logs al LEFT JOIN voters v ON al.voter_id=v.id ORDER BY al.id DESC LIMIT 150")->fetchAll();
$results=$db->query("SELECT c.id,c.name,c.party,c.symbol,c.constituency,e.title AS election_title,e.id AS eid,e.is_active AS election_active,(SELECT COUNT(*) FROM votes v WHERE v.candidate_id=c.id) AS votes FROM candidates c LEFT JOIN elections e ON c.election_id=e.id ORDER BY e.id,votes DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Admin Dashboard — VoteSure</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<style>
:root{--saffron:#FF6B00;--saffron-d:#e05a00;--ink:#0a0a0f;--ink-soft:#1c1c24;--paper:#f5f2eb;--paper-dim:#edeae2;--green:#0A7A3E;--green-l:#12a352;--muted:#6b6b72;--rule:rgba(10,10,15,0.1);--sidebar-w:240px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--paper-dim);color:var(--ink);min-height:100vh;display:flex;}
.sidebar{width:var(--sidebar-w);background:var(--ink);color:var(--paper);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;overflow-y:auto;}
.sidebar-brand{padding:1.5rem;border-bottom:1px solid rgba(245,242,235,0.08);}
.sb-logo{font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:900;color:var(--paper);}
.sb-logo span{color:var(--saffron);}
.sb-admin{padding:.5rem 1.5rem 1rem;font-family:'DM Mono',monospace;font-size:0.62rem;letter-spacing:.1em;color:rgba(245,242,235,0.35);border-bottom:1px solid rgba(245,242,235,0.08);}
.sidebar-nav{padding:1rem 0;flex:1;}
.nav-sec{font-family:'DM Mono',monospace;font-size:0.55rem;letter-spacing:.15em;color:rgba(245,242,235,0.3);padding:.5rem 1.5rem .25rem;text-transform:uppercase;}
.nav-item a{display:flex;align-items:center;gap:.75rem;padding:.65rem 1.5rem;font-size:.86rem;color:rgba(245,242,235,.6);text-decoration:none;transition:all .2s;border-left:3px solid transparent;}
.nav-item a:hover{color:var(--paper);background:rgba(245,242,235,.05);}
.nav-item a.active{color:var(--paper);background:rgba(255,107,0,.1);border-left-color:var(--saffron);}
.nav-item a i{font-size:1rem;width:18px;}
.sb-foot{padding:1rem 1.5rem;border-top:1px solid rgba(245,242,235,.08);}
.sb-foot a{font-size:.78rem;color:rgba(245,242,235,.4);text-decoration:none;display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;}
.sb-foot a:hover{color:var(--paper);}
.main-content{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:var(--paper);border-bottom:1px solid var(--rule);padding:.9rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
.topbar-title{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:900;}
.admin-pill{background:rgba(255,107,0,.1);color:var(--saffron);padding:.25rem .75rem;border-radius:2px;font-family:'DM Mono',monospace;font-size:.68rem;letter-spacing:.07em;}
.content-body{padding:2rem;flex:1;}
.stat-card{background:var(--paper);border:1px solid var(--rule);border-radius:4px;padding:1.3rem 1.5rem;}
.stat-card.dark{background:var(--ink);color:var(--paper);}
.stat-label{font-family:'DM Mono',monospace;font-size:.65rem;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:.5rem;}
.stat-label.dk{color:rgba(245,242,235,.4);}
.stat-num{font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:900;line-height:1;}
.stat-sub{font-size:.74rem;color:var(--muted);margin-top:.4rem;}
.stat-icon{font-size:1.5rem;margin-bottom:.6rem;}
.sec-card{background:var(--paper);border:1px solid var(--rule);border-radius:4px;margin-bottom:1.5rem;}
.sch{padding:1rem 1.5rem;border-bottom:1px solid var(--rule);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;}
.sct{font-family:'Playfair Display',serif;font-size:1rem;font-weight:900;}
.scb{padding:1.5rem;}
.vs-label{display:block;font-size:.72rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--ink);margin-bottom:.4rem;font-family:'DM Mono',monospace;}
.vs-input,.vs-select,.vs-textarea{width:100%;padding:.7rem 1rem;font-family:'DM Sans',sans-serif;font-size:.88rem;background:var(--paper);color:var(--ink);border:1.5px solid rgba(10,10,15,.18);border-radius:3px;outline:none;transition:border-color .2s,box-shadow .2s;}
.vs-input:focus,.vs-select:focus,.vs-textarea:focus{border-color:var(--saffron);box-shadow:0 0 0 3px rgba(255,107,0,.1);}
.vs-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b6b72' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 1rem center;}
.vs-textarea{resize:vertical;min-height:80px;}
.btn-vs{display:inline-flex;align-items:center;gap:.4rem;font-family:'DM Sans',sans-serif;font-size:.84rem;font-weight:500;padding:.6rem 1.2rem;border-radius:3px;border:none;cursor:pointer;text-decoration:none;transition:all .2s;}
.btn-saffron{background:var(--saffron);color:#fff;}.btn-saffron:hover{background:var(--saffron-d);color:#fff;}
.btn-green{background:var(--green);color:#fff;}.btn-green:hover{background:var(--green-l);color:#fff;}
.btn-outline{background:transparent;color:var(--ink);border:1.5px solid var(--rule);}.btn-outline:hover{border-color:var(--ink);}
.btn-dv{background:rgba(220,38,38,.08);color:#dc2626;border:1.5px solid rgba(220,38,38,.2);}.btn-dv:hover{background:#dc2626;color:#fff;}
.btn-sm-vs{padding:.35rem .75rem;font-size:.78rem;}
.vs-table{width:100%;border-collapse:collapse;font-size:.85rem;}
.vs-table th{font-family:'DM Mono',monospace;font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);padding:.6rem 1rem;border-bottom:2px solid var(--rule);text-align:left;font-weight:600;}
.vs-table td{padding:.75rem 1rem;border-bottom:1px solid var(--rule);vertical-align:middle;}
.vs-table tr:last-child td{border-bottom:none;}
.vs-table tr:hover td{background:rgba(255,107,0,.02);}
.bv{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .65rem;border-radius:2px;font-family:'DM Mono',monospace;font-size:.65rem;letter-spacing:.05em;}
.bg{background:rgba(10,122,62,.12);color:var(--green);}
.bo{background:rgba(255,107,0,.12);color:var(--saffron);}
.br{background:rgba(220,38,38,.1);color:#dc2626;}
.bgy{background:rgba(10,10,15,.07);color:var(--muted);}
.bbl{background:rgba(37,99,235,.1);color:#2563eb;}
.vote-bar{height:6px;background:var(--rule);border-radius:3px;overflow:hidden;margin-top:4px;}
.vote-bar-fill{height:100%;background:var(--saffron);border-radius:3px;transition:width .6s ease;}
.vs-ok{background:rgba(10,122,62,.08);border-left:3px solid var(--green);color:var(--ink);padding:.75rem 1rem;border-radius:3px;font-size:.86rem;margin-bottom:1.2rem;}
.vs-err{background:rgba(220,38,38,.08);border-left:3px solid #dc2626;color:#dc2626;padding:.75rem 1rem;border-radius:3px;font-size:.86rem;margin-bottom:1.2rem;}
.modal-header,.modal-footer{border-color:var(--rule);}
.modal-title{font-family:'Playfair Display',serif;font-weight:900;}
@media(max-width:768px){.sidebar{transform:translateX(-100%);transition:transform .3s;}.main-content{margin-left:0;}.sidebar.open{transform:translateX(0);}}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
</style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand"><div class="sb-logo">Vote<span>Sure</span></div></div>
  <div class="sb-admin"><i class="bi bi-shield-check me-1" style="color:var(--saffron)"></i>ADMIN: <?= htmlspecialchars($_SESSION['admin_username']) ?></div>
  <nav class="sidebar-nav">
    <div class="nav-sec">Overview</div>
    <div class="nav-item"><a href="?page=dashboard" class="<?= $page==='dashboard'?'active':'' ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a></div>
    <div class="nav-sec">Elections</div>
    <div class="nav-item"><a href="?page=elections" class="<?= $page==='elections'?'active':'' ?>"><i class="bi bi-calendar-event"></i> Elections<?php if($stats['active_elections']>0):?><span style="margin-left:auto;background:var(--green);color:#fff;border-radius:10px;padding:1px 7px;font-size:.6rem"><?=$stats['active_elections']?></span><?php endif;?></a></div>
    <div class="nav-item"><a href="?page=candidates" class="<?= $page==='candidates'?'active':'' ?>"><i class="bi bi-people"></i> Candidates</a></div>
    <div class="nav-item"><a href="?page=results" class="<?= $page==='results'?'active':'' ?>"><i class="bi bi-bar-chart"></i> Results</a></div>
    <div class="nav-sec">Voters</div>
    <div class="nav-item"><a href="?page=voters" class="<?= $page==='voters'?'active':'' ?>"><i class="bi bi-person-lines-fill"></i> Voter Management<?php if($stats['pending_voters']>0):?><span style="margin-left:auto;background:var(--saffron);color:#fff;border-radius:10px;padding:1px 7px;font-size:.6rem"><?=$stats['pending_voters']?></span><?php endif;?></a></div>
    <div class="nav-item"><a href="?page=logs" class="<?= $page==='logs'?'active':'' ?>"><i class="bi bi-journal-text"></i> Audit Logs</a></div>
  </nav>
  <div class="sb-foot">
    <a href="register.php" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View Voter Site</a>
    <a href="results.php" target="_blank"><i class="bi bi-bar-chart-line"></i> Public Results</a>
    <a href="?logout=1" onclick="return confirm('Log out?')"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<div class="main-content">
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <button class="btn-vs btn-outline d-md-none" onclick="document.getElementById('sidebar').classList.toggle('open')"><i class="bi bi-list"></i></button>
      <span class="topbar-title"><?php $t=['dashboard'=>'Dashboard','elections'=>'Elections','candidates'=>'Candidates','results'=>'Results','voters'=>'Voter Management','logs'=>'Audit Logs']; echo $t[$page]??'Dashboard'; ?></span>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <span class="admin-pill"><i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($_SESSION['admin_username']) ?></span>
      <a href="results.php" target="_blank" class="btn-vs btn-outline btn-sm-vs"><i class="bi bi-bar-chart-line"></i> Live Results</a>
    </div>
  </div>
  <div class="content-body">
    <?php if($success):?><div class="vs-ok" id="succ"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars(urldecode($success)) ?></div><?php endif;?>
    <?php if($err):?><div class="vs-err"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($err) ?></div><?php endif;?>

<?php if($page==='dashboard'): ?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="stat-card dark"><div class="stat-icon">🗳️</div><div class="stat-label dk">Total Voters</div><div class="stat-num"><?= number_format($stats['total_voters']) ?></div><div class="stat-sub" style="color:rgba(245,242,235,.4)"><?=$stats['verified_voters']?> verified · <?=$stats['pending_voters']?> pending</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon">✅</div><div class="stat-label">Votes Cast</div><div class="stat-num" style="color:var(--green)"><?= number_format($stats['votes_cast']) ?></div><div class="stat-sub"><?=$stats['total_voters']>0?round($stats['votes_cast']/$stats['total_voters']*100,1):0?>% turnout</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon">📋</div><div class="stat-label">Active Elections</div><div class="stat-num" style="color:var(--saffron)"><?=$stats['active_elections']?></div><div class="stat-sub">of <?=$stats['total_elections']?> total</div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-label">Pending Approvals</div><div class="stat-num" style="color:#f59e0b"><?=$stats['pending_voters']?></div><div class="stat-sub"><a href="?page=voters" style="color:var(--saffron);text-decoration:none">Review →</a></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="sec-card">
      <div class="sch"><span class="sct">Recent Voters</span><a href="?page=voters" class="btn-vs btn-outline btn-sm-vs">View All</a></div>
      <table class="vs-table"><thead><tr><th>Name</th><th>Voter Ref</th><th>City</th><th>Status</th><th>Voted</th></tr></thead><tbody>
      <?php foreach(array_slice($voters,0,8) as $v):?>
      <tr><td><strong><?=htmlspecialchars($v['first_name'].' '.$v['last_name'])?></strong><br><small style="color:var(--muted);font-size:.74rem"><?=htmlspecialchars($v['email'])?></small></td><td style="font-family:'DM Mono',monospace;font-size:.74rem"><?=htmlspecialchars($v['voter_ref'])?></td><td><?=htmlspecialchars($v['city'])?></td>
      <td><?=$v['status']==='verified'?'<span class="bv bg">✓ Verified</span>':($v['status']==='blocked'?'<span class="bv br">Blocked</span>':'<span class="bv bo">Pending</span>')?></td>
      <td><?=$v['has_voted']?'<span class="bv bg">✓</span>':'<span class="bv bgy">—</span>'?></td></tr>
      <?php endforeach; if(empty($voters)):?><tr><td colspan="5" class="text-center text-muted py-3">No voters yet.</td></tr><?php endif;?>
      </tbody></table>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="sec-card">
      <div class="sch"><span class="sct">Live Results Snapshot</span><a href="?page=results" class="btn-vs btn-outline btn-sm-vs">Full Results</a></div>
      <div class="scb">
        <?php $byEl=[]; foreach($results as $r) $byEl[$r['election_title']][]=$r;
        if(empty($byEl)):?><div class="text-center text-muted py-4" style="font-size:.86rem"><i class="bi bi-bar-chart" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>No votes yet.</div>
        <?php else: foreach($byEl as $et=>$cands): $total=array_sum(array_column($cands,'votes'));?>
        <div class="mb-4"><div style="font-size:.72rem;font-family:'DM Mono',monospace;color:var(--muted);margin-bottom:.6rem"><?=htmlspecialchars($et)?> · <?=$total?> votes</div>
        <?php foreach($cands as $c): $pct=$total>0?round($c['votes']/$total*100,1):0;?>
        <div class="mb-2"><div class="d-flex justify-content-between" style="font-size:.82rem"><span><?=htmlspecialchars(($c['symbol']??'').' '.$c['name'])?></span><span style="font-family:'DM Mono',monospace"><?=$c['votes']?> (<?=$pct?>%)</span></div>
        <div class="vote-bar"><div class="vote-bar-fill" style="width:<?=$pct?>%;background:<?=$c===reset($cands)?'var(--green)':'var(--saffron)'?>"></div></div></div>
        <?php endforeach;?></div>
        <?php endforeach; endif;?>
      </div>
    </div>
  </div>
</div>
<div class="sec-card">
  <div class="sch"><span class="sct">Recent Activity</span><a href="?page=logs" class="btn-vs btn-outline btn-sm-vs">All Logs</a></div>
  <table class="vs-table"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Result</th><th>IP</th></tr></thead><tbody>
  <?php foreach(array_slice($audit_logs,0,8) as $log):?>
  <tr><td style="font-family:'DM Mono',monospace;font-size:.72rem;white-space:nowrap"><?=date('d M H:i',strtotime($log['created_at']))?></td>
  <td style="font-size:.82rem"><?=htmlspecialchars(trim($log['voter_name'])?:('ID #'.$log['voter_id']))?></td>
  <td><span class="bv bgy"><?=htmlspecialchars($log['action'])?></span></td>
  <td><?=$log['result']==='ok'?'<span class="bv bg">OK</span>':'<span class="bv br">FAIL</span>'?></td>
  <td style="font-family:'DM Mono',monospace;font-size:.72rem"><?=htmlspecialchars($log['ip_address']??'')?></td></tr>
  <?php endforeach;?>
  </tbody></table>
</div>

<?php elseif($page==='elections'): ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="sec-card">
      <div class="sch"><span class="sct">All Elections (<?=count($elections)?>)</span></div>
      <table class="vs-table"><thead><tr><th>Title</th><th>Period</th><th>Status</th><th>Candidates</th><th>Votes</th><th>Actions</th></tr></thead><tbody>
      <?php foreach($elections as $e): $now=time(); $s=strtotime($e['start_time']); $en=strtotime($e['end_time']);?>
      <tr>
        <td><strong><?=htmlspecialchars($e['title'])?></strong><?php if($e['description']):?><br><small class="text-muted"><?=htmlspecialchars(substr($e['description'],0,50))?></small><?php endif;?></td>
        <td style="font-size:.78rem;font-family:'DM Mono',monospace;white-space:nowrap"><?=date('d M Y',strtotime($e['start_time']))?><br><span style="color:var(--muted)">→ <?=date('d M Y',strtotime($e['end_time']))?></span></td>
        <td><?php if(!$e['is_active']):?><span class="bv bgy">Inactive</span><?php elseif($now<$s):?><span class="bv bbl">Upcoming</span><?php elseif($now>$en):?><span class="bv bgy">Ended</span><?php else:?><span class="bv bg" style="animation:none">● Live</span><?php endif;?></td>
        <td style="font-family:'DM Mono',monospace"><?=$e['cand_count']?></td>
        <td style="font-family:'DM Mono',monospace;font-weight:700"><?=$e['vote_count']?></td>
        <td><div class="d-flex gap-1 flex-wrap">
          <form method="POST" class="d-inline"><input type="hidden" name="act" value="toggle_election"><input type="hidden" name="id" value="<?=$e['id']?>"><button class="btn-vs btn-outline btn-sm-vs"><?=$e['is_active']?'Deactivate':'Activate'?></button></form>
          <form method="POST" class="d-inline" onsubmit="return confirm('Delete election and all votes?')"><input type="hidden" name="act" value="delete_election"><input type="hidden" name="id" value="<?=$e['id']?>"><button class="btn-vs btn-dv btn-sm-vs"><i class="bi bi-trash"></i></button></form>
        </div></td>
      </tr>
      <?php endforeach; if(empty($elections)):?><tr><td colspan="6" class="text-center text-muted py-4">No elections yet.</td></tr><?php endif;?>
      </tbody></table>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="sec-card"><div class="sch"><span class="sct">Create Election</span></div><div class="scb">
      <form method="POST"><input type="hidden" name="act" value="add_election">
        <div class="mb-3"><label class="vs-label">Title *</label><input class="vs-input" type="text" name="title" placeholder="2026 Municipal Election" required></div>
        <div class="mb-3"><label class="vs-label">Description</label><textarea class="vs-textarea" name="description" placeholder="Optional…"></textarea></div>
        <div class="mb-3"><label class="vs-label">Start *</label><input class="vs-input" type="datetime-local" name="start_time" required></div>
        <div class="mb-3"><label class="vs-label">End *</label><input class="vs-input" type="datetime-local" name="end_time" required></div>
        <button class="btn-vs btn-saffron w-100" type="submit"><i class="bi bi-plus-circle"></i> Create Election</button>
      </form>
    </div></div>
  </div>
</div>

<?php elseif($page==='candidates'): ?>
<div class="sec-card mb-3" style="border-left:4px solid var(--saffron)"><div class="scb"><strong>📋 How to Add Candidates</strong><ol style="font-size:.84rem;color:var(--muted);margin:.5rem 0 0;padding-left:1.2rem;line-height:2"><li>First <a href="?page=elections" style="color:var(--saffron)">create an election</a></li><li>Select election, fill candidate details, click Add Candidate</li><li>Voters see only active candidates for the currently running election</li></ol></div></div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="sec-card"><div class="sch"><span class="sct">All Candidates (<?=count($candidates)?>)</span></div>
      <table class="vs-table"><thead><tr><th>#</th><th>Candidate</th><th>Party</th><th>Constituency</th><th>Election</th><th>Status</th><th>Votes</th><th>Actions</th></tr></thead><tbody>
      <?php foreach($candidates as $i=>$c):?>
      <tr><td style="font-family:'DM Mono',monospace;font-size:.75rem"><?=$i+1?></td>
      <td><strong><?=htmlspecialchars(($c['symbol']??'').' '.$c['name'])?></strong></td>
      <td style="font-size:.82rem"><?=htmlspecialchars($c['party']??'—')?></td>
      <td style="font-size:.82rem"><?=htmlspecialchars($c['constituency']??'—')?></td>
      <td><span class="bv bo" style="font-size:.6rem"><?=htmlspecialchars(substr($c['election_title']??'',0,20))?></span></td>
      <td><?=$c['is_active']?'<span class="bv bg">Active</span>':'<span class="bv bgy">Hidden</span>'?></td>
      <td style="font-family:'DM Mono',monospace;font-weight:700"><?=$c['vote_count']?></td>
      <td><div class="d-flex gap-1">
        <button class="btn-vs btn-outline btn-sm-vs" data-bs-toggle="modal" data-bs-target="#editCandModal" data-id="<?=$c['id']?>" data-name="<?=htmlspecialchars($c['name'])?>" data-party="<?=htmlspecialchars($c['party']??'')?>" data-symbol="<?=htmlspecialchars($c['symbol']??'')?>" data-const="<?=htmlspecialchars($c['constituency']??'')?>"><i class="bi bi-pencil"></i></button>
        <form method="POST" class="d-inline"><input type="hidden" name="act" value="toggle_candidate"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn-vs btn-outline btn-sm-vs" type="submit"><i class="bi bi-eye<?=$c['is_active']?'':'-slash'?>"></i></button></form>
        <form method="POST" class="d-inline" onsubmit="return confirm('Delete candidate?')"><input type="hidden" name="act" value="delete_candidate"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn-vs btn-dv btn-sm-vs"><i class="bi bi-trash"></i></button></form>
      </div></td></tr>
      <?php endforeach; if(empty($candidates)):?><tr><td colspan="8" class="text-center text-muted py-4">No candidates yet.</td></tr><?php endif;?>
      </tbody></table>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="sec-card"><div class="sch"><span class="sct">Add Candidate</span></div><div class="scb">
      <form method="POST"><input type="hidden" name="act" value="add_candidate">
        <div class="mb-3"><label class="vs-label">Election *</label><select class="vs-select" name="election_id" required><option value="">— Select —</option><?php foreach($elections as $e):?><option value="<?=$e['id']?>"><?=htmlspecialchars($e['title'])?><?=!$e['is_active']?' (Inactive)':''?></option><?php endforeach;?></select></div>
        <div class="mb-3"><label class="vs-label">Full Name *</label><input class="vs-input" type="text" name="name" placeholder="Rajiv Sharma" required></div>
        <div class="mb-3"><label class="vs-label">Party</label><input class="vs-input" type="text" name="party" placeholder="National Progress Party"></div>
        <div class="mb-3"><label class="vs-label">Symbol (Emoji)</label><input class="vs-input" type="text" name="symbol" placeholder="🌟" maxlength="4" value="🗳️"><div style="font-size:.72rem;color:var(--muted);margin-top:.3rem">Any emoji — shown on ballot</div></div>
        <div class="mb-3"><label class="vs-label">Constituency</label><input class="vs-input" type="text" name="constituency" placeholder="Ward 14"></div>
        <button class="btn-vs btn-saffron w-100"><i class="bi bi-person-plus"></i> Add Candidate</button>
      </form>
    </div></div>
  </div>
</div>

<?php elseif($page==='results'):
$byEl=[]; foreach($results as $r) $byEl[$r['eid']][]=$r;
if(empty($byEl)):?><div class="text-center text-muted py-5"><i class="bi bi-bar-chart" style="font-size:2.5rem;display:block;margin-bottom:.5rem"></i>No results yet.</div>
<?php else: foreach($byEl as $eid=>$cands): $total=array_sum(array_column($cands,'votes')); $winner=$cands[0]; $etitle=$cands[0]['election_title']??''; $isLive=$cands[0]['election_active']??0;?>
<div class="sec-card mb-4">
  <div class="sch"><div><span class="sct"><?=htmlspecialchars($etitle)?></span><span class="ms-2 bv <?=$isLive?'bg':'bgy'?>" style="font-size:.65rem"><?=$isLive?'● ACTIVE':'INACTIVE'?></span><span class="ms-1 bv bo" style="font-size:.65rem"><?=$total?> votes</span></div>
  <form method="POST" class="d-inline" onsubmit="return confirm('Reset all votes for this election?')"><input type="hidden" name="act" value="reset_votes"><input type="hidden" name="election_id" value="<?=$eid?>"><button class="btn-vs btn-dv btn-sm-vs"><i class="bi bi-arrow-counterclockwise"></i> Reset</button></form></div>
  <div class="scb">
    <?php if($total>0&&$winner):?><div class="mb-3 p-3" style="background:rgba(10,122,62,.07);border-radius:3px;border-left:3px solid var(--green)"><div style="font-size:.7rem;font-family:'DM Mono',monospace;color:var(--green);margin-bottom:.3rem">🏆 LEADING</div><div style="font-size:1.1rem;font-weight:700"><?=htmlspecialchars(($winner['symbol']??'').' '.$winner['name'])?></div><div style="font-size:.82rem;color:var(--muted)"><?=htmlspecialchars($winner['party']??'')?> · <?=$winner['votes']?> votes (<?=$total>0?round($winner['votes']/$total*100,1):0?>%)</div></div><?php endif;?>
    <?php foreach($cands as $idx=>$c): $pct=$total>0?round($c['votes']/$total*100,1):0;?>
    <div class="mb-3"><div class="d-flex justify-content-between align-items-center mb-1"><span style="font-size:.9rem;font-weight:500"><?=$idx===0&&$total>0?'👑 ':''?><?=htmlspecialchars(($c['symbol']??'').' '.$c['name'])?> <small class="text-muted" style="font-family:'DM Mono',monospace;font-size:.74rem">(<?=htmlspecialchars($c['party']??'')?>)</small></span><span style="font-family:'DM Mono',monospace;font-size:.84rem;font-weight:700"><?=$c['votes']?> <small class="text-muted">(<?=$pct?>%)</small></span></div>
    <div class="vote-bar"><div class="vote-bar-fill" style="width:<?=$pct?>%;background:<?=$idx===0?'var(--green)':'var(--saffron)'?>"></div></div></div>
    <?php endforeach;?>
  </div>
</div>
<?php endforeach; endif;?>

<?php elseif($page==='voters'):?>
<div class="d-flex gap-2 mb-3 flex-wrap">
  <span class="bv bgy" style="padding:.4rem .9rem;font-size:.75rem">Total: <?=$stats['total_voters']?></span>
  <span class="bv bg" style="padding:.4rem .9rem;font-size:.75rem">Verified: <?=$stats['verified_voters']?></span>
  <span class="bv bo" style="padding:.4rem .9rem;font-size:.75rem">Pending: <?=$stats['pending_voters']?></span>
  <span class="bv br" style="padding:.4rem .9rem;font-size:.75rem">Blocked: <?=$stats['blocked_voters']?></span>
</div>
<div class="sec-card">
  <div class="sch"><span class="sct">Voter Management</span><input type="search" id="voterSearch" class="vs-input" style="width:240px" placeholder="Search name, voter ID, city…" oninput="filterVoters(this.value)"></div>
  <table class="vs-table" id="voterTable"><thead><tr><th>#</th><th>Name</th><th>Voter Ref</th><th>Mobile</th><th>City</th><th>Status</th><th>Voted</th><th>Registered</th><th>Action</th></tr></thead><tbody>
  <?php foreach($voters as $v):?>
  <tr class="voter-row"><td style="font-family:'DM Mono',monospace;font-size:.72rem"><?=$v['id']?></td>
  <td><strong><?=htmlspecialchars($v['first_name'].' '.$v['last_name'])?></strong><br><small style="color:var(--muted);font-size:.74rem"><?=htmlspecialchars($v['email'])?></small></td>
  <td style="font-family:'DM Mono',monospace;font-size:.74rem"><?=htmlspecialchars($v['voter_ref'])?><br><span style="color:var(--muted)"><?=htmlspecialchars($v['voter_id'])?></span></td>
  <td style="font-family:'DM Mono',monospace;font-size:.8rem"><?=htmlspecialchars($v['mobile'])?></td>
  <td><?=htmlspecialchars($v['city'])?></td>
  <td><?=$v['status']==='verified'?'<span class="bv bg">✓ Verified</span>':($v['status']==='blocked'?'<span class="bv br">Blocked</span>':'<span class="bv bo">Pending</span>')?></td>
  <td><?=$v['has_voted']?'<span class="bv bg">✓ Yes</span>':'<span class="bv bgy">—</span>'?></td>
  <td style="font-size:.75rem;color:var(--muted);white-space:nowrap"><?=date('d M Y',strtotime($v['created_at']))?></td>
  <td><form method="POST" class="d-inline"><input type="hidden" name="act" value="voter_status"><input type="hidden" name="id" value="<?=$v['id']?>"><select name="status" class="vs-select" style="width:auto;padding:.3rem .5rem;font-size:.76rem" onchange="this.form.submit()"><option value="pending" <?=$v['status']==='pending'?'selected':''?>>Pending</option><option value="verified" <?=$v['status']==='verified'?'selected':''?>>Verified</option><option value="blocked" <?=$v['status']==='blocked'?'selected':''?>>Blocked</option></select></form></td>
  </tr>
  <?php endforeach; if(empty($voters)):?><tr><td colspan="9" class="text-center text-muted py-4">No voters yet.</td></tr><?php endif;?>
  </tbody></table>
</div>

<?php elseif($page==='logs'):?>
<div class="sec-card">
  <div class="sch"><span class="sct">Audit Logs (last 150)</span><input type="search" class="vs-input" style="width:220px" placeholder="Filter…" oninput="filterLogs(this.value)"></div>
  <table class="vs-table" id="logsTable"><thead><tr><th>Time</th><th>Voter</th><th>Action</th><th>Result</th><th>IP</th><th>Details</th></tr></thead><tbody>
  <?php $ac=['LOGIN'=>'bbl','LOGOUT'=>'bgy','REGISTER'=>'bg','VOTE'=>'bo','ADMIN_LOGIN'=>'bo'];
  foreach($audit_logs as $log):?>
  <tr class="log-row"><td style="font-family:'DM Mono',monospace;font-size:.72rem;white-space:nowrap"><?=date('d M Y H:i',strtotime($log['created_at']))?></td>
  <td style="font-size:.82rem"><?=htmlspecialchars(trim($log['voter_name'])?:('ID #'.$log['voter_id']))?></td>
  <td><span class="bv <?=$ac[$log['action']]??'bgy'?>"><?=htmlspecialchars($log['action'])?></span></td>
  <td><?=$log['result']==='ok'?'<span class="bv bg">OK</span>':'<span class="bv br">FAIL</span>'?></td>
  <td style="font-family:'DM Mono',monospace;font-size:.72rem"><?=htmlspecialchars($log['ip_address']??'—')?></td>
  <td style="font-size:.8rem;color:var(--muted)"><?=htmlspecialchars($log['details']??'')?></td></tr>
  <?php endforeach; if(empty($audit_logs)):?><tr><td colspan="6" class="text-center text-muted py-4">No logs yet.</td></tr><?php endif;?>
  </tbody></table>
</div>
<?php endif;?>

  </div>
</div>

<!-- EDIT CANDIDATE MODAL -->
<div class="modal fade" id="editCandModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content" style="border-radius:4px">
  <div class="modal-header"><h5 class="modal-title">Edit Candidate</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="act" value="edit_candidate"><input type="hidden" name="id" id="editCandId">
    <div class="modal-body">
      <div class="mb-3"><label class="vs-label">Name *</label><input class="vs-input" type="text" name="name" id="editCandName" required></div>
      <div class="mb-3"><label class="vs-label">Party</label><input class="vs-input" type="text" name="party" id="editCandParty"></div>
      <div class="mb-3"><label class="vs-label">Symbol (Emoji)</label><input class="vs-input" type="text" name="symbol" id="editCandSymbol" maxlength="4"></div>
      <div class="mb-3"><label class="vs-label">Constituency</label><input class="vs-input" type="text" name="constituency" id="editCandConst"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn-vs btn-outline" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-vs btn-saffron"><i class="bi bi-check2"></i> Save</button></div>
  </form>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editCandModal')?.addEventListener('show.bs.modal',function(e){
  const b=e.relatedTarget;
  document.getElementById('editCandId').value=b.dataset.id;
  document.getElementById('editCandName').value=b.dataset.name;
  document.getElementById('editCandParty').value=b.dataset.party;
  document.getElementById('editCandSymbol').value=b.dataset.symbol;
  document.getElementById('editCandConst').value=b.dataset.const;
});
function filterVoters(q){q=q.toLowerCase();document.querySelectorAll('.voter-row').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});}
function filterLogs(q){q=q.toLowerCase();document.querySelectorAll('.log-row').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});}
const s=document.getElementById('succ');if(s)setTimeout(()=>{s.style.transition='opacity .5s';s.style.opacity='0';setTimeout(()=>s.remove(),500);},4000);
<?php if($page==='results'):?>setTimeout(()=>location.reload(),30000);<?php endif;?>
</script>
</body></html>
