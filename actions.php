<?php
if (session_status() == PHP_SESSION_NONE) session_start();

/* -------------------------
   CONFIGURAÇÃO DO BANCO
------------------------- */
$host = 'sql103.infinityfree.com';
$db   = 'if0_41204542_world_barber';
$user = 'if0_41204542';
$pass = 'oL1S5VqHeF';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Erro de conexão: ' . $e->getMessage());
}

/* -------------------------
   FUNÇÕES AUXILIARES
------------------------- */
function normalize_phone($phone){
    return preg_replace('/\D/', '', (string)$phone);
}

/* -------------------------
   SERVIÇOS
------------------------- */
function load_services(){
    global $pdo;
    return $pdo->query("SELECT * FROM services ORDER BY id")->fetchAll();
}
function get_service_by_id($id){
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}
function save_service($data){
    global $pdo;
    if (!empty($data['id'])) {
        $stmt = $pdo->prepare("UPDATE services SET name=?, duration=?, price=? WHERE id=?");
        $stmt->execute([$data['name'],$data['duration'],$data['price'],$data['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO services (name,duration,price) VALUES (?,?,?)");
        $stmt->execute([$data['name'],$data['duration'],$data['price']]);
    }
}
function delete_service($id){
    global $pdo;
    $pdo->prepare("DELETE FROM services WHERE id=?")->execute([$id]);
}

/* -------------------------
   AGENDAMENTOS
------------------------- */
function load_all_bookings(){
    global $pdo;
    return $pdo->query("SELECT * FROM bookings ORDER BY date DESC,time DESC")->fetchAll();
}
function cancel_booking($id){
    global $pdo;
    $pdo->prepare("DELETE FROM bookings WHERE id=?")->execute([$id]);
}
function book($data){
    global $pdo;
    // Gera código de confirmação único de 6 caracteres
    $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $stmt = $pdo->prepare("INSERT INTO bookings (service_id,name,phone,date,time,confirmation_code) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$data['service_id'],$data['name'],$data['phone'],$data['date'],$data['time'],$code]);
    return $code;
}

/* -------------------------
   BUSCA POR TELEFONE
------------------------- */
function load_bookings_by_phone($phone, $code = null){
    global $pdo;
    $phone_norm = normalize_phone($phone);
    if ($code) {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE phone = ? AND UPPER(confirmation_code) = UPPER(?)");
        $stmt->execute([$phone_norm, trim($code)]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE phone = ?");
        $stmt->execute([$phone_norm]);
    }
    return $stmt->fetchAll();
}

/* -------------------------
   RECEBIDOS
------------------------- */
function load_received_bookings(){
    global $pdo;
    return $pdo->query("SELECT * FROM received_bookings ORDER BY date DESC,time DESC")->fetchAll();
}

function receive_booking($id){
    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id=?");
        $stmt->execute([$id]);
        $b = $stmt->fetch();
        if($b){
            $stmt2 = $pdo->prepare("INSERT INTO received_bookings (service_id,name,phone,date,time) VALUES (?,?,?,?,?)");
            $stmt2->execute([$b['service_id'],$b['name'],$b['phone'],$b['date'],$b['time']]);
            add_client($b['name'],$b['phone']);
            cancel_booking($id);
            update_wallet($b['service_id'],$b['date']);
        }
        $pdo->commit();
    } catch(Exception $e){
        $pdo->rollBack();
        throw $e;
    }
}

/* -------------------------
   CLIENTES
------------------------- */
function add_client($name,$phone){
    global $pdo;
    $phone_norm = normalize_phone($phone);
    $stmt = $pdo->prepare("INSERT IGNORE INTO clients (name,phone) VALUES (?,?)");
    $stmt->execute([$name,$phone_norm]);
}
function load_clients(){
    global $pdo;
    return $pdo->query("SELECT * FROM clients ORDER BY name")->fetchAll();
}
function delete_client($id){
    global $pdo;
    $pdo->prepare("DELETE FROM clients WHERE id=?")->execute([$id]);
}

/* -------------------------
   CARTEIRA
------------------------- */
function load_wallet(){
    global $pdo;
    return $pdo->query("SELECT * FROM wallet ORDER BY month DESC")->fetchAll();
}
function update_wallet($service_id,$date){
    global $pdo;
    $month = date('Y-m',strtotime($date));
    $service = get_service_by_id($service_id);
    $amount = $service ? $service['price'] : 0;
    $stmt = $pdo->prepare("INSERT INTO wallet (month,total) VALUES (?,?) ON DUPLICATE KEY UPDATE total=total+VALUES(total)");
    $stmt->execute([$month,$amount]);
}
function reset_wallet_all(){
    global $pdo;
    $pdo->exec("UPDATE wallet SET total=0");
    $pdo->exec("DELETE FROM received_bookings");
}
function reset_wallet_month($month){
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM received_bookings WHERE DATE_FORMAT(date,'%Y-%m')=?");
    $stmt->execute([$month]);
    $bookings = $stmt->fetchAll();
    foreach($bookings as $b){
        $service = get_service_by_id($b['service_id']);
        $amount = $service ? $service['price'] : 0;
        $stmt2 = $pdo->prepare("UPDATE wallet SET total=total-? WHERE month=?");
        $stmt2->execute([$amount,$month]);
    }
    $stmt3 = $pdo->prepare("DELETE FROM received_bookings WHERE DATE_FORMAT(date,'%Y-%m')=?");
    $stmt3->execute([$month]);
}

/* -------------------------
   BLOQUEIO DE HORÁRIOS
------------------------- */
function block_interval($date,$start,$end){
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO blocked_intervals (date,start_time,end_time) VALUES (?,?,?)");
    $stmt->execute([$date,$start,$end]);
}
function load_blocked_intervals(){
    global $pdo;
    return $pdo->query("SELECT * FROM blocked_intervals WHERE date<>'0000-00-00' ORDER BY date,start_time")->fetchAll();
}
function unblock_interval($id){
    global $pdo;
    $pdo->prepare("DELETE FROM blocked_intervals WHERE id=?")->execute([$id]);
}

/* -------------------------
   INTERVALOS FIXOS (TODOS OS DIAS)
------------------------- */
function block_fixed_interval($start,$end){
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO blocked_intervals (date,start_time,end_time) VALUES ('0000-00-00',?,?)");
    $stmt->execute([$start,$end]);
}
function load_fixed_intervals(){
    global $pdo;
    return $pdo->query("SELECT * FROM blocked_intervals WHERE date='0000-00-00' ORDER BY start_time")->fetchAll();
}
function unblock_fixed_interval($id){
    global $pdo;
    $pdo->prepare("DELETE FROM blocked_intervals WHERE id=?")->execute([$id]);
}

/* -------------------------
   EXPEDIENTE
------------------------- */
function load_schedule(){
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM schedule LIMIT 1");
    return $stmt->fetch();
}
function save_schedule($open_time,$end_time){
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO schedule (id,open_time,end_time) VALUES (1,?,?) ON DUPLICATE KEY UPDATE open_time=?, end_time=?");
    $stmt->execute([$open_time,$end_time,$open_time,$end_time]);
}

/* -------------------------
   ADMIN
------------------------- */
function load_admin_by_username($username){
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username=?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

/* -------------------------
   PROCESSO POST
------------------------- */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
    $action = $_POST['action'];
    switch($action){
        case 'book': $code = book($_POST); header('Location: confirmacao.php?code=' . $code); exit;
        case 'cancel_booking': cancel_booking($_POST['id']); header('Location: admin_panel.php'); exit;
        case 'receive_booking': receive_booking($_POST['id']); header('Location: admin_panel.php'); exit;
        case 'save_service': save_service($_POST); header('Location: admin_panel.php'); exit;
        case 'delete_service': delete_service($_POST['id']); header('Location: admin_panel.php'); exit;
        case 'block_interval': block_interval($_POST['date'],$_POST['start_time'],$_POST['end_time']); header('Location: admin_panel.php#blocked'); exit;
        case 'unblock_interval': unblock_interval($_POST['id']); header('Location: admin_panel.php#blocked'); exit;

        case 'block_fixed_interval': block_fixed_interval($_POST['start_time'],$_POST['end_time']); header('Location: admin_panel.php?page=fixed_intervals'); exit;
        case 'unblock_fixed_interval': unblock_fixed_interval($_POST['id']); header('Location: admin_panel.php?page=fixed_intervals'); exit;

        case 'delete_client': delete_client($_POST['id']); header('Location: admin_panel.php#clients'); exit;
        case 'reset_wallet_all': reset_wallet_all(); header('Location: admin_panel.php#wallet'); exit;
        case 'reset_wallet_month': reset_wallet_month(date('Y-m')); header('Location: admin_panel.php#wallet'); exit;
        case 'save_schedule': save_schedule($_POST['open_time'],$_POST['end_time']); header('Location: admin_panel.php#schedule'); exit;

        case 'login':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $admin = load_admin_by_username($username);
            if($admin && password_verify($password,$admin['password'])){
                session_regenerate_id(true);
                $_SESSION['admin']=true;
                if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
                header('Location: admin_panel.php'); exit;
            } else { header('Location: admin_login.php?error=1'); exit; }
        break;

        case 'logout': session_destroy(); header('Location: admin_login.php'); exit;
    }
}
?>
