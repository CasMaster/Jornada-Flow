<?php
declare(strict_types=1);

const APP_NAME = 'HÍBRIDO';
const DB_FILE = __DIR__ . '/data/home-office.sqlite';
const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

date_default_timezone_set(getenv('HIBRIDO_TIMEZONE') ?: DEFAULT_TIMEZONE);

function manager_pin(): string
{
    return getenv('HIBRIDO_MANAGER_PIN') ?: '1234';
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;

    if (!is_dir(dirname(DB_FILE))) mkdir(dirname(DB_FILE), 0750, true);
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        team TEXT NOT NULL DEFAULT "",
        work_date TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(email, work_date)
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        team TEXT NOT NULL DEFAULT "",
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT "employee",
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS records_email_idx ON records(email)');
    return $pdo;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function local_datetime(string $utcDateTime, string $format = 'd/m/Y H:i'): string
{
    $date = new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format($format);
}
