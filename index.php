<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
if (empty($_SESSION['employee'])) { header('Location: login.php?perfil=employee'); exit; }
$employee = $_SESSION['employee'];

if (isset($_GET['api'])) {
    try {
        $action = (string) $_GET['api'];

        if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            if (!hash_equals($_SESSION['csrf'], (string)($payload['csrf'] ?? ''))) json_response(['error' => 'Sessão expirada. Atualize a página.'], 419);
            $name = (string)$employee['name'];
            $email = (string)$employee['email'];
            $team = (string)$employee['team'];
            $dates = array_values(array_unique((array)($payload['dates'] ?? [])));
            $dates = array_filter($dates, fn($date) => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date));
            if (!$dates) json_response(['error' => 'Selecione pelo menos um dia.'], 422);

            $stmt = db()->prepare("INSERT OR IGNORE INTO records (name, email, team, work_date, status) VALUES (?, ?, ?, ?, 'pending')");
            $saved = 0;
            db()->beginTransaction();
            foreach ($dates as $date) { $stmt->execute([$name, $email, $team, $date]); $saved += $stmt->rowCount(); }
            db()->commit();
            json_response(['saved' => $saved], 201);
        }

        json_response(['error' => 'Operação não encontrada.'], 404);
    } catch (Throwable $error) {
        if (db()->inTransaction()) db()->rollBack();
        json_response(['error' => 'Não foi possível concluir a operação.'], 500);
    }
}
$historyStmt = db()->prepare('SELECT work_date, created_at, status, reviewed_at FROM records WHERE email = ? ORDER BY work_date DESC');
$historyStmt->execute([(string)$employee['email']]);
$history = $historyStmt->fetchAll();
$historyWeekdays = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Registro e consolidação dos dias de trabalho remoto.">
  <title>Híbrido — Controle de Home Office</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/employee.css">
  <link rel="stylesheet" href="assets/brand-theme.css">
  <script src="assets/theme.js"></script>
</head>
<body data-csrf="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
  <header class="topbar">
    <a class="brand" href="index.php"><img src="assets/mix-fiscal-mark.png" alt="Mix Fiscal"><span>HÍBRIDO</span></a>
    <nav><span class="user-label"><?= htmlspecialchars($employee['name'], ENT_QUOTES) ?></span><a class="tab" href="login.php?logout=1">Sair</a></nav>
  </header>

  <main>
    <section id="employee" class="view workspace">
      <div class="hero"><div><p class="eyebrow">CONTROLE DE PRESENÇA</p><h1>Olá, <?= htmlspecialchars(explode(' ', trim($employee['name']))[0], ENT_QUOTES) ?>.<br>Solicite seu <em>home office.</em></h1><p>Marque no calendário os dias e envie para aprovação do gestor.</p></div><div class="counter"><strong id="selectedCount">0</strong><span>DIAS SELECIONADOS</span></div></div>
      <div class="layout">
        <article class="card identity">
          <div class="title"><span>01</span><h2>Seu perfil</h2></div>
          <div class="profile-data"><small>NOME</small><b><?= htmlspecialchars($employee['name'], ENT_QUOTES) ?></b><small>E-MAIL</small><span><?= htmlspecialchars($employee['email'], ENT_QUOTES) ?></span><small>EQUIPE</small><span><?= htmlspecialchars($employee['team'] ?: 'Não informada', ENT_QUOTES) ?></span></div>
          <a class="change-profile" href="login.php?logout=1">Trocar usuário</a>
          <p class="safe">✓ Suas solicitações serão vinculadas a este perfil.</p>
        </article>
        <article class="card calendar-card">
          <div class="calendar-head"><div class="title"><span>02</span><h2>Selecione os dias</h2></div><div class="month-nav"><button id="prevMonth" aria-label="Mês anterior">←</button><b id="monthLabel"></b><button id="nextMonth" aria-label="Próximo mês">→</button></div></div>
          <div class="week"><span>SEG</span><span>TER</span><span>QUA</span><span>QUI</span><span>SEX</span><span>SÁB</span><span>DOM</span></div>
          <div id="calendar" class="calendar"></div>
          <div class="action"><p id="notice">Revise as datas antes de enviar.</p><button id="register" class="primary">Enviar solicitação <span>→</span></button></div>
        </article>
      </div>
      <article class="card employee-history">
        <div class="history-head"><div><p class="eyebrow">MEU HISTÓRICO</p><h2>Dias já registrados</h2></div><div class="history-total"><strong><?= count($history) ?></strong><span>REGISTROS</span></div></div>
        <p class="readonly-note">🔒 Solicitações enviadas não podem ser alteradas. Acompanhe abaixo a análise do gestor.</p>
        <?php if (!$history): ?><p class="empty compact-empty">Você ainda não possui dias registrados.</p><?php else: ?>
        <div class="history-grid">
          <?php foreach ($history as $item): $statusLabel = ['pending'=>'Pendente','approved'=>'Aprovada','rejected'=>'Recusada'][$item['status']] ?? 'Pendente'; ?><div class="history-item status-<?= htmlspecialchars($item['status'], ENT_QUOTES) ?>" title="Enviado em <?= local_datetime($item['created_at']) ?>"><div class="date-badge"><strong><?= date('d', strtotime($item['work_date'])) ?></strong><span><?= strtoupper(date('M', strtotime($item['work_date']))) ?></span></div><div><b><?= date('d/m/Y', strtotime($item['work_date'])) ?></b><small><?= $historyWeekdays[(int)date('w', strtotime($item['work_date']))] ?></small></div><span class="request-status"><?= $statusLabel ?></span></div><?php endforeach; ?>
        </div><?php endif; ?>
      </article>
    </section>

  </main>
  <footer><span class="footer-brand"><img src="assets/mix-fiscal-logo.svg" alt="Mix Fiscal"><b>HÍBRIDO</b></span><span>Controle simples. Trabalho flexível.</span></footer>
  <script src="assets/app.js" defer></script>
</body>
</html>
