<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config.php';

$target = $argv[1] ?? '';
if ($target === '') {
    fwrite(STDERR, "Uso: php scripts/backup-data.php /caminho/backup.sqlite\n");
    exit(1);
}

$directory = dirname($target);
if (!is_dir($directory) || !is_writable($directory)) {
    fwrite(STDERR, "Diretório de destino inexistente ou sem permissão de escrita.\n");
    exit(1);
}

if (file_exists($target)) {
    fwrite(STDERR, "O arquivo de destino já existe. Escolha outro nome.\n");
    exit(1);
}

db()->exec('VACUUM INTO ' . db()->quote($target));
$size = filesize($target);
$hash = hash_file('sha256', $target);
fwrite(STDOUT, "Backup criado: {$target}\nTamanho: {$size} bytes\nSHA256: {$hash}\n");

