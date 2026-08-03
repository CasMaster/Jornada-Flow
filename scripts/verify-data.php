<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$source = $argv[1] ?? '';
if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Uso: php scripts/verify-data.php /caminho/backup.sqlite\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $source, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$integrity = (string)$pdo->query('PRAGMA integrity_check')->fetchColumn();
if ($integrity !== 'ok') {
    fwrite(STDERR, "Falha de integridade: {$integrity}\n");
    exit(2);
}

fwrite(STDOUT, "Integridade: ok\n");
foreach (['users', 'teams', 'records'] as $table) {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    fwrite(STDOUT, ucfirst($table) . ": {$count}\n");
}

