<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once 'actions.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user  = trim($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $admin = load_admin_by_username($user);
    if (!$admin || !isset($admin['password']) || !password_verify($pass, $admin['password'])) {
        $error = 'Usuário ou senha incorretos.';
    } else {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        header('Location: admin_panel.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>World Barber — Acesso</title>
<link rel="icon" type="image/png" href="icon.png">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Georgia', serif;
    background: #1a1a1a;
    color: #f0ece4;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
  }

  .card {
    background: #242424;
    border: 1px solid #333;
    border-radius: 12px;
    padding: 2.5rem 2rem;
    width: 100%;
    max-width: 380px;
  }

  .card h1 {
    font-size: 1.1rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: #d4af7a;
    text-align: center;
    margin-bottom: 2rem;
    font-weight: normal;
  }

  .alert-red {
    background: #2a1010;
    border: 1px solid #6b2020;
    color: #cf8e8e;
    border-radius: 8px;
    padding: 0.7rem 1rem;
    font-size: 0.85rem;
    text-align: center;
    margin-bottom: 1.2rem;
  }

  label {
    display: block;
    font-size: 0.72rem;
    color: #888;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin-bottom: 0.35rem;
  }

  input[type="text"],
  input[type="password"] {
    width: 100%;
    background: #1a1a1a;
    border: 1px solid #444;
    color: #f0ece4;
    border-radius: 8px;
    padding: 0.65rem 0.85rem;
    font-size: 0.95rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
    margin-bottom: 1.1rem;
  }
  input:focus { border-color: #d4af7a; }

  .btn {
    display: block;
    width: 100%;
    padding: 0.72rem;
    border: none;
    border-radius: 8px;
    font-size: 0.93rem;
    font-family: inherit;
    cursor: pointer;
    letter-spacing: 0.05em;
    transition: opacity 0.2s;
    text-align: center;
    text-decoration: none;
  }
  .btn:hover { opacity: 0.82; }
  .btn-gold  { background: #c9a05a; color: #1a1a1a; font-weight: bold; }
  .btn-ghost {
    background: transparent;
    border: 1px solid #444;
    color: #888;
    margin-top: 0.75rem;
    font-size: 0.85rem;
  }
  .btn-ghost:hover { border-color: #d4af7a; color: #d4af7a; }
</style>
</head>
<body>

<div class="card">
  <h1>PAINEL DO BARBEIRO</h1>

  <?php if ($error !== ''): ?>
    <div class="alert-red"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <form method="post">
    <label>Usuário</label>
    <input type="text" name="username" placeholder="" required autofocus>

    <label>Senha</label>
    <input type="password" name="password" placeholder="*****" required>

    <button type="submit" class="btn btn-gold">Entrar</button>
  </form>

  <a href="index.php" class="btn btn-ghost">← Voltar ao início</a>
</div>

</body>
</html>