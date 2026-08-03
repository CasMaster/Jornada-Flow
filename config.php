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
        status TEXT NOT NULL DEFAULT "pending",
        reviewed_at TEXT,
        reviewed_by INTEGER,
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
    $pdo->exec('CREATE TABLE IF NOT EXISTS teams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS manager_teams (
        manager_id INTEGER NOT NULL,
        team_id INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (manager_id, team_id),
        FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )');
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('active', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN active INTEGER NOT NULL DEFAULT 1');
    }
    $recordColumns = $pdo->query('PRAGMA table_info(records)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('status', $recordColumns, true)) {
        $pdo->exec('ALTER TABLE records ADD COLUMN status TEXT NOT NULL DEFAULT "approved"');
    }
    if (!in_array('reviewed_at', $recordColumns, true)) $pdo->exec('ALTER TABLE records ADD COLUMN reviewed_at TEXT');
    if (!in_array('reviewed_by', $recordColumns, true)) $pdo->exec('ALTER TABLE records ADD COLUMN reviewed_by INTEGER');
    $pdo->exec("INSERT OR IGNORE INTO teams (name) SELECT DISTINCT team FROM users WHERE team <> ''");
    $managerCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('manager', 'super_admin')")->fetchColumn();
    if ($managerCount === 0) {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO users (name, email, team, password_hash, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(['Gestor principal', 'gestor@local', '', password_hash(manager_pin(), PASSWORD_DEFAULT), 'super_admin']);
    }
    $pdo->exec("UPDATE users SET role = 'super_admin' WHERE email = 'gestor@local' AND role = 'manager'");
    $pdo->exec("INSERT OR IGNORE INTO manager_teams (manager_id, team_id)
        SELECT users.id, teams.id FROM users JOIN teams ON teams.name = users.team
        WHERE users.role = 'manager' AND users.team <> ''");
    $pdo->exec('CREATE INDEX IF NOT EXISTS records_email_idx ON records(email)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS records_status_idx ON records(status)');
    return $pdo;
}

function reporting_period(?DateTimeImmutable $today = null): array
{
    $today ??= new DateTimeImmutable('today');
    $start = (int)$today->format('d') >= 20
        ? $today->setDate((int)$today->format('Y'), (int)$today->format('m'), 20)
        : $today->modify('first day of previous month')->setDate((int)$today->modify('first day of previous month')->format('Y'), (int)$today->modify('first day of previous month')->format('m'), 20);
    return [$start, $start->modify('+1 month')->modify('-1 day')];
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
