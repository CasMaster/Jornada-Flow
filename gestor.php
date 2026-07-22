<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
if (empty($_SESSION['manager']) || !is_array($_SESSION['manager'])) { header('Location: login.php?perfil=manager'); exit; }
$manager = $_SESSION['manager'];
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
            if ($action === 'delete_record') { db()->prepare('DELETE FROM records WHERE id = ?')->execute([(int)$_POST['id']]); redirect_manager('registros'); }
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
                $role = ($_POST['role'] ?? '') === 'manager' ? 'manager' : 'employee'; $password = (string)($_POST['password'] ?? '');
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe nome e e-mail válidos.');
                if ($role === 'employee' && $team === '') throw new RuntimeException('Selecione a equipe do colaborador.');
                if ($id > 0) {
                    db()->prepare('UPDATE users SET name = ?, email = ?, team = ?, role = ? WHERE id = ?')->execute([$name, $email, $team, $role, $id]);
                    if ($password !== '') { if (strlen($password) < 8) throw new RuntimeException('A nova senha deve ter 8 caracteres.'); db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]); }
                } else {
                    if (strlen($password) < 8) throw new RuntimeException('A senha inicial deve ter 8 caracteres.');
                    db()->prepare('INSERT INTO users (name, email, team, password_hash, role) VALUES (?, ?, ?, ?, ?)')->execute([$name, $email, $team, password_hash($password, PASSWORD_DEFAULT), $role]);
                }
                redirect_manager('usuarios');
            }
            if ($action === 'toggle_user') {
                $id = (int)($_POST['id'] ?? 0); if ($id === (int)$manager['id']) throw new RuntimeException('Você não pode desativar sua própria conta.');
                db()->prepare('UPDATE users SET active = CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$id]); redirect_manager('usuarios');
            }
        } catch (Throwable $exception) {
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
$team = trim((string)($_GET['team'] ?? '')); $search = trim((string)($_GET['search'] ?? ''));
$teams = db()->query('SELECT id, name, active FROM teams ORDER BY active DESC, name')->fetchAll();
$users = db()->query('SELECT id, name, email, team, role, active, created_at FROM users ORDER BY role DESC, name')->fetchAll();
$conditions = ['work_date BETWEEN ? AND ?']; $params = [$start, $end];
if ($team !== '') { $conditions[] = 'team = ?'; $params[] = $team; }
if ($search !== '') { $conditions[] = '(name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$stmt = db()->prepare('SELECT id, name, email, team, work_date, created_at FROM records WHERE ' . implode(' AND ', $conditions) . ' ORDER BY work_date DESC, name');
$stmt->execute($params); $records = $stmt->fetchAll();
if (isset($_GET['export'])) {
    require __DIR__ . '/vendor/autoload.php';
    $peopleConditions = ["role = 'employee'", 'active = 1']; $peopleParams = [];
    if ($team !== '') { $peopleConditions[] = 'team = ?'; $peopleParams[] = $team; }
    if ($search !== '') { $peopleConditions[] = '(name LIKE ? OR email LIKE ?)'; $peopleParams[] = "%$search%"; $peopleParams[] = "%$search%"; }
    $peopleStmt = db()->prepare('SELECT name, email, team FROM users WHERE ' . implode(' AND ', $peopleConditions) . ' ORDER BY name');
    $peopleStmt->execute($peopleParams); $exportPeople = $peopleStmt->fetchAll();
    $recordMap = [];
    foreach ($records as $row) $recordMap[strtolower($row['email'])][$row['work_date']] = true;
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
$people = count(array_unique(array_column($records, 'email'))); $weekdays = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gestor — Híbrido</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/manager.css"></head>
<body class="manager-page"><header class="topbar"><a class="brand" href="gestor.php"><span class="mark">H</span> HÍBRIDO<span class="accent">.</span></a><nav><span class="user-label"><?= h($manager['name']) ?></span><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button class="tab active" name="action" value="logout">Sair</button></form></nav></header>
<main><section class="workspace manager-workspace"><div class="manager-head"><div><p class="eyebrow">PAINEL DO GESTOR</p><h1>Gestão do <em>trabalho remoto.</em></h1><p>Período padrão: dia 20 até dia 19 do mês seguinte.</p></div><a class="primary button-link" href="?<?= h(http_build_query(array_merge($_GET, ['export'=>1]))) ?>">Exportar Excel ↓</a></div>
<?php if ($error): ?><p class="login-error manager-alert"><?= h($error) ?></p><?php endif; ?>
<nav class="manager-sections"><a href="#registros">Registros</a><a href="#equipes">Equipes</a><a href="#usuarios">Usuários</a></nav>
<div class="stats stats-three"><div><span>DIAS NO PERÍODO</span><strong><?= count($records) ?></strong></div><div><span>COLABORADORES</span><strong><?= $people ?></strong></div><div><span>GESTORES ATIVOS</span><strong><?= count(array_filter($users, fn($u) => $u['role'] === 'manager' && (int)$u['active'] === 1)) ?></strong></div></div>
<article class="card filters-card" id="registros"><form method="get" class="filters"><label>Ciclo 20–19<select name="cycle"><?php foreach ($cycleOptions as $option): ?><option value="<?= h($option['value']) ?>" <?= $cycleValue === $option['value'] ? 'selected' : '' ?>><?= h($option['label']) ?></option><?php endforeach; ?></select></label><label>Equipe<select name="team"><option value="">Todas</option><?php foreach ($teams as $option): if (!(int)$option['active']) continue; ?><option <?= $team === $option['name'] ? 'selected' : '' ?>><?= h($option['name']) ?></option><?php endforeach; ?></select></label><label>Colaborador<input name="search" value="<?= h($search) ?>" placeholder="Nome ou e-mail"></label><button class="primary">Aplicar ciclo</button><a class="clear-filter" href="gestor.php">Ciclo atual</a></form></article>
<article class="card report"><div class="report-head"><div><p class="eyebrow">REGISTROS CONSOLIDADOS</p><h2><?= count($records) ?> resultados</h2></div><span class="status-ok">● <?= date('d/m/Y', strtotime($start)) ?> — <?= date('d/m/Y', strtotime($end)) ?></span></div><div class="table-wrap"><table><thead><tr><th>COLABORADOR</th><th>EQUIPE</th><th>DATA</th><th>DIA</th><th></th></tr></thead><tbody><?php if (!$records): ?><tr><td colspan="5" class="empty">Nenhum registro neste período.</td></tr><?php endif; ?><?php foreach ($records as $row): ?><tr><td><b><?= h($row['name']) ?></b><small><?= h($row['email']) ?></small></td><td><?= h($row['team'] ?: '—') ?></td><td><?= date('d/m/Y', strtotime($row['work_date'])) ?></td><td><?= $weekdays[(int)date('w', strtotime($row['work_date']))] ?></td><td class="row-action"><form method="post" onsubmit="return confirm('Excluir este registro?')"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="delete_record"><button name="id" value="<?= (int)$row['id'] ?>">×</button></form></td></tr><?php endforeach; ?></tbody></table></div></article>
<div class="admin-grid">
<article class="card admin-card" id="equipes"><div class="report-head"><div><p class="eyebrow">CADASTRO CENTRAL</p><h2>Equipes</h2></div></div><form method="post" class="inline-create"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input name="name" required placeholder="Nome da nova equipe"><button class="primary" name="action" value="add_team">Adicionar</button></form><div class="compact-list"><?php if (!$teams): ?><p class="empty">Nenhuma equipe cadastrada.</p><?php endif; ?><?php foreach ($teams as $item): ?><div><span><b><?= h($item['name']) ?></b><small><?= (int)$item['active'] ? 'Ativa' : 'Inativa' ?></small></span><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="text-action" name="action" value="toggle_team"><?= (int)$item['active'] ? 'Desativar' : 'Ativar' ?></button></form></div><?php endforeach; ?></div></article>
<article class="card admin-card user-editor" id="usuarios"><div class="report-head"><div><p class="eyebrow">ACESSOS E PERMISSÕES</p><h2>Novo usuário</h2></div></div><form method="post" class="management-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><label>Nome<input name="name" required></label><label>E-mail<input name="email" type="email" required></label><label>Perfil<select name="role"><option value="employee">Colaborador</option><option value="manager">Gestor</option></select></label><label>Equipe<select name="team"><option value="">Sem equipe</option><?php foreach ($teams as $option): if ((int)$option['active']): ?><option><?= h($option['name']) ?></option><?php endif; endforeach; ?></select></label><label>Senha inicial<input name="password" type="password" minlength="8" required></label><button class="primary" name="action" value="save_user">Criar usuário</button></form></article>
</div>
<article class="card report users-report"><div class="report-head"><div><p class="eyebrow">DIRETÓRIO</p><h2><?= count($users) ?> usuários</h2></div></div><div class="table-wrap"><table><thead><tr><th>USUÁRIO</th><th>PERFIL</th><th>EQUIPE</th><th>STATUS</th><th></th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><b><?= h($user['name']) ?></b><small><?= h($user['email']) ?></small></td><td><?= $user['role'] === 'manager' ? 'Gestor' : 'Colaborador' ?></td><td><?= h($user['team'] ?: '—') ?></td><td><span class="<?= (int)$user['active'] ? 'status-ok' : '' ?>"><?= (int)$user['active'] ? 'Ativo' : 'Inativo' ?></span></td><td class="user-actions"><details><summary>Editar</summary><form method="post" class="edit-user-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><input name="name" value="<?= h($user['name']) ?>" required><input name="email" type="email" value="<?= h($user['email']) ?>" required><select name="role"><option value="employee" <?= $user['role']==='employee'?'selected':'' ?>>Colaborador</option><option value="manager" <?= $user['role']==='manager'?'selected':'' ?>>Gestor</option></select><select name="team"><option value="">Sem equipe</option><?php foreach ($teams as $option): ?><option <?= $user['team']===$option['name']?'selected':'' ?>><?= h($option['name']) ?></option><?php endforeach; ?></select><input name="password" type="password" placeholder="Nova senha (opcional)"><button class="primary" name="action" value="save_user">Salvar</button><button class="text-action" name="action" value="toggle_user"><?= (int)$user['active']?'Desativar':'Ativar' ?></button></form></details></td></tr><?php endforeach; ?></tbody></table></div></article>
</section></main><footer><b>HÍBRIDO.</b><span>Controle simples. Trabalho flexível.</span></footer></body></html>
