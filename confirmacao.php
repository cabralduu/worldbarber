<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Agendamento Confirmado — World Barber</title>
<link rel="icon" type="image/png" href="icon.png">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-gray-900 via-blue-200 to-gray-900 min-h-screen flex items-center justify-center p-4">

<?php
$code = strtoupper(trim($_GET['code'] ?? ''));
if (!$code || strlen($code) !== 6) {
    header('Location: index.php');
    exit;
}
?>

<div class="bg-white rounded-3xl shadow-2xl p-8 max-w-sm w-full text-center">

    <div class="text-6xl mb-4">✅</div>
    <h1 class="text-2xl font-extrabold text-green-700 mb-2">Agendado!</h1>
    <p class="text-gray-500 mb-6 text-sm">Guarde seu código de confirmação. Você precisará dele para consultar ou cancelar seu agendamento.</p>

    <!-- Código em destaque -->
    <div class="bg-purple-50 border-2 border-purple-400 rounded-2xl p-5 mb-6">
        <p class="text-sm font-semibold text-purple-600 mb-2">Seu código de confirmação</p>
        <p id="codigo" class="text-4xl font-extrabold tracking-widest text-purple-800"><?=htmlspecialchars($code)?></p>
    </div>

    <!-- Botão copiar -->
    <button onclick="copiarCodigo()" id="btnCopiar"
        class="w-full bg-purple-700 hover:bg-purple-800 text-white font-bold py-3 px-6 rounded-xl mb-3 transition transform hover:scale-105">
        📋 Copiar código
    </button>

    <!-- Botão voltar -->
    <a href="index.php" class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 px-6 rounded-xl transition">
        ← Voltar para o início
    </a>

    <p class="text-xs text-gray-400 mt-4">Esta página pode ser fechada após copiar o código.</p>
</div>

<script>
function copiarCodigo() {
    const codigo = document.getElementById('codigo').textContent.trim();
    navigator.clipboard.writeText(codigo).then(() => {
        const btn = document.getElementById('btnCopiar');
        btn.textContent = '✅ Copiado!';
        btn.classList.remove('bg-purple-700', 'hover:bg-purple-800');
        btn.classList.add('bg-green-600');
        setTimeout(() => {
            btn.textContent = '📋 Copiar código';
            btn.classList.add('bg-purple-700', 'hover:bg-purple-800');
            btn.classList.remove('bg-green-600');
        }, 3000);
    }).catch(() => {
        // Fallback para navegadores mais antigos
        const el = document.createElement('textarea');
        el.value = codigo;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        alert('Código copiado: ' + codigo);
    });
}
</script>

</body>
</html>
