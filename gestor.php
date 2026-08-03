<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
if (empty($_SESSION['manager']) || !is_array($_SESSION['manager'])) { header('Location: login.php?perfil=manager'); exit; }
$manager = $_SESSION['manager'];
$isSuperAdmin = ($manager['role'] ?? '') === 'super_admin';
$allowedTeamRows = $isSuperAdmin
    ? db()->query('SELECT id, name, active FROM teams ORDER BY active DESC, name')->fetchAll()
    : (function () use ($manager): array { $stmt = db()->prepare('SELECT teams.id, teams.name, teams.active FROM teams JOIN manager_teams ON manager_teams.team_id = teams.id WHERE manager_teams.manager_id = ? ORDER BY teams.name'); $stmt->execute([(int)$manager['id']]); return $stmt->fetchAll(); })();
$allowedTeamNames = array_column($allowedTeamRows, 'name');
$message = '';
$error = '';

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function redirect_manager(string $section = ''): never { header('Location: gestor.php' . ($section ? '#' . $section : '')); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $error = 'Sessão expirada. Atualize a página.';
    else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'logout') { $_SESSION = []; session_regenerate_id(true); header('Location: login.php?perfil=manager'); exit; }
            if (in_array($action, ['add_team', 'toggle_team', 'save_user', 'toggle_user'], true) && !$isSuperAdmin) throw new RuntimeException('Apenas o Super Admin pode administrar equipes e usuários.');
            if ($action === 'review_record') {
                $decision = ($_POST['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';
                $recordId = (int)$_POST['id'];
                $scopeSql = $isSuperAdmin ? '' : ' AND team IN (' . implode(',', array_fill(0, count($allowedTeamNames), '?')) . ')';
                if (!$isSuperAdmin && !$allowedTeamNames) throw new RuntimeException('Este gestor ainda não possui equipes vinculadas.');
                $reviewParams = [$decision, (int)$manager['id'], $recordId, ...$allowedTeamNames];
                db()->prepare("UPDATE records SET status = ?, reviewed_at = datetime('now'), reviewed_by = ? WHERE id = ?{$scopeSql}")->execute($reviewParams); redirect_manager('registros');
            }
            if ($action === 'add_team') {
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '') throw new RuntimeException('Informe o nome da equipe.');
                db()->prepare('INSERT INTO teams (name) VALUES (?)')->execute([$name]); redirect_manager('equipes');
            }
            if ($action === 'toggle_team') {
                db()->prepare('UPDATE teams SET active = CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([(int)$_POST['id']]); redirect_manager('equipes');
            }
            if ($action === 'save_user') {
                $id = (int)($_POST['id'] ?? 0); $name = trim((string)($_POST['name'] ?? ''));
                $email = strtolower(trim((string)($_POST['email'] ?? ''))); $team = trim((string)($_POST['team'] ?? ''));
                $role = in_array((string)($_POST['role'] ?? ''), ['employee','manager','super_admin'], true) ? (string)$_POST['role'] : 'employee'; $password = (string)($_POST['password'] ?? '');
                $managerTeamIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['manager_teams'] ?? [])), fn($id) => $id > 0)));
                $validTeamIds = array_map('intval', db()->query('SELECT id FROM teams WHERE active = 1')->fetchAll(PDO::FETCH_COLUMN));
                $managerTeamIds = array_values(array_intersect($managerTeamIds, $validTeamIds));
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe nome e e-mail válidos.');
                if ($role === 'employee' && $team === '') throw new RuntimeException('Selecione a equipe do colaborador.');
                if ($role === 'manager' && !$managerTeamIds) throw new RuntimeException('Vincule pelo menos uma equipe ao gestor.');
                if ($id === (int)$manager['id'] && $role !== 'super_admin') throw new RuntimeException('Você não pode remover sua própria permissão de Super Admin.');
                db()->beginTransaction();
                if ($id > 0) {
                    db()->prepare('UPDATE users SET name = ?, email = ?, team = ?, role = ? WHERE id = ?')->execute([$name, $email, $role === 'employee' ? $team : '', $role, $id]);
                    if ($password !== '') { if (strlen($password) < 8) throw new RuntimeException('A nova senha deve ter 8 caracteres.'); db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]); }
                } else {
                    if (strlen($password) < 8) throw new RuntimeException('A senha inicial deve ter 8 caracteres.');
                    db()->prepare('INSERT INTO users (name, email, team, password_hash, role) VALUES (?, ?, ?, ?, ?)')->execute([$name, $email, $role === 'employee' ? $team : '', password_hash($password, PASSWORD_DEFAULT), $role]);
                    $id = (int)db()->lastInsertId();
                }
                db()->prepare('DELETE FROM manager_teams WHERE manager_id = ?')->execute([$id]);
                if ($role === 'manager') { $link = db()->prepare('INSERT OR IGNORE INTO manager_teams (manager_id, team_id) VALUES (?, ?)'); foreach ($managerTeamIds as $teamId) $link->execute([$id, $teamId]); }
                db()->commit();
                redirect_manager('usuarios');
            }
            if ($action === 'toggle_user') {
                $id = (int)($_POST['id'] ?? 0); if ($id === (int)$manager['id']) throw new RuntimeException('Você não pode desativar sua própria conta.');
                db()->prepare('UPDATE users SET active = CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$id]); redirect_manager('usuarios');
            }
        } catch (Throwable $exception) {
            if (db()->inTransaction()) db()->rollBack();
            $error = str_contains($exception->getMessage(), 'UNIQUE') ? 'Este nome ou e-mail já está cadastrado.' : $exception->getMessage();
        }
    }
}

[$defaultStart, $defaultEnd] = reporting_period();
$cycleValue = preg_match('/^\d{4}-\d{2}-20$/', (string)($_GET['cycle'] ?? '')) ? (string)$_GET['cycle'] : $defaultStart->format('Y-m-d');
$cycleStart = new DateTimeImmutable($cycleValue);
$start = $cycleStart->format('Y-m-d');
$end = $cycleStart->modify('+1 month')->modify('-1 day')->format('Y-m-d');
$cycleOptions = [];
for ($offset = 2; $offset >= -12; $offset--) {
    $optionStart = $defaultStart->modify(($offset >= 0 ? '+' : '') . $offset . ' months');
    $optionEnd = $optionStart->modify('+1 month')->modify('-1 day');
    $cycleOptions[] = ['value' => $optionStart->format('Y-m-d'), 'label' => $optionStart->format('d/m/Y') . ' — ' . $optionEnd->format('d/m/Y')];
}
$team = trim((string)($_GET['team'] ?? ''));
if ($team !== '' && !in_array($team, $allowedTeamNames, true)) $team = '';
$selectedEmployees = array_values(array_unique(array_filter(array_map(fn($value) => strtolower(trim((string)$value)), (array)($_GET['employees'] ?? [])), fn($value) => filter_var($value, FILTER_VALIDATE_EMAIL))));
$status = in_array((string)($_GET['status'] ?? ''), ['pending','approved','rejected'], true) ? (string)$_GET['status'] : '';
$teams = $allowedTeamRows;
$scopePlaceholders = implode(',', array_fill(0, count($allowedTeamNames), '?'));
$employeeSql = "SELECT id, name, email, team, role, active, created_at FROM users WHERE role = 'employee' AND active = 1" . ($isSuperAdmin ? '' : ($allowedTeamNames ? " AND team IN ({$scopePlaceholders})" : ' AND 1 = 0')) . ' ORDER BY name';
$employeeStmt = db()->prepare($employeeSql); $employeeStmt->execute($isSuperAdmin ? [] : $allowedTeamNames); $employeeOptions = $employeeStmt->fetchAll();
$users = $isSuperAdmin ? db()->query("SELECT users.id, users.name, users.email, users.team, users.role, users.active, users.created_at, GROUP_CONCAT(teams.name, ', ') AS managed_teams FROM users LEFT JOIN manager_teams ON manager_teams.manager_id = users.id LEFT JOIN teams ON teams.id = manager_teams.team_id GROUP BY users.id ORDER BY CASE users.role WHEN 'super_admin' THEN 0 WHEN 'manager' THEN 1 ELSE 2 END, users.name")->fetchAll() : $employeeOptions;
$conditions = ['work_date BETWEEN ? AND ?']; $params = [$start, $end];
if (!$isSuperAdmin) { if ($allowedTeamNames) { $conditions[] = 'team IN (' . $scopePlaceholders . ')'; array_push($params, ...$allowedTeamNames); } else $conditions[] = '1 = 0'; }
if ($team !== '') { $conditions[] = 'team = ?'; $params[] = $team; }
if ($selectedEmployees) { $conditions[] = 'lower(email) IN (' . implode(',', array_fill(0, count($selectedEmployees), '?')) . ')'; array_push($params, ...$selectedEmployees); }
if ($status !== '') { $conditions[] = 'status = ?'; $params[] = $status; }
$stmt = db()->prepare("SELECT id, name, email, team, work_date, created_at, status, reviewed_at FROM records WHERE " . implode(' AND ', $conditions) . " ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END, work_date DESC, name");
$stmt->execute($params); $records = $stmt->fetchAll();
if (isset($_GET['export'])) {
    require __DIR__ . '/vendor/autoload.php';
    $peopleConditions = ["role = 'employee'", 'active = 1']; $peopleParams = [];
    if (!$isSuperAdmin) { if ($allowedTeamNames) { $peopleConditions[] = 'team IN (' . $scopePlaceholders . ')'; array_push($peopleParams, ...$allowedTeamNames); } else $peopleConditions[] = '1 = 0'; }
    if ($team !== '') { $peopleConditions[] = 'team = ?'; $peopleParams[] = $team; }
    if ($selectedEmployees) { $peopleConditions[] = 'lower(email) IN (' . implode(',', array_fill(0, count($selectedEmployees), '?')) . ')'; array_push($peopleParams, ...$selectedEmployees); }
    $peopleStmt = db()->prepare('SELECT name, email, team FROM users WHERE ' . implode(' AND ', $peopleConditions) . ' ORDER BY name');
    $peopleStmt->execute($peopleParams); $exportPeople = $peopleStmt->fetchAll();
    $approvedConditions = ["work_date BETWEEN ? AND ?", "status = 'approved'"]; $approvedParams = [$start, $end];
    if (!$isSuperAdmin) { if ($allowedTeamNames) { $approvedConditions[] = 'team IN (' . $scopePlaceholders . ')'; array_push($approvedParams, ...$allowedTeamNames); } else $approvedConditions[] = '1 = 0'; }
    if ($team !== '') { $approvedConditions[] = 'team = ?'; $approvedParams[] = $team; }
    if ($selectedEmployees) { $approvedConditions[] = 'lower(email) IN (' . implode(',', array_fill(0, count($selectedEmployees), '?')) . ')'; array_push($approvedParams, ...$selectedEmployees); }
    $approvedStmt = db()->prepare('SELECT email, work_date FROM records WHERE ' . implode(' AND ', $approvedConditions)); $approvedStmt->execute($approvedParams);
    $recordMap = [];
    foreach ($approvedStmt->fetchAll() as $row) $recordMap[strtolower($row['email'])][$row['work_date']] = true;
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); $sheet = $spreadsheet->getActiveSheet(); $sheet->setTitle('Home Office'); $sheet->setShowGridlines(false);
    $dates = []; for ($day = new DateTimeImmutable($start); $day <= new DateTimeImmutable($end); $day = $day->modify('+1 day')) $dates[] = $day;
    $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($dates) + 1);
    $sheet->mergeCells("A1:{$lastColumn}1"); $sheet->setCellValue('A1', 'CONTROLE DE HOME OFFICE — ' . date('d/m/Y', strtotime($start)) . ' A ' . date('d/m/Y', strtotime($end)));
    $sheet->setCellValue('A3', 'Colaborador');
    foreach ($dates as $index => $date) {
        $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 2);
        $sheet->setCellValue($column . '3', \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date));
        $sheet->getStyle($column . '3')->getNumberFormat()->setFormatCode('dd/mm');
        if ((int)$date->format('N') >= 6) $sheet->getStyle($column . '3:' . $column . (max(4, count($exportPeople) + 3)))->getFill()->setFillType('solid')->getStartColor()->setRGB('F2F2F2');
    }
    foreach ($exportPeople as $rowIndex => $person) {
        $excelRow = $rowIndex + 4; $sheet->setCellValue('A' . $excelRow, $person['name']);
        foreach ($dates as $dateIndex => $date) {
            $dateKey = $date->format('Y-m-d'); if (empty($recordMap[strtolower($person['email'])][$dateKey])) continue;
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dateIndex + 2); $cell = $column . $excelRow; $sheet->setCellValue($cell, 'HOME');
            $sheet->getStyle($cell)->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'9C0006']],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>'F4CCCC']],'alignment'=>['horizontal'=>'center','vertical'=>'center']]);
        }
    }
    $lastDataRow = max(4, count($exportPeople) + 3); $noteRow = $lastDataRow + 2;
    $sheet->mergeCells("A{$noteRow}:{$lastColumn}{$noteRow}"); $sheet->setCellValue("A{$noteRow}", 'Ciclo selecionado: ' . date('d/m/Y', strtotime($start)) . ' a ' . date('d/m/Y', strtotime($end)) . '. Exportado em ' . date('d/m/Y H:i') . '.');
    $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray(['font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>'1F4E78']],'alignment'=>['horizontal'=>'center','vertical'=>'center']]);
    $sheet->getStyle("A3:{$lastColumn}3")->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'1F1F1F']],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>'D9EAF7']],'alignment'=>['horizontal'=>'center','vertical'=>'center']]);
    $sheet->getStyle("A4:A{$lastDataRow}")->getFont()->setBold(true); $sheet->getStyle("A3:{$lastColumn}{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle('hair')->getColor()->setRGB('E6E6E6');
    $sheet->getStyle("A{$noteRow}:{$lastColumn}{$noteRow}")->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'7F6000']],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>'FFF2CC']]]);
    $sheet->getColumnDimension('A')->setWidth(28); for ($index = 2; $index <= count($dates) + 1; $index++) $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index))->setWidth(11);
    $sheet->getRowDimension(1)->setRowHeight(28); $sheet->getRowDimension(3)->setRowHeight(22); $sheet->freezePane('B4'); $sheet->setAutoFilter("A3:{$lastColumn}3");
    $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0); $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.25)->setRight(0.25);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="home-office-' . $start . '-a-' . $end . '.xlsx"'); header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output'); exit;
}
$managerTeamMap = [];
if ($isSuperAdmin) foreach (db()->query('SELECT manager_id, team_id FROM manager_teams')->fetchAll() as $link) $managerTeamMap[(int)$link['manager_id']][] = (int)$link['team_id'];
$people = count(array_unique(array_column($records, 'email'))); $pendingCount = count(array_filter($records, fn($row) => $row['status'] === 'pending')); $weekdays = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gestor — Híbrido</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/manager.css"><link rel="stylesheet" href="assets/brand-theme.css"><script src="assets/theme.js"></script></head>
<body class="manager-page"><header class="topbar"><a class="brand" href="gestor.php"><img src="assets/mix-fiscal-mark.png" alt="Mix Fiscal"><span>HÍBRIDO</span></a><nav><span class="user-label"><?= h($manager['name']) ?> · <?= $isSuperAdmin ? 'Super Admin' : 'Gestor' ?></span><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button class="tab active" name="action" value="logout">Sair</button></form></nav></header>
<main><section class="workspace manager-workspace"><div class="manager-head"><div><p class="eyebrow">PAINEL DO GESTOR</p><h1>Gestão do <em>trabalho remoto.</em></h1><p>Período padrão: dia 20 até dia 19 do mês seguinte.</p></div><a class="primary button-link" href="?<?= h(http_build_query(array_merge($_GET, ['export'=>1]))) ?>">Exportar Excel ↓</a></div>
<?php if ($error): ?><p class="login-error manager-alert"><?= h($error) ?></p><?php endif; ?>
<nav class="manager-sections"><a href="#registros">Solicitações</a><?php if ($isSuperAdmin): ?><a href="#equipes">Equipes</a><a href="#usuarios">Usuários</a><?php endif; ?></nav>
<div class="stats stats-three"><div><span>SOLICITAÇÕES NO CICLO</span><strong><?= count($records) ?></strong></div><div><span>PENDENTES</span><strong><?= $pendingCount ?></strong></div><div><span>COLABORADORES</span><strong><?= $people ?></strong></div></div>
<article class="card filters-card" id="registros"><form method="get" class="filters request-filters"><label>Ciclo 20–19<select name="cycle"><?php foreach ($cycleOptions as $option): ?><option value="<?= h($option['value']) ?>" <?= $cycleValue === $option['value'] ? 'selected' : '' ?>><?= h($option['label']) ?></option><?php endforeach; ?></select></label><label>Equipe<select name="team"><option value="">Todas permitidas</option><?php foreach ($teams as $option): if (!(int)$option['active']) continue; ?><option <?= $team === $option['name'] ? 'selected' : '' ?>><?= h($option['name']) ?></option><?php endforeach; ?></select></label><label>Status<select name="status"><option value="">Todos</option><option value="pending" <?= $status==='pending'?'selected':'' ?>>Pendentes</option><option value="approved" <?= $status==='approved'?'selected':'' ?>>Aprovadas</option><option value="rejected" <?= $status==='rejected'?'selected':'' ?>>Recusadas</option></select></label><label>Colaboradores<select name="employees[]" multiple class="multi-filter click-multi" data-placeholder="Selecionar colaboradores"><?php foreach ($employeeOptions as $employeeOption): ?><option value="<?= h($employeeOption['email']) ?>" <?= in_array(strtolower($employeeOption['email']), $selectedEmployees, true) ? 'selected' : '' ?>><?= h($employeeOption['name']) ?> · <?= h($employeeOption['team']) ?></option><?php endforeach; ?></select></label><button class="primary">Aplicar</button><a class="clear-filter" href="gestor.php">Ciclo atual</a></form></article>
<article class="card report"><div class="report-head"><div><p class="eyebrow">SOLICITAÇÕES</p><h2><?= count($records) ?> resultados</h2></div><span class="status-ok">● <?= date('d/m/Y', strtotime($start)) ?> — <?= date('d/m/Y', strtotime($end)) ?></span></div><div class="table-wrap"><table><thead><tr><th>COLABORADOR</th><th>EQUIPE</th><th>DATA</th><th>STATUS</th><th>ANÁLISE</th></tr></thead><tbody><?php if (!$records): ?><tr><td colspan="5" class="empty">Nenhuma solicitação neste período.</td></tr><?php endif; ?><?php foreach ($records as $row): $label=['pending'=>'Pendente','approved'=>'Aprovada','rejected'=>'Recusada'][$row['status']] ?? 'Pendente'; ?><tr class="request-row request-<?= h($row['status']) ?>"><td><b><?= h($row['name']) ?></b><small><?= h($row['email']) ?></small></td><td><?= h($row['team'] ?: '—') ?></td><td><b><?= date('d/m/Y', strtotime($row['work_date'])) ?></b><small><?= $weekdays[(int)date('w', strtotime($row['work_date']))] ?></small></td><td><span class="request-badge"><?= $label ?></span></td><td class="review-actions"><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="review_record"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="approve-action" name="decision" value="approved" title="Aprovar solicitação">✓ Aprovar</button><button class="reject-action" name="decision" value="rejected" title="Recusar e arquivar" onclick="return confirm('Recusar e arquivar esta solicitação?')">× Recusar</button></form></td></tr><?php endforeach; ?></tbody></table></div></article>
<?php if ($isSuperAdmin): ?><div class="admin-grid">
<article class="card admin-card" id="equipes"><div class="report-head"><div><p class="eyebrow">CADASTRO CENTRAL</p><h2>Equipes</h2></div></div><form method="post" class="inline-create"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input name="name" required placeholder="Nome da nova equipe"><button class="primary" name="action" value="add_team">Adicionar</button></form><div class="compact-list"><?php if (!$teams): ?><p class="empty">Nenhuma equipe cadastrada.</p><?php endif; ?><?php foreach ($teams as $item): ?><div><span><b><?= h($item['name']) ?></b><small><?= (int)$item['active'] ? 'Ativa' : 'Inativa' ?></small></span><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="text-action" name="action" value="toggle_team"><?= (int)$item['active'] ? 'Desativar' : 'Ativar' ?></button></form></div><?php endforeach; ?></div></article>
<article class="card admin-card user-editor" id="usuarios"><div class="report-head"><div><p class="eyebrow">ACESSOS E PERMISSÕES</p><h2>Novo usuário</h2></div></div><form method="post" class="management-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><label>Nome<input name="name" required></label><label>E-mail<input name="email" type="email" required></label><label>Perfil<select name="role"><option value="employee">Colaborador</option><option value="manager">Gestor</option><option value="super_admin">Super Admin</option></select></label><label>Equipe do colaborador<select name="team"><option value="">Sem equipe</option><?php foreach ($teams as $option): if ((int)$option['active']): ?><option><?= h($option['name']) ?></option><?php endif; endforeach; ?></select></label><label>Equipes do gestor<select name="manager_teams[]" multiple class="click-multi" data-placeholder="Selecionar equipes"><?php foreach ($teams as $option): if ((int)$option['active']): ?><option value="<?= (int)$option['id'] ?>"><?= h($option['name']) ?></option><?php endif; endforeach; ?></select></label><label>Senha inicial<input name="password" type="password" minlength="8" required></label><button class="primary" name="action" value="save_user">Criar usuário</button></form></article>
</div>
<article class="card report users-report"><div class="report-head"><div><p class="eyebrow">DIRETÓRIO</p><h2><?= count($users) ?> usuários</h2></div></div><div class="table-wrap"><table><thead><tr><th>USUÁRIO</th><th>PERFIL</th><th>EQUIPE(S)</th><th>STATUS</th><th></th></tr></thead><tbody><?php foreach ($users as $user): $roleLabel=['employee'=>'Colaborador','manager'=>'Gestor','super_admin'=>'Super Admin'][$user['role']] ?? 'Colaborador'; ?><tr><td><b><?= h($user['name']) ?></b><small><?= h($user['email']) ?></small></td><td><?= $roleLabel ?></td><td><?= h($user['role']==='manager' ? ($user['managed_teams'] ?: 'Nenhuma vinculada') : ($user['team'] ?: ($user['role']==='super_admin' ? 'Todas' : '—'))) ?></td><td><span class="<?= (int)$user['active'] ? 'status-ok' : '' ?>"><?= (int)$user['active'] ? 'Ativo' : 'Inativo' ?></span></td><td class="user-actions"><details><summary>Editar</summary><form method="post" class="edit-user-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><input name="name" value="<?= h($user['name']) ?>" required><input name="email" type="email" value="<?= h($user['email']) ?>" required><select name="role"><option value="employee" <?= $user['role']==='employee'?'selected':'' ?>>Colaborador</option><option value="manager" <?= $user['role']==='manager'?'selected':'' ?>>Gestor</option><option value="super_admin" <?= $user['role']==='super_admin'?'selected':'' ?>>Super Admin</option></select><select name="team"><option value="">Equipe do colaborador</option><?php foreach ($teams as $option): ?><option <?= $user['team']===$option['name']?'selected':'' ?>><?= h($option['name']) ?></option><?php endforeach; ?></select><select name="manager_teams[]" multiple class="click-multi" data-placeholder="Selecionar equipes" title="Equipes do gestor"><?php foreach ($teams as $option): ?><option value="<?= (int)$option['id'] ?>" <?= in_array((int)$option['id'], $managerTeamMap[(int)$user['id']] ?? [], true)?'selected':'' ?>><?= h($option['name']) ?></option><?php endforeach; ?></select><input name="password" type="password" placeholder="Nova senha (opcional)"><button class="primary" name="action" value="save_user">Salvar</button><button class="text-action" name="action" value="toggle_user"><?= (int)$user['active']?'Desativar':'Ativar' ?></button></form></details></td></tr><?php endforeach; ?></tbody></table></div></article>
<?php endif; ?></section></main><footer><span class="footer-brand"><img src="assets/mix-fiscal-logo.svg" alt="Mix Fiscal"><b>HÍBRIDO</b></span><span>Controle simples. Trabalho flexível.</span></footer><script src="assets/manager.js" defer></script></body></html>
