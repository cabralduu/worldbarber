<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once 'actions.php';

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$services          = load_services();
$bookings          = load_all_bookings();
$received          = load_received_bookings();
$clients           = load_clients();
$wallet_rows       = load_wallet();
$wallet            = [];
foreach ($wallet_rows as $w) $wallet[$w['month']] = $w['total'];
$blocked_intervals = load_blocked_intervals();
$fixed_intervals   = load_fixed_intervals();
$schedule          = load_schedule();

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

$received_per_month = [];
foreach ($received as $r) {
    $m = date('Y-m', strtotime($r['date']));
    if (!isset($received_per_month[$m])) $received_per_month[$m] = 0;
    $received_per_month[$m]++;
}
$months_received = json_encode(array_keys($received_per_month));
$counts_received = json_encode(array_values($received_per_month));
$months_json     = json_encode(array_keys($wallet));
$totals_json     = json_encode(array_values($wallet));

$page = $_GET['page'] ?? 'dashboard';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<title>World Barber — Admin</title>
<link rel="icon" type="image/png" href="icon.png">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Georgia', serif;
    background: #141414;
    color: #f0ece4;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  a { color: inherit; text-decoration: none; }

  /* ── layout ── */
  .layout { display: flex; flex: 1; min-height: 100vh; }

  /* ── sidebar ── */
  .sidebar {
    width: 220px;
    flex-shrink: 0;
    background: #1c1c1c;
    border-right: 1px solid #2a2a2a;
    display: flex;
    flex-direction: column;
  }

  .sidebar-logo {
    padding: 1.5rem 1.2rem 1rem;
    border-bottom: 1px solid #2a2a2a;
    font-size: 1rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #d4af7a;
  }

  .sidebar nav { flex: 1; padding: 0.8rem 0; }

  .sidebar nav a {
    display: block;
    padding: 0.6rem 1.2rem;
    font-size: 0.82rem;
    letter-spacing: 0.06em;
    color: #aaa;
    text-transform: uppercase;
    transition: color 0.2s, background 0.2s;
    border-left: 2px solid transparent;
  }

  .sidebar nav a:hover,
  .sidebar nav a.active {
    color: #d4af7a;
    background: #242424;
    border-left-color: #d4af7a;
  }

  .sidebar-footer {
    padding: 1rem 1.2rem;
    border-top: 1px solid #2a2a2a;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  /* ── main content ── */
  .main { flex: 1; padding: 2rem 2.5rem; overflow: auto; }

  .page-title {
    font-size: 1rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #d4af7a;
    margin-bottom: 1.5rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid #2a2a2a;
    font-weight: normal;
  }

  /* ── buttons ── */
  .btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 7px;
    font-size: 0.82rem;
    font-family: inherit;
    cursor: pointer;
    letter-spacing: 0.04em;
    transition: opacity 0.2s;
    text-align: center;
    white-space: nowrap;
  }
  .btn:hover { opacity: 0.82; }
  .btn-gold   { background: #c9a05a; color: #1a1a1a; font-weight: bold; }
  .btn-green  { background: #2d6a3f; color: #fff; }
  .btn-red    { background: #7a2020; color: #fff; }
  .btn-dark   { background: #333; color: #ccc; }
  .btn-yellow { background: #8a6a10; color: #fff; }
  .btn-block  { display: block; width: 100%; }
  .btn-wa     { background: #1a4d2e; color: #a8e6bf; }

  /* ── forms ── */
  .form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    align-items: flex-end;
  }

  .form-row label { display: block; font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.3rem; }

  .form-row input,
  .form-row select {
    background: #1a1a1a;
    border: 1px solid #444;
    color: #f0ece4;
    border-radius: 7px;
    padding: 0.5rem 0.7rem;
    font-size: 0.88rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
  }
  .form-row input:focus { border-color: #d4af7a; }

  /* ── cards grid ── */
  .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }

  .card {
    background: #1e1e1e;
    border: 1px solid #2e2e2e;
    border-radius: 10px;
    padding: 1rem;
    font-size: 0.85rem;
    line-height: 1.7;
  }

  .card strong { color: #d4af7a; display: block; margin-bottom: 0.3rem; font-size: 0.9rem; }
  .card .card-actions { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.8rem; }

  .card-accent-blue   { border-left: 3px solid #3a6b9b; }
  .card-accent-green  { border-left: 3px solid #3a7a4a; }
  .card-accent-red    { border-left: 3px solid #7a2a2a; }
  .card-accent-yellow { border-left: 3px solid #9b7a20; }

  /* ── stat cards ── */
  .stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }

  .stat {
    background: #1e1e1e;
    border: 1px solid #2e2e2e;
    border-radius: 10px;
    padding: 1.2rem;
  }

  .stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 0.5rem; }
  .stat-value { font-size: 1.8rem; color: #d4af7a; font-weight: bold; }

  /* ── charts ── */
  .chart-wrap {
    background: #1e1e1e;
    border: 1px solid #2e2e2e;
    border-radius: 10px;
    padding: 1.2rem;
    margin-bottom: 1.5rem;
  }

  .chart-wrap h3 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 1rem; font-weight: normal; }

  /* ── wallet months ── */
  .wallet-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.8rem; margin-bottom: 1.5rem; }

  .wallet-card {
    background: #1e1e1e;
    border: 1px solid #2e2e2e;
    border-left: 3px solid #d4af7a;
    border-radius: 8px;
    padding: 0.9rem;
    text-align: center;
  }

  .wallet-card .month { font-size: 0.75rem; color: #888; letter-spacing: 0.06em; text-transform: uppercase; }
  .wallet-card .amount { font-size: 1.2rem; color: #d4af7a; font-weight: bold; margin-top: 0.2rem; }

  .danger-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; }

  /* ── responsive ── */
  @media (max-width: 700px) {
    .layout { flex-direction: column; }
    .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #2a2a2a; }
    .sidebar nav { display: flex; flex-wrap: wrap; padding: 0.5rem; }
    .sidebar nav a { border-left: none; border-bottom: 2px solid transparent; }
    .main { padding: 1.2rem; }
  }
</style>
    
</head>
<body>
<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">World Barber</div>
    <nav>
      <?php
      $nav = [
        'dashboard'      => 'Dashboard',
        'services'       => 'Serviços',
        'bookings'       => 'Agendamentos',
        'received'       => 'Recebidos',
        'clients'        => 'Clientes',
        'blocked'        => 'Bloquear Horários',
        'fixed_intervals'=> 'Intervalos Fixos',
        'schedule'       => 'Expediente',
      ];
      foreach ($nav as $key => $label):
      ?>
        <a href="?page=<?=$key?>" class="<?=$page===$key?'active':''?>"><?=$label?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
      <a href="index.php" class="btn btn-green btn-block">Ver site</a>
      <form method="post" action="actions.php">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="btn btn-red btn-block">Logout</button>
      </form>
    </div>
  </aside>

  <!-- Main -->
  <main class="main">
  <?php switch($page):

  // ── SERVIÇOS ──
  case 'services': ?>
    <h2 class="page-title">Serviços</h2>
    <form method="post" action="actions.php" class="form-row">
      <input type="hidden" name="action" value="save_service">
      <div><label>Nome</label><input type="text" name="name" placeholder="" required></div>
      <div><label>Duração (min)</label><input type="number" name="duration" placeholder="" required style="width:100px"></div>
      <div><label>Preço</label><input type="number" step="0.01" name="price" placeholder="" required style="width:100px"></div>
      <button type="submit" class="btn btn-gold">Salvar</button>
    </form>
    <div class="cards">
      <?php foreach ($services as $s): ?>
      <div class="card card-accent-yellow">
        <strong><?=htmlspecialchars($s['name'])?></strong>
        <?=$s['duration']?> min &nbsp;·&nbsp; R$ <?=$s['price']?>
        <div class="card-actions">
          <form method="post" action="actions.php">
            <input type="hidden" name="action" value="delete_service">
            <input type="hidden" name="id" value="<?=$s['id']?>">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
            <button class="btn btn-red">Excluir</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php break;

  // ── AGENDAMENTOS ATIVOS ──
  case 'bookings': ?>
    <h2 class="page-title">Agendamentos Ativos</h2>
    <div class="cards">
    <?php foreach ($bookings as $b):
      $svc  = get_service_by_id($b['service_id']);
      $phone = preg_replace('/\D/', '', $b['phone']);
      $msg  = "Olá {$b['name']}!\n\nLembrete do seu agendamento na *World Barber*.\n\n✂️ Serviço: {$svc['name']}\n📅 Data: ".date('d/m/Y', strtotime($b['date']))."\n🕒 Horário: {$b['time']}\n\nTe esperamos!";
      $wa   = "https://wa.me/{$phone}?text=".urlencode($msg);
    ?>
      <div class="card card-accent-blue">
        <strong><?=htmlspecialchars($svc['name'])?></strong>
        <?=htmlspecialchars($b['name'])?><br>
        <?=htmlspecialchars($b['phone'])?><br>
        <?=date('d/m/Y', strtotime($b['date']))?> às <?=$b['time']?>
        <div class="card-actions">
        <a href="<?=$wa?>" class="btn btn-wa">
  <i class="fab fa-whatsapp"></i></a>
          <form method="post" action="actions.php">
            <input type="hidden" name="action" value="receive_booking">
            <input type="hidden" name="id" value="<?=$b['id']?>">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
            <button class="btn btn-green">Receber</button>
          </form>
          <form method="post" action="actions.php">
            <input type="hidden" name="action" value="cancel_booking">
            <input type="hidden" name="id" value="<?=$b['id']?>">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
            <button class="btn btn-red">Cancelar</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php break;

  // ── RECEBIDOS ──
  case 'received': ?>
    <h2 class="page-title">Agendamentos Recebidos</h2>
    <div class="cards">
    <?php foreach ($received as $r): $svc = get_service_by_id($r['service_id']); ?>
      <div class="card card-accent-green">
        <strong><?=htmlspecialchars($svc['name'])?></strong>
        <?=htmlspecialchars($r['name'])?><br>
        <?=htmlspecialchars($r['phone'])?><br>
        <?=date('d/m/Y', strtotime($r['date']))?> às <?=$r['time']?>
      </div>
    <?php endforeach; ?>
    </div>
  <?php break;

  // ── CLIENTES ──
  case 'clients': ?>
    <h2 class="page-title">Clientes</h2>
    <div class="cards">
    <?php foreach ($clients as $c):
      $phone = preg_replace('/\D/', '', $c['phone']);
      $msg   = "Olá {$c['name']}! 💈\n\nAqui é da *World Barber*.\nTemos novos horários disponíveis — bora renovar o visual?";
      $wa    = "https://wa.me/{$phone}?text=".urlencode($msg);
    ?>
      <div class="card card-accent-yellow">
        <strong><?=htmlspecialchars($c['name'])?></strong>
        <?=htmlspecialchars($c['phone'])?>
        <div class="card-actions">
         <a href="<?=$wa?>" class="btn btn-wa">
  <i class="fab fa-whatsapp"></i></a>
          <form method="post" action="actions.php">
            <input type="hidden" name="action" value="delete_client">
            <input type="hidden" name="id" value="<?=$c['id']?>">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
            <button class="btn btn-red">Excluir</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php break;

  // ── BLOQUEAR HORÁRIOS ──
  case 'blocked': ?>
    <h2 class="page-title">Bloquear Horários</h2>
    <form method="post" action="actions.php" class="form-row">
      <input type="hidden" name="action" value="block_interval">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
      <div><label>Data</label><input type="date" name="date" required></div>
      <div><label>De</label><input type="time" name="start_time" required></div>
      <div><label>Até</label><input type="time" name="end_time" required></div>
      <button type="submit" class="btn btn-gold">Bloquear</button>
    </form>
    <div class="cards">
    <?php foreach ($blocked_intervals as $b): ?>
      <div class="card card-accent-red">
        <strong><?=date('d/m/Y', strtotime($b['date']))?></strong>
        <?=$b['start_time']?> → <?=$b['end_time']?>
        <div class="card-actions">
          <form method="post" action="actions.php">
            <input type="hidden" name="action" value="unblock_interval">
            <input type="hidden" name="id" value="<?=$b['id']?>">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
            <button class="btn btn-red">Desbloquear</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php break;

  // ── INTERVALOS FIXOS ──
  case 'fixed_intervals': ?>
    <h2 class="page-title">Intervalos Fixos (todos os dias)</h2>
    <form method="post" action="actions.php" class="form-row">
      <input type="hidden" name="action" value="block_fixed_interval">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
      <div><label>De</label><input type="time" name="start_time" required></div>
      <div><label>Até</label><input type="time" name="end_time" required></div>
      <button type="submit" class="btn btn-gold">Bloquear</button>
    </form>
    <div class="cards">
    <?php foreach ($fixed_intervals as $f): ?>
      <div class="card card-accent-red">
        <strong>Diário</strong>
        <?=$f['start_time']?> → <?=$f['end_time']?>
        <div class="card-actions">
          <form method="post" action="actions.php">
            <input type="hidden" name="action" value="unblock_fixed_interval">
            <input type="hidden" name="id" value="<?=$f['id']?>">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
            <button class="btn btn-red">Desbloquear</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php break;

  // ── EXPEDIENTE ──
  case 'schedule': ?>
    <h2 class="page-title">Expediente</h2>
    <form method="post" action="actions.php" class="form-row">
      <input type="hidden" name="action" value="save_schedule">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
      <div><label>Abertura</label><input type="time" name="open_time" value="<?=htmlspecialchars($schedule['open_time'])?>" required></div>
      <div><label>Fechamento</label><input type="time" name="end_time" value="<?=htmlspecialchars($schedule['end_time'])?>" required></div>
      <button type="submit" class="btn btn-gold">Salvar</button>
    </form>
  <?php break;

  // ── DASHBOARD ──
  default: ?>
    <h2 class="page-title">Dashboard</h2>

    <div class="stats">
      <div class="stat">
        <div class="stat-label">Agendamentos ativos</div>
        <div class="stat-value"><?=count($bookings)?></div>
      </div>
      <div class="stat">
        <div class="stat-label">Recebidos</div>
        <div class="stat-value"><?=count($received)?></div>
      </div>
      <div class="stat">
        <div class="stat-label">Receita total</div>
        <div class="stat-value" style="font-size:1.3rem">R$ <?=number_format(array_sum($wallet),2,',','.')?></div>
      </div>
    </div>

    <div class="chart-wrap">
      <h3>Receita por mês (R$)</h3>
      <canvas id="walletChart"></canvas>
    </div>

    <div class="chart-wrap">
      <h3>Atendimentos por mês</h3>
      <canvas id="receivedChart"></canvas>
    </div>

    <h3 style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.08em;color:#888;margin-bottom:0.8rem;font-weight:normal;">Saldo mensal</h3>
    <div class="wallet-grid">
      <?php foreach ($wallet as $month => $total): ?>
      <div class="wallet-card">
        <div class="month"><?=$month?></div>
        <div class="amount">R$ <?=number_format($total,2,',','.')?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="danger-actions">
      <form method="post" action="actions.php" onsubmit="return confirm('Zerar toda a carteira e recebidos?')">
        <input type="hidden" name="action" value="reset_wallet_all">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
        <button class="btn btn-yellow">Zerar saldo total</button>
      </form>
      <form method="post" action="actions.php" onsubmit="return confirm('Zerar carteira do mês atual?')">
        <input type="hidden" name="action" value="reset_wallet_month">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
        <button class="btn btn-yellow">Zerar saldo do mês</button>
      </form>
    </div>

    <script>
    const chartDefaults = {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: '#888' }, grid: { color: '#2a2a2a' } },
        y: { ticks: { color: '#888' }, grid: { color: '#2a2a2a' } }
      }
    };

    new Chart(document.getElementById('walletChart').getContext('2d'), {
      type: 'bar',
      data: {
        labels: <?=$months_json?>,
        datasets: [{ label: 'R$', data: <?=$totals_json?>, backgroundColor: '#c9a05a', borderRadius: 4 }]
      },
      options: chartDefaults
    });

    new Chart(document.getElementById('receivedChart').getContext('2d'), {
      type: 'line',
      data: {
        labels: <?=$months_received?>,
        datasets: [{
          label: 'Atendimentos',
          data: <?=$counts_received?>,
          borderColor: '#5a9b6a',
          backgroundColor: 'rgba(90,155,106,0.15)',
          fill: true,
          tension: 0.3,
          pointBackgroundColor: '#5a9b6a'
        }]
      },
      options: { ...chartDefaults, plugins: { legend: { display: false } } }
    });
    </script>
  <?php endswitch; ?>
  </main>

</div>
</body>
</html>