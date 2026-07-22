<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
$error = '';
$activeRole = (string)($_POST['role'] ?? $_GET['perfil'] ?? 'employee');
$employeeMode = (string)($_POST['mode'] ?? $_GET['modo'] ?? 'login');

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sessão expirada. Atualize a página.';
    } elseif ($activeRole === 'manager') {
        if (hash_equals(manager_pin(), (string)($_POST['pin'] ?? ''))) {
            session_regenerate_id(true); unset($_SESSION['employee']); $_SESSION['manager'] = true;
            header('Location: gestor.php'); exit;
        }
        $error = 'PIN do gestor inválido.';
    } elseif ($employeeMode === 'register') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $team = trim((string)($_POST['team'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirmation'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Informe nome e e-mail corporativo válido.';
        elseif (strlen($password) < 8) $error = 'A senha deve ter pelo menos 8 caracteres.';
        elseif ($password !== $confirmation) $error = 'As senhas não coincidem.';
        else {
            try {
                $stmt = db()->prepare('INSERT INTO users (name, email, team, password_hash) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $email, $team, password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true); unset($_SESSION['manager']);
                $_SESSION['employee'] = ['id' => (int)db()->lastInsertId(), 'name' => $name, 'email' => $email, 'team' => $team, 'role' => 'employee'];
                header('Location: index.php'); exit;
            } catch (PDOException $exception) {
                $error = str_contains($exception->getMessage(), 'UNIQUE') ? 'Já existe uma conta com este e-mail.' : 'Não foi possível criar a conta.';
            }
        }
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT id, name, email, team, password_hash, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]); $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true); unset($_SESSION['manager'], $user['password_hash']); $_SESSION['employee'] = $user;
            header('Location: index.php'); exit;
        }
        $error = 'E-mail ou senha inválidos.';
    }
}
function lh(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="pt-BR"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Entrar — Híbrido</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/login.css">
</head><body class="unified-login">
  <header class="topbar"><a class="brand" href="login.php"><span class="mark">H</span> HÍBRIDO<span class="accent">.</span></a><span class="secure-label">● AMBIENTE SEGURO</span></header>
  <main class="login-shell">
    <section class="login-copy"><p class="eyebrow">CONTROLE DE HOME OFFICE</p><h1>Um só acesso.<br><em>Duas experiências.</em></h1><p>Cadastre-se como colaborador para manter seu histórico ou entre como gestor para acompanhar toda a equipe.</p><div class="login-note"><b>H</b><span>Após o envio, seus registros ficam disponíveis apenas para consulta e não podem ser alterados pelo colaborador.</span></div></section>
    <section class="card unified-card">
      <div class="role-tabs" role="tablist"><a class="<?= $activeRole !== 'manager' ? 'active' : '' ?>" href="?perfil=employee">Sou colaborador</a><a class="<?= $activeRole === 'manager' ? 'active' : '' ?>" href="?perfil=manager">Sou gestor</a></div>
      <?php if ($activeRole === 'manager'): ?>
        <div class="login-heading"><span class="role-icon">↗</span><div><p class="eyebrow">ACESSO RESTRITO</p><h2>Painel do gestor</h2></div></div><p class="form-intro">Use o PIN administrativo para acessar indicadores e relatórios.</p>
        <form method="post" class="unified-form"><input type="hidden" name="csrf" value="<?= lh($_SESSION['csrf']) ?>"><input type="hidden" name="role" value="manager"><label>PIN administrativo<input name="pin" type="password" inputmode="numeric" autocomplete="current-password" autofocus required placeholder="••••"></label><?php if ($error): ?><p class="login-error"><?= lh($error) ?></p><?php endif; ?><button class="primary">Entrar como gestor <span>→</span></button></form>
      <?php else: ?>
        <div class="employee-auth-tabs"><a class="<?= $employeeMode === 'login' ? 'active' : '' ?>" href="?perfil=employee&modo=login">Já tenho cadastro</a><a class="<?= $employeeMode === 'register' ? 'active' : '' ?>" href="?perfil=employee&modo=register">Primeiro acesso</a></div>
        <?php if ($employeeMode === 'register'): ?>
          <div class="login-heading compact"><span class="role-icon">+</span><div><p class="eyebrow">PRIMEIRO ACESSO</p><h2>Crie sua conta</h2></div></div>
          <form method="post" class="unified-form register-form"><input type="hidden" name="csrf" value="<?= lh($_SESSION['csrf']) ?>"><input type="hidden" name="role" value="employee"><input type="hidden" name="mode" value="register"><label>Nome completo<input name="name" value="<?= lh((string)($_POST['name'] ?? '')) ?>" required></label><label>E-mail corporativo<input name="email" type="email" value="<?= lh((string)($_POST['email'] ?? '')) ?>" required></label><label>Equipe / área<input name="team" value="<?= lh((string)($_POST['team'] ?? '')) ?>"></label><div class="password-grid"><label>Senha<input name="password" type="password" minlength="8" required></label><label>Confirmar senha<input name="password_confirmation" type="password" minlength="8" required></label></div><?php if ($error): ?><p class="login-error"><?= lh($error) ?></p><?php endif; ?><button class="primary">Criar conta e continuar <span>→</span></button></form>
        <?php else: ?>
          <div class="login-heading compact"><span class="role-icon">H</span><div><p class="eyebrow">BEM-VINDO DE VOLTA</p><h2>Acesse sua conta</h2></div></div>
          <form method="post" class="unified-form"><input type="hidden" name="csrf" value="<?= lh($_SESSION['csrf']) ?>"><input type="hidden" name="role" value="employee"><input type="hidden" name="mode" value="login"><label>E-mail corporativo<input name="email" type="email" autocomplete="email" autofocus required></label><label>Senha<input name="password" type="password" autocomplete="current-password" required></label><?php if ($error): ?><p class="login-error"><?= lh($error) ?></p><?php endif; ?><button class="primary">Entrar na minha conta <span>→</span></button></form>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main><footer><b>HÍBRIDO.</b><span>Controle simples. Trabalho flexível.</span></footer>
</body></html>
