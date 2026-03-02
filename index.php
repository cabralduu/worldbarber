<?php
// index.php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once 'actions.php';

if (!function_exists('normalize_phone')) {
    function normalize_phone($phone){
        return preg_replace('/\D/', '', (string)$phone);
    }
}

function generate_available_slots($date, $service_duration){
    global $pdo, $blocked_intervals;
    $schedule   = load_schedule();
    $start_time = $schedule ? $schedule['open_time'] : '09:00';
    $end_time   = $schedule ? $schedule['end_time']  : '17:00';

    $start = strtotime($date.' '.$start_time);
    $end   = strtotime($date.' '.$end_time);

    $stmt = $pdo->prepare("SELECT b.*, s.duration as service_duration FROM bookings b JOIN services s ON b.service_id=s.id WHERE date=?");
    $stmt->execute([$date]);
    $bookings = $stmt->fetchAll();

    $slots = [];
    $t = $start;
    while ($t + $service_duration * 60 <= $end) {
        $slot_start = $t;
        $slot_end   = $t + $service_duration * 60;
        $busy = false;

        foreach ($bookings as $b) {
            $b_start = strtotime($date.' '.$b['time']);
            $b_end   = $b_start + $b['service_duration'] * 60;
            if (!($slot_end <= $b_start || $slot_start >= $b_end)) { $busy = true; break; }
        }

        foreach ($blocked_intervals as $bl) {
            $bl_start = strtotime(($bl['date'] === '0000-00-00' ? $date : $bl['date']).' '.$bl['start_time']);
            $bl_end   = strtotime(($bl['date'] === '0000-00-00' ? $date : $bl['date']).' '.$bl['end_time']);
            if (!($slot_end <= $bl_start || $slot_start >= $bl_end)) { $busy = true; break; }
        }

        $slots[] = ['time' => date('H:i', $slot_start), 'available' => !$busy];
        $t += $service_duration * 60;
    }

    return $slots;
}

$services  = load_services();
$today     = date('Y-m-d');
$selected_date = $_POST['date'] ?? '';
$service_id    = $_POST['service_id'] ?? '';
$service       = $service_id ? get_service_by_id($service_id) : null;

$blocked_intervals = array_merge(load_blocked_intervals(), load_fixed_intervals());

$success  = !empty($_GET['success']) && $_GET['success'] == 1;
$error    = $_GET['error'] ?? '';

$search_phone_raw = $_POST['search_phone'] ?? '';
$search_code_raw  = $_POST['search_code']  ?? '';
$search_phone     = '';
$search_results   = [];
$search_attempted = false;

if (!empty($_POST['search_phone'])) {
    $search_attempted = true;
    $search_phone     = normalize_phone($search_phone_raw);
    $search_results   = load_bookings_by_phone($search_phone, $search_code_raw);
}

$confirmation_code = $_GET['code'] ?? '';

if (isset($_GET['cancel_id'])) {
    cancel_booking($_GET['cancel_id']);
    echo "<script>alert('Agendamento cancelado!'); window.location.href='index.php';</script>";
    exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>World Barber</title>
<link rel="icon" type="image/png" href="icon.png">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Georgia', serif;
    background: #1a1a1a;
    color: #f0ece4;
    min-height: 100vh;
    padding: 1.5rem 1rem 4rem;
  }

  a { color: inherit; text-decoration: none; }
  .wrap { max-width: 460px; margin: 0 auto; }

  /* header */
  header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #3a3a3a;
  }
  header h1 { font-size: 1.5rem; letter-spacing: 0.08em; color: #d4af7a; }
  header a {
    font-size: 0.8rem; color: #888;
    border: 1px solid #444; padding: 0.35rem 0.8rem;
    border-radius: 6px; transition: border-color 0.2s, color 0.2s;
  }
  header a:hover { border-color: #d4af7a; color: #d4af7a; }

  /* panels */
  .panel {
    background: #242424; border: 1px solid #333;
    border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;
  }
  .panel h2 {
    font-size: 0.8rem; font-weight: normal;
    letter-spacing: 0.1em; color: #d4af7a;
    margin-bottom: 1.2rem; text-transform: uppercase;
  }

  /* forms */
  label {
    display: block; font-size: 0.72rem; color: #888;
    letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 0.35rem;
  }
  input[type="text"], input[type="date"], select {
    width: 100%; background: #1a1a1a; border: 1px solid #444;
    color: #f0ece4; border-radius: 8px; padding: 0.6rem 0rem;
    font-size: 0.95rem; font-family: inherit; outline: none;
    transition: border-color 0.2s; margin-bottom: 1rem; appearance: auto;
  }
  input:focus, select:focus { border-color: #d4af7a; }

  /* buttons */
  .btn {
    display: block; width: 100%; padding: 0.7rem; border: none;
    border-radius: 8px; font-size: 0.93rem; font-family: inherit;
    cursor: pointer; letter-spacing: 0.04em; transition: opacity 0.2s;
  }
  .btn:hover { opacity: 0.82; }
  .btn-gold { background: #c9a05a; color: #1a1a1a; font-weight: bold; }
  .btn-dark { background: #3a3a3a; color: #f0ece4; }
  .btn-red  { background: #9b3030; color: #fff; }
  .btn-blue { background: #2b5278; color: #fff; font-weight: bold; }
  .btn-sm   { display: inline-block; width: auto; padding: 0.4rem 0.9rem; font-size: 0.83rem; }

  /* slots */
  .slots-scroll { overflow-x: auto; padding-bottom: 0.5rem; }
  .slots-row    { display: inline-flex; gap: 0.75rem; }
  .slot {
    min-width: 88px; padding: 0.9rem 0.6rem; border-radius: 10px;
    text-align: center; border: 1px solid transparent;
    transition: transform 0.15s, border-color 0.15s;
  }
  .slot-free { background: #2a2a2a; border-color: #555; cursor: pointer; }
  .slot-free:hover { border-color: #d4af7a; transform: translateY(-2px); }
  .slot-busy { background: #1f1414; border-color: #4a2222; color: #6a4040; cursor: not-allowed; opacity: 0.65; }
  .slot-time  { font-size: 0.98rem; font-weight: bold; margin-bottom: 0.2rem; }
  .slot-dur   { font-size: 0.7rem; color: #888; }
  .slot-price { font-size: 0.83rem; margin-top: 0.3rem; color: #d4af7a; }

  /* modal */
  .modal {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.78);
    align-items: center; justify-content: center; z-index: 100;
  }
  .modal.open { display: flex; }
  .modal-box {
    background: #242424; border: 1px solid #444;
    border-radius: 12px; padding: 1.6rem; width: 92%; max-width: 360px;
  }
  .modal-box h3 { font-size: 0.95rem; color: #d4af7a; margin-bottom: 1.2rem; }
  .modal-actions { display: flex; gap: 0.6rem; justify-content: flex-end; margin-top: 0.4rem; }

  /* alerts */
  .alert { border-radius: 8px; padding: 0.8rem 1rem; margin-bottom: 1.2rem; font-size: 0.88rem; line-height: 1.6; }
  .alert-green  { background: #1a2e1a; border: 1px solid #3a6b3a; color: #8ecf8e; }
  .alert-yellow { background: #2a2210; border: 1px solid #6b5520; color: #c9a55a; }
  .alert-red    { background: #2a1010; border: 1px solid #6b2020; color: #cf8e8e; }

  .code-box {
    background: #1a1a1a; border: 1px solid #3a6b3a;
    border-radius: 8px; padding: 0.8rem; margin-top: 0.8rem; text-align: center;
  }
  .code-box .code {
    font-size: 1.8rem; font-family: monospace;
    letter-spacing: 0.2em; color: #8ecf8e; display: block; margin: 0.4rem 0;
  }
  .code-box small { color: #666; font-size: 0.76rem; }

  /* booking cards */
  .booking-card {
    min-width: 180px; background: #2a2a2a; border: 1px solid #444;
    border-left: 4px solid #d4af7a; border-radius: 10px;
    padding: 0.9rem; position: relative; font-size: 0.86rem; line-height: 1.8;
  }
  .booking-badge {
    position: absolute; top: 0.5rem; right: 0.5rem;
    background: #333; color: #d4af7a; font-size: 0.72rem;
    padding: 0.15rem 0.5rem; border-radius: 99px; font-family: monospace;
  }
  .booking-card strong { color: #d4af7a; }

  .section-title {
    font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.1em;
    color: #888; margin-bottom: 1rem; text-align: center;
  }
  .empty-msg { text-align: center; color: #555; font-size: 0.88rem; padding: 1rem 0; }

  footer { position: fixed; bottom: 8px; right: 14px; font-size: 0.7rem; color: #3a3a3a; }
</style>
</head>
<body>
<div class="wrap">

  <header>
    <h1>World Barber</h1>
    <a href="admin_login.php">Loja</a>
  </header>

  <div class="panel">
    <h2>Novo agendamento</h2>
    <form method="post">
      <label>Data — terça a sábado</label>
      <input type="date" id="datePicker" name="date" min="<?=date('Y-m-d')?>" value="" required>
      <label>Serviço</label>
      <select name="service_id" required>
        <option value=""></option>
        <?php foreach ($services as $s): ?>
          <option value="<?=$s['id']?>" <?=($service_id==$s['id'])?'selected':''?>>
            <?=$s['name']?> (<?=$s['duration']?> min) — R$ <?=$s['price']?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-gold">Ver horários</button>
    </form>
  </div>

  <?php if ($service && $selected_date):
      $slots = generate_available_slots($selected_date, $service['duration']);
  ?>
  <p class="section-title"><?=date('d/m/Y', strtotime($selected_date))?> — <?=htmlspecialchars($service['name'])?></p>
  <div class="slots-scroll" style="margin-bottom:1.5rem;">
    <div class="slots-row">
      <?php foreach ($slots as $s): ?>
        <div class="slot <?=$s['available'] ? 'slot-free' : 'slot-busy'?>"
             <?=$s['available'] ? "onclick=\"openModal('modal-{$s['time']}')\"" : ''?>>
          <div class="slot-time"><?=$s['time']?></div>
          <div class="slot-dur"><?=$service['duration']?> min</div>
          <div class="slot-price">R$ <?=$service['price']?></div>
        </div>

        <?php if ($s['available']): ?>
        <div id="modal-<?=$s['time']?>" class="modal">
          <div class="modal-box">
            <h3>Agendar às <?=$s['time']?> — <?=htmlspecialchars($service['name'])?></h3>
            <form method="post" action="actions.php">
              <input type="hidden" name="action"     value="book">
              <input type="hidden" name="date"       value="<?=$selected_date?>">
              <input type="hidden" name="time"       value="<?=$s['time']?>">
              <input type="hidden" name="service_id" value="<?=$service_id?>">
              <label>Nome</label>
              <input type="text" name="name"  placeholder="Nome completo" required>
              <label>Telefone</label>
              <input type="text" name="phone" placeholder="DDD + número" required>
              <div class="modal-actions">
                <button type="button" class="btn btn-dark btn-sm" onclick="closeModal('modal-<?=$s['time']?>')">Voltar</button>
                <button type="submit" class="btn btn-blue btn-sm">Confirmar</button>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-green">
      Agendamento confirmado!
      <?php if ($confirmation_code): ?>
      <div class="code-box">
        <span>Seu código de confirmação</span>
        <span class="code"><?=htmlspecialchars($confirmation_code)?></span>
        <small>Guarde este código — você vai precisar dele para consultar ou cancelar.</small>
      </div>
      <?php endif; ?>
    </div>
  <?php elseif ($error === 'conflict'): ?>
    <div class="alert alert-yellow">Horário já ocupado. Escolha outro horário.</div>
  <?php elseif ($error === 'missing'): ?>
    <div class="alert alert-red">Dados incompletos. Tente novamente.</div>
  <?php endif; ?>

  <div class="panel">
    <h2>Meus agendamentos</h2>
    <form method="post">
      <label>Telefone</label>
      <input type="text" name="search_phone" placeholder="DDD + número"
             value="<?=htmlspecialchars($search_phone_raw)?>" required>
      <label>Código de confirmação</label>
      <input type="text" name="search_code" placeholder="Ex: A1B2C3"
             value="<?=htmlspecialchars($search_code_raw)?>"
             required maxlength="6" style="text-transform:uppercase;">
      <button type="submit" class="btn btn-gold">Consultar</button>
    </form>

    <?php if ($search_attempted && empty($search_results)): ?>
      <p class="empty-msg" style="color:#cf8e8e;margin-top:1rem;">Telefone ou código incorretos.</p>
    <?php endif; ?>

    <?php if ($search_phone && $search_results): ?>
      <div class="slots-scroll" style="margin-top:1.2rem;">
        <div class="slots-row">
          <?php foreach ($search_results as $b):
              $svc_name = htmlspecialchars(get_service_by_id($b['service_id'])['name']);
          ?>
            <div class="booking-card">
              <span class="booking-badge"><?=$b['time']?></span>
              <strong><?=$svc_name?></strong><br>
              <?=date('d/m/Y', strtotime($b['date']))?><br>
              <?=htmlspecialchars($b['phone'])?>
              <a href="index.php?cancel_id=<?=$b['id']?>"
                 onclick="return confirm('Cancelar este agendamento?')"
                 class="btn btn-red"
                 style="margin-top:0.7rem;display:block;text-align:center;padding:0.4rem;border-radius:6px;font-size:0.8rem;">
                Cancelar
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php elseif ($search_phone && $search_attempted): ?>
      <p class="empty-msg">Nenhum agendamento encontrado.</p>
    <?php endif; ?>
  </div>

</div>

<footer>© 2026 PI 1 Univesp</footer>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.getElementById('datePicker')?.addEventListener('input', function() {
  const d = new Date(this.value);
  if (d.getDay() === 0 || d.getDay() === 6) this.value = '';
});
</script>
</body>
</html>