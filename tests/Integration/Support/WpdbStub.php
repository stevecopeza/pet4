<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Support;

/**
 * Lightweight wpdb-compatible stub backed by PDO/SQLite.
 *
 * Implements only the surface area used by SqlEscalationRepository,
 * migration definitions, and MigrationRunner so integration tests
 * can run without a full WordPress/MySQL environment.
 */
class WpdbStub
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public string $last_error = '';
    public int $num_rows = 0;

    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    // ── DDL helpers ─────────────────────────────────────────────

    public function get_charset_collate(): string
    {
        return ''; // not needed for SQLite
    }

    /**
     * Execute a raw SQL statement (DDL or DML).
     */
    public function query(string $sql)
    {
        $this->last_error = '';
        // Translate MySQL-specific DDL to SQLite-compatible equivalents
        $sql = $this->translateSql($sql);

        try {
            $result = $this->pdo->exec($sql);
            return $result !== false ? $result : true;
        } catch (\PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    // ── Read helpers ────────────────────────────────────────────

    public function get_row(string $sql, string $output = 'OBJECT', int $offset = 0)
    {
        $this->last_error = '';
        $sql = $this->translateSql($sql);

        try {
            $fetchMode = \PDO::FETCH_OBJ;
            if ($output === 'ARRAY_A') {
                $fetchMode = \PDO::FETCH_ASSOC;
            } elseif ($output === 'ARRAY_N') {
                $fetchMode = \PDO::FETCH_NUM;
            }

            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll($fetchMode);
            $row = $rows[$offset] ?? null;
            $this->num_rows = $row ? 1 : 0;
            return $row ?: null;
        } catch (\PDOException $e) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    public function get_results(string $sql, string $output = 'OBJECT'): array
    {
        $this->last_error = '';
        $sql = $this->translateSql($sql);

        try {
            $stmt = $this->pdo->query($sql);

            if ($output === 'ARRAY_A') {
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $this->num_rows = count($rows);
                return $rows;
            }
            if ($output === 'ARRAY_N') {
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
                $this->num_rows = count($rows);
                return $rows;
            }
            if ($output === 'OBJECT_K') {
                $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
                $this->num_rows = count($rows);
                $keyed = [];
                foreach ($rows as $row) {
                    $props = get_object_vars($row);
                    $key = array_key_first($props);
                    if ($key === null) {
                        continue;
                    }
                    $keyed[(string)$props[$key]] = $row;
                }
                return $keyed;
            }

            $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $this->num_rows = count($rows);
            return $rows;
        } catch (\PDOException $e) {
            $this->last_error = $e->getMessage();
            return [];
        }
    }

    public function get_var(string $sql, int $col = 0, int $row = 0)
    {
        $this->last_error = '';
        $sql = $this->translateSql($sql);

        try {
            $stmt = $this->pdo->query($sql);
            $result = $stmt->fetchColumn($col);
            return $result !== false ? $result : null;
        } catch (\PDOException $e) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    public function get_col(string $sql, int $col = 0): array
    {
        $this->last_error = '';
        $sql = $this->translateSql($sql);

        try {
            $stmt = $this->pdo->query($sql);
            $values = [];
            while (($val = $stmt->fetchColumn($col)) !== false) {
                $values[] = $val;
            }
            return $values;
        } catch (\PDOException $e) {
            $this->last_error = $e->getMessage();
            return [];
        }
    }

    // ── Write helpers ───────────────────────────────────────────

    /**
     * @param string $table
     * @param array<string, mixed> $data
     * @return int|false  Rows affected or false on error
     */
    public function insert(string $table, array $data)
    {
        $this->last_error = '';
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($data));
            $this->insert_id = (int)$this->pdo->lastInsertId();
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            $this->last_error = $this->translateError($e->getMessage());
            $this->insert_id = 0;
            return false;
        }
    }

    /**
     * @param string $table
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     * @return int|false
     */
    public function update(string $table, array $data, array $where)
    {
        $this->last_error = '';
        $setParts = [];
        $values = [];
        foreach ($data as $col => $val) {
            $setParts[] = "$col = ?";
            $values[] = $val;
        }
        $whereParts = [];
        foreach ($where as $col => $val) {
            $whereParts[] = "$col = ?";
            $values[] = $val;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * WordPress $wpdb->prepare() compatible — replaces %s, %d, %f placeholders.
     */
    public function prepare(string $query, ...$args): string
    {
        // Flatten if a single array was passed (wpdb supports both forms)
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $i = 0;
        $replaced = preg_replace_callback('/%[sdf]/', function ($match) use (&$i, $args) {
            $val = $args[$i] ?? '';
            $i++;
            if ($match[0] === '%d') {
                return (string)(int)$val;
            }
            if ($match[0] === '%f') {
                return (string)(float)$val;
            }
            // %s — quote for SQLite
            return "'" . str_replace("'", "''", (string)$val) . "'";
        }, $query);

        return $replaced;
    }

    // ── Internal ────────────────────────────────────────────────

    /**
     * Translate common MySQL SQL to SQLite-compatible SQL.
     */
    private function translateSql(string $sql): string
    {
        // Remove MySQL charset/collate clauses
        $sql = preg_replace('/ DEFAULT CHARSET=\w+/', '', $sql);
        $sql = preg_replace('/ COLLATE \w+/', '', $sql);

        // bigint(20) unsigned → INTEGER
        $sql = preg_replace('/bigint\(\d+\)\s*unsigned/i', 'INTEGER', $sql);
        $sql = preg_replace('/bigint\(\d+\)/i', 'INTEGER', $sql);
        $sql = preg_replace('/mediumint\(\d+\)/i', 'INTEGER', $sql);

        // varchar(N), char(N) → TEXT
        $sql = preg_replace('/varchar\(\d+\)/i', 'TEXT', $sql);
        $sql = preg_replace('/char\(\d+\)/i', 'TEXT', $sql);

        // longtext, text → TEXT
        $sql = preg_replace('/\blongtext\b/i', 'TEXT', $sql);

        // datetime → TEXT (SQLite stores as text)
        $sql = preg_replace('/\bdatetime\b/i', 'TEXT', $sql);

        // AUTO_INCREMENT → handled by INTEGER PRIMARY KEY in SQLite
        $sql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $sql);

        // Remove NOT NULL from id columns that are autoincrement primary key (SQLite needs it nullable for autoincrement)
        // Actually SQLite supports NOT NULL with INTEGER PRIMARY KEY AUTOINCREMENT, but let's strip NOT NULL selectively
        // for the primary key column only. This is tricky, so instead we let SQLite handle it.

        // UNIQUE KEY name (col) → handled separately; remove inline KEY definitions
        // We need to keep UNIQUE constraints but strip MySQL-style index syntax.

        // Remove FOR UPDATE (SQLite doesn't support it)
        $sql = preg_replace('/\bFOR UPDATE\b/i', '', $sql);

        // MODIFY column → not needed for SQLite (we skip ALTER TABLE MODIFY)
        if (preg_match('/ALTER TABLE .+ MODIFY /i', $sql)) {
            return '-- skipped: ' . $sql;
        }

        // ALTER TABLE … ADD COLUMN — keep as-is (SQLite supports it)
        // ALTER TABLE … ADD UNIQUE KEY name (col) → CREATE UNIQUE INDEX IF NOT EXISTS name ON table(col)
        if (preg_match('/ALTER TABLE (\S+) ADD UNIQUE KEY (\S+) \((\S+)\)/i', $sql, $m)) {
            return "CREATE UNIQUE INDEX IF NOT EXISTS {$m[2]} ON {$m[1]}({$m[3]})";
        }

        // DESCRIBE table → pragma-based (return via special handler)
        if (preg_match('/^DESCRIBE (\S+)/i', $sql, $m)) {
            return "PRAGMA table_info({$m[1]})";
        }

        // SHOW TABLES LIKE 'xxx' → SELECT name FROM sqlite_master WHERE type='table' AND name='xxx'
        if (preg_match("/^SHOW TABLES LIKE '([^']+)'/i", $sql, $m)) {
            return "SELECT name FROM sqlite_master WHERE type='table' AND name='{$m[1]}'";
        }

        return $sql;
    }

    /**
     * Translate SQLite error messages to MySQL-style messages where needed.
     */
    private function translateError(string $message): string
    {
        // SQLite unique constraint → MySQL "Duplicate entry" phrasing
        if (stripos($message, 'UNIQUE constraint failed') !== false) {
            return 'Duplicate entry — ' . $message;
        }
        return $message;
    }
}
