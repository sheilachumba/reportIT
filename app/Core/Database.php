<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private PDO $pdo;
    private string $driver;

    public function __construct(array $dbConfig)
    {
        $this->driver = (string) ($dbConfig['driver'] ?? 'sqlite');

        if ($this->driver === 'sqlite') {
            $path = (string) ($dbConfig['sqlite_path'] ?? '');
            if ($path === '') {
                throw new \RuntimeException('Missing sqlite_path in config.');
            }

            $dir = \dirname($path);
            if (!\is_dir($dir)) {
                \mkdir($dir, 0777, true);
            }

            if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                throw new \RuntimeException('PDO SQLite driver is not enabled for this PHP runtime. Enable the sqlite extensions in your php.ini (pdo_sqlite and sqlite3) and restart the PHP built-in server.');
            }

            try {
                $this->pdo = new PDO('sqlite:' . $path);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            } catch (PDOException $e) {
                throw new \RuntimeException('DB connection failed: ' . $e->getMessage());
            }

            return;
        }

        if ($this->driver === 'mysql') {
            if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
                throw new \RuntimeException('PDO MySQL driver is not enabled for this PHP runtime. Enable/install the pdo_mysql extension and restart the PHP built-in server.');
            }

            $host = (string) ($dbConfig['host'] ?? '127.0.0.1');
            $port = (int) ($dbConfig['port'] ?? 3306);
            $database = (string) ($dbConfig['database'] ?? '');
            $username = (string) ($dbConfig['username'] ?? '');
            $password = (string) ($dbConfig['password'] ?? '');
            $charset = (string) ($dbConfig['charset'] ?? 'utf8mb4');

            if ($database === '') {
                throw new \RuntimeException('Missing database name in config.');
            }

            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;

            try {
                $this->pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                throw new \RuntimeException('DB connection failed: ' . $e->getMessage());
            }

            return;
        }

        throw new \RuntimeException('Unsupported db driver: ' . $this->driver);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function migrate(): void
    {
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS campuses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            )');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                campus_id INTEGER,
                password_hash TEXT NOT NULL,
                email_verified_at TEXT,
                twofa_enabled INTEGER NOT NULL DEFAULT 1,
                twofa_enabled_at TEXT,
                created_at TEXT NOT NULL
            )');

            $this->ensureUsersCampusIdColumn();
            $this->ensureUsersEmailVerifiedAtColumn();
            $this->ensureUsersTwofaEnabledColumn();
            $this->ensureUsersTwofaEnabledAtColumn();

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS auth_email_verifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                consumed_at TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS auth_2fa_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                code_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS buildings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campus_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                FOREIGN KEY(campus_id) REFERENCES campuses(id) ON DELETE CASCADE
            )');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                building_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                FOREIGN KEY(building_id) REFERENCES buildings(id) ON DELETE CASCADE
            )');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                device_type TEXT NOT NULL,
                asset_tag TEXT,
                label TEXT,
                FOREIGN KEY(room_id) REFERENCES rooms(id) ON DELETE CASCADE
            )');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_number TEXT NOT NULL UNIQUE,
                reporter_name TEXT,
                reporter_email TEXT,
                campus_id INTEGER NOT NULL,
                building_id INTEGER NOT NULL,
                room_id INTEGER NOT NULL,
                device_id INTEGER NOT NULL,
                device_condition TEXT NOT NULL,
                severity TEXT NOT NULL,
                description TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "Open",
                assigned_to_name TEXT,
                assigned_to_email TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT,
                resolved_at TEXT,
                FOREIGN KEY(campus_id) REFERENCES campuses(id),
                FOREIGN KEY(building_id) REFERENCES buildings(id),
                FOREIGN KEY(room_id) REFERENCES rooms(id),
                FOREIGN KEY(device_id) REFERENCES devices(id)
            )');

            $this->ensureTicketsColumns();

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS ticket_attachments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_id INTEGER NOT NULL,
                original_name TEXT NOT NULL,
                stored_path TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                bytes INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            )');

            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_buildings_campus_id ON buildings(campus_id)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_rooms_building_id ON rooms(building_id)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_devices_room_id ON devices(room_id)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON tickets(created_at)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_email_verifications_user_id ON auth_email_verifications(user_id)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_2fa_codes_user_id ON auth_2fa_codes(user_id)');
            return;
        }

        if ($this->driver === 'mysql') {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                email VARCHAR(190) NOT NULL,
                campus_id INT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email_verified_at DATETIME NULL,
                twofa_enabled TINYINT(1) NOT NULL DEFAULT 1,
                twofa_enabled_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_users_email (email),
                KEY idx_users_campus_id (campus_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $this->ensureUsersCampusIdColumn();
            $this->ensureUsersEmailVerifiedAtColumn();
            $this->ensureUsersTwofaEnabledColumn();
            $this->ensureUsersTwofaEnabledAtColumn();

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS auth_email_verifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_auth_email_verifications_user_id (user_id),
                CONSTRAINT fk_auth_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS auth_2fa_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_auth_2fa_codes_user_id (user_id),
                CONSTRAINT fk_auth_2fa_codes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS campuses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                UNIQUE KEY uq_campuses_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS buildings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                campus_id INT NOT NULL,
                name VARCHAR(190) NOT NULL,
                KEY idx_buildings_campus_id (campus_id),
                CONSTRAINT fk_buildings_campus FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS rooms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                building_id INT NOT NULL,
                name VARCHAR(190) NOT NULL,
                KEY idx_rooms_building_id (building_id),
                CONSTRAINT fk_rooms_building FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS devices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_id INT NOT NULL,
                device_type VARCHAR(60) NOT NULL,
                asset_tag VARCHAR(100) NULL,
                label VARCHAR(190) NULL,
                KEY idx_devices_room_id (room_id),
                CONSTRAINT fk_devices_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_number VARCHAR(40) NOT NULL,
                reporter_name VARCHAR(190) NULL,
                reporter_email VARCHAR(190) NULL,
                campus_id INT NOT NULL,
                building_id INT NOT NULL,
                room_id INT NOT NULL,
                device_id INT NOT NULL,
                device_condition VARCHAR(20) NOT NULL,
                severity VARCHAR(20) NOT NULL,
                description TEXT NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT "Open",
                created_at DATETIME NOT NULL,
                assigned_to_name VARCHAR(190) NULL,
                assigned_to_email VARCHAR(190) NULL,
                updated_at DATETIME NULL,
                resolved_at DATETIME NULL,
                UNIQUE KEY uq_tickets_ticket_number (ticket_number),
                KEY idx_tickets_created_at (created_at),
                KEY idx_tickets_campus_id (campus_id),
                KEY idx_tickets_building_id (building_id),
                KEY idx_tickets_room_id (room_id),
                KEY idx_tickets_device_id (device_id),
                CONSTRAINT fk_tickets_campus FOREIGN KEY (campus_id) REFERENCES campuses(id),
                CONSTRAINT fk_tickets_building FOREIGN KEY (building_id) REFERENCES buildings(id),
                CONSTRAINT fk_tickets_room FOREIGN KEY (room_id) REFERENCES rooms(id),
                CONSTRAINT fk_tickets_device FOREIGN KEY (device_id) REFERENCES devices(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $this->ensureTicketsColumns();

            $this->pdo->exec('CREATE TABLE IF NOT EXISTS ticket_attachments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                bytes INT NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_ticket_attachments_ticket_id (ticket_id),
                CONSTRAINT fk_ticket_attachments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            return;
        }
    }

    private function ensureUsersEmailVerifiedAtColumn(): void
    {
        try {
            $this->pdo->query('SELECT email_verified_at FROM users LIMIT 1');
            return;
        } catch (\Throwable $e) {
        }

        if ($this->driver === 'sqlite') {
            try {
                $this->pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at TEXT');
            } catch (\Throwable $e) {
            }
            return;
        }

        if ($this->driver === 'mysql') {
            try {
                $this->pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL');
            } catch (\Throwable $e) {
            }
        }
    }

    private function ensureUsersTwofaEnabledColumn(): void
    {
        try {
            $this->pdo->query('SELECT twofa_enabled FROM users LIMIT 1');
            return;
        } catch (\Throwable $e) {
        }

        if ($this->driver === 'sqlite') {
            try {
                $this->pdo->exec('ALTER TABLE users ADD COLUMN twofa_enabled INTEGER NOT NULL DEFAULT 1');
            } catch (\Throwable $e) {
            }
            return;
        }

        if ($this->driver === 'mysql') {
            try {
                $this->pdo->exec('ALTER TABLE users ADD COLUMN twofa_enabled TINYINT(1) NOT NULL DEFAULT 1');
            } catch (\Throwable $e) {
            }
        }
    }

    private function ensureUsersTwofaEnabledAtColumn(): void
    {
        try {
            $this->pdo->query('SELECT twofa_enabled_at FROM users LIMIT 1');
            return;
        } catch (\Throwable $e) {
        }

        if ($this->driver === 'sqlite') {
            try {
                $this->pdo->exec('ALTER TABLE users ADD COLUMN twofa_enabled_at TEXT');
            } catch (\Throwable $e) {
            }
            return;
        }

        if ($this->driver === 'mysql') {
            try {
                $this->pdo->exec('ALTER TABLE users ADD COLUMN twofa_enabled_at DATETIME NULL');
            } catch (\Throwable $e) {
            }
        }
    }

    public function seedIfEmpty(): void
    {
        $usersCount = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($usersCount === 0) {
            $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash, email_verified_at, twofa_enabled, twofa_enabled_at, created_at)
                VALUES (:name, :email, :password_hash, :email_verified_at, :twofa_enabled, :twofa_enabled_at, :created_at)');
            $stmt->execute([
                ':name' => 'Admin',
                ':email' => 'admin@ksg.ac.ke',
                ':password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                ':email_verified_at' => gmdate('Y-m-d H:i:s'),
                ':twofa_enabled' => 1,
                ':twofa_enabled_at' => gmdate('Y-m-d H:i:s'),
                ':created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } else {
            try {
                $now = gmdate('Y-m-d H:i:s');
                $this->pdo->prepare('UPDATE users SET email_verified_at = :v WHERE email = :e AND (email_verified_at IS NULL OR email_verified_at = "")')
                    ->execute([':v' => $now, ':e' => 'admin@ksg.ac.ke']);

                $this->pdo->prepare('UPDATE users SET twofa_enabled = 1 WHERE email = :e AND (twofa_enabled IS NULL OR twofa_enabled = 0)')
                    ->execute([':e' => 'admin@ksg.ac.ke']);
                $this->pdo->prepare('UPDATE users SET twofa_enabled_at = :v WHERE email = :e AND (twofa_enabled_at IS NULL OR twofa_enabled_at = "")')
                    ->execute([':v' => $now, ':e' => 'admin@ksg.ac.ke']);
            } catch (\Throwable $e) {
            }
        }

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM campuses')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $this->pdo->beginTransaction();

        $campuses = ['HQ', 'Nairobi', 'Embu', 'Matuga', 'Mombasa', 'Baringo'];
        $stmtCampus = $this->pdo->prepare('INSERT INTO campuses (name) VALUES (:name)');
        foreach ($campuses as $name) {
            $stmtCampus->execute([':name' => $name]);
        }

        $campusIds = $this->pdo->query('SELECT id, name FROM campuses')->fetchAll(PDO::FETCH_KEY_PAIR);

        $stmtBuilding = $this->pdo->prepare('INSERT INTO buildings (campus_id, name) VALUES (:campus_id, :name)');
        $stmtRoom = $this->pdo->prepare('INSERT INTO rooms (building_id, name) VALUES (:building_id, :name)');
        $stmtDevice = $this->pdo->prepare('INSERT INTO devices (room_id, device_type, asset_tag, label) VALUES (:room_id, :device_type, :asset_tag, :label)');

        foreach ($campusIds as $id => $campusName) {
            $buildings = ['Admin Block', 'Library', 'Engineering'];
            foreach ($buildings as $b) {
                $stmtBuilding->execute([':campus_id' => (int) $id, ':name' => $b]);
                $buildingId = (int) $this->pdo->lastInsertId();

                $rooms = ['Room 101', 'Room 102', 'Lab A'];
                foreach ($rooms as $r) {
                    $stmtRoom->execute([':building_id' => $buildingId, ':name' => $r]);
                    $roomId = (int) $this->pdo->lastInsertId();

                    $devices = [
                        ['Desktop', 'DT-' . $id . '-' . $buildingId . '-' . $roomId, 'Desktop Workstation'],
                        ['Projector', 'PJ-' . $id . '-' . $buildingId . '-' . $roomId, 'Ceiling Projector'],
                        ['Speaker', 'SP-' . $id . '-' . $buildingId . '-' . $roomId, 'Front Speaker'],
                        ['Microphone', 'MC-' . $id . '-' . $buildingId . '-' . $roomId, 'Lectern Mic'],
                        ['Laptop', 'LT-' . $id . '-' . $buildingId . '-' . $roomId, 'Loan Laptop'],
                    ];

                    foreach ($devices as [$type, $tag, $label]) {
                        $stmtDevice->execute([
                            ':room_id' => $roomId,
                            ':device_type' => $type,
                            ':asset_tag' => $tag,
                            ':label' => $label,
                        ]);
                    }
                }
            }
        }

        $this->pdo->commit();
    }

    private function ensureUsersCampusIdColumn(): void
    {
        try {
            $this->pdo->query('SELECT campus_id FROM users LIMIT 1');
            return;
        } catch (\Throwable $e) {
        }

        if ($this->driver === 'sqlite') {
            try {
                $this->pdo->exec('ALTER TABLE users ADD COLUMN campus_id INTEGER');
            } catch (\Throwable $e) {
            }
            return;
        }

        if ($this->driver === 'mysql') {
            try {
                $this->pdo->exec('ALTER TABLE users ADD COLUMN campus_id INT NULL');
            } catch (\Throwable $e) {
            }

            try {
                $this->pdo->exec('CREATE INDEX idx_users_campus_id ON users(campus_id)');
            } catch (\Throwable $e) {
            }
        }
    }

    private function ensureTicketsColumns(): void
    {
        $columns = [
            'assigned_to_name' => $this->driver === 'mysql' ? 'VARCHAR(190) NULL' : 'TEXT',
            'assigned_to_email' => $this->driver === 'mysql' ? 'VARCHAR(190) NULL' : 'TEXT',
            'updated_at' => $this->driver === 'mysql' ? 'DATETIME NULL' : 'TEXT',
            'resolved_at' => $this->driver === 'mysql' ? 'DATETIME NULL' : 'TEXT',
        ];

        foreach ($columns as $col => $type) {
            try {
                $this->pdo->query('SELECT ' . $col . ' FROM tickets LIMIT 1');
                continue;
            } catch (\Throwable $e) {
            }

            try {
                $this->pdo->exec('ALTER TABLE tickets ADD COLUMN ' . $col . ' ' . $type);
            } catch (\Throwable $e) {
            }
        }
    }
}
