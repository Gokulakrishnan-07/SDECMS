<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Base model with common CRUD helpers. Every query uses prepared statements.
 */
abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';

    protected function db(): PDO
    {
        return Database::connection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function all(string $orderBy = 'id', string $dir = 'ASC'): array
    {
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $orderBy = preg_replace('/[^a-zA-Z0-9_]/', '', $orderBy);
        return $this->db()->query("SELECT * FROM {$this->table} ORDER BY {$orderBy} {$dir}")->fetchAll();
    }

    /**
     * Insert an associative array of column => value. Returns new row id.
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $cols    = implode(', ', array_map(static fn ($c) => "`$c`", $columns));
        $marks   = implode(', ', array_fill(0, count($columns), '?'));

        $stmt = $this->db()->prepare("INSERT INTO {$this->table} ($cols) VALUES ($marks)");
        $stmt->execute(array_values($data));
        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if ($data === []) {
            return false;
        }
        $sets = implode(', ', array_map(static fn ($c) => "`$c` = ?", array_keys($data)));
        $stmt = $this->db()->prepare("UPDATE {$this->table} SET $sets WHERE {$this->primaryKey} = ?");
        return $stmt->execute([...array_values($data), $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db()->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    public function count(string $where = '1=1', array $params = []): int
    {
        $stmt = $this->db()->prepare("SELECT COUNT(*) AS c FROM {$this->table} WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetch()['c'];
    }
}
