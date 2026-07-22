<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
$authenticated = !empty($_SESSION['manager']);
if (!$authenticated) { header('Location: login.php?perfil=manager'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sessão expirada. Atualize a página.';
    } elseif (isset($_POST['login'])) {
        if (hash_equals(manager_pin(), (string)($_POST['pin'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['manager'] = true;
            header('Location: gestor.php'); exit;
        }
        $error = 'PIN inválido. Tente novamente.';
    } elseif (isset($_POST['logout'])) {
        unset($_SESSION['manager']);
        header('Location: gestor.php'); exit;
    } elseif (!empty($_SESSION['manager']) && isset($_POST['delete_id'])) {
        $stmt = db()->prepare('DELETE FROM records WHERE id = ?');
        $stmt->execute([(int)$_POST['delete_id']]);
        header('Location: gestor.php?' . http_build_query($_GET)); exit;
    }
}

$records = [];
$teams = [];
$params = [];
$conditions = [];
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? (string)$_GET['month'] : '';
$team = trim((string)($_GET['team'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

if ($authenticated) {
    $teams = db()->query("SELECT DISTINCT team FROM records WHERE team <> '' ORDER BY team")->fetchAll(PDO::FETCH_COLUMN);
    if ($month !== '') { $conditions[] = 'substr(work_date, 1, 7) = ?'; $params[] = $month; }
    if ($team !== '') { $conditions[] = 'team = ?'; $params[] = $team; }
    if ($search !== '') { $conditions[] = '(name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    $sql = 'SELECT id, name, email, team, work_date, created_at FROM records' . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') . ' ORDER BY work_date DESC, name';
    $stmt = db()->prepare($sql); $stmt->execute($params); $records = $stmt->fetchAll();

    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="home-office-' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Colaborador', 'E-mail', 'Equipe', 'Data', 'Dia da semana'], ';');
        $weekdays = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
        foreach ($records as $row) fputcsv($out, [$row['name'], $row['email'], $row['team'], date('d/m/Y', strtotime($row['work_date'])), $weekdays[(int)date('w', strtotime($row['work_date']))]], ';');
        fclose($out); exit;
    }
}

$people = count(array_unique(array_column($records, 'email')));
$remoteDays = count($records);
$teamCount = count(array_unique(array_filter(array_column($records, 'team'))));
$weekdays = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Gestor — Híbrido</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/manager.css">
</head>
<body class="manager-page">
  <header class="topbar"><a class="brand" href="gestor.php"><span class="mark">H</span> HÍBRIDO<span class="accent">.</span></a><nav><a class="tab" href="login.php?logout=1">Acesso do colaborador</a><?php if ($authenticated): ?><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button class="tab active" name="logout">Sair do painel</button></form><?php endif; ?></nav></header>
  <main>
  <?php if (!$authenticated): ?>
    <section class="login-page">
      <article class="card login-panel">
        <div class="login-brand"><span class="login-icon">↗</span><p class="eyebrow">ÁREA RESTRITA</p></div>
        <h1>Acesso do <em>gestor.</em></h1>
        <p>Entre com o PIN administrativo para consultar, filtrar e exportar os registros da equipe.</p>
        <form method="post" class="login-form">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <label>PIN administrativo<input name="pin" type="password" inputmode="numeric" autocomplete="current-password" autofocus required placeholder="••••"></label>
          <?php if ($error): ?><p class="login-error" role="alert"><?= h($error) ?></p><?php endif; ?>
          <button class="primary" name="login">Entrar no painel <span>→</span></button>
        </form>
        <a class="back-link" href="index.php">← Voltar ao registro</a>
      </article>
      <aside class="login-aside"><p class="eyebrow">GESTÃO SIMPLES</p><h2>Decisões melhores começam com dados organizados.</h2><div class="feature-list"><span>01 <b>Visão consolidada</b></span><span>02 <b>Filtros rápidos</b></span><span>03 <b>Exportação para Excel</b></span></div></aside>
    </section>
  <?php else: ?>
    <section class="workspace manager-workspace">
      <div class="manager-head"><div><p class="eyebrow">PAINEL DO GESTOR</p><h1>Visão do <em>trabalho remoto.</em></h1><p>Acompanhe os registros, encontre inconsistências e exporte o recorte necessário.</p></div><a class="primary button-link" href="?<?= h(http_build_query(array_merge($_GET, ['export' => 1]))) ?>">Exportar CSV ↓</a></div>
      <div class="stats stats-three"><div><span>DIAS REGISTRADOS</span><strong><?= $remoteDays ?></strong></div><div><span>COLABORADORES</span><strong><?= $people ?></strong></div><div><span>EQUIPES</span><strong><?= $teamCount ?></strong></div></div>
      <article class="card filters-card">
        <form method="get" class="filters">
          <label>Mês<input type="month" name="month" value="<?= h($month) ?>"></label>
          <label>Equipe<select name="team"><option value="">Todas</option><?php foreach ($teams as $option): ?><option <?= $team === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></label>
          <label>Colaborador<input name="search" value="<?= h($search) ?>" placeholder="Nome ou e-mail"></label>
          <button class="primary">Aplicar filtros</button><a class="clear-filter" href="gestor.php">Limpar</a>
        </form>
      </article>
      <article class="card report">
        <div class="report-head"><div><p class="eyebrow">REGISTROS CONSOLIDADOS</p><h2><?= $remoteDays ?> <?= $remoteDays === 1 ? 'resultado' : 'resultados' ?></h2></div><span class="status-ok">● Dados atualizados</span></div>
        <div class="table-wrap"><table><thead><tr><th>COLABORADOR</th><th>EQUIPE</th><th>DATA</th><th>DIA</th><th></th></tr></thead><tbody>
        <?php if (!$records): ?><tr><td colspan="5" class="empty">Nenhum registro encontrado para os filtros selecionados.</td></tr><?php endif; ?>
        <?php foreach ($records as $row): ?><tr><td><b><?= h($row['name']) ?></b><small><?= h($row['email']) ?></small></td><td><?= h($row['team'] ?: '—') ?></td><td><?= date('d/m/Y', strtotime($row['work_date'])) ?></td><td><?= $weekdays[(int)date('w', strtotime($row['work_date']))] ?></td><td class="row-action"><form method="post" onsubmit="return confirm('Excluir este registro?')"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button name="delete_id" value="<?= (int)$row['id'] ?>" title="Excluir registro">×</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </article>
    </section>
  <?php endif; ?>
  </main>
  <footer><b>HÍBRIDO.</b><span>Controle simples. Trabalho flexível.</span></footer>
</body></html>
