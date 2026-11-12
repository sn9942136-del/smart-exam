<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Database;
use PDO;

/**
 * Base Model
 * Lightweight Active Record / Query Builder abstraction over PDO.
 */
abstract class BaseModel
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $allowedFields = [];
    protected bool $useTimestamps = true;
    protected string $createdAtField = 'created_at';
    protected string $updatedAtField = 'updated_at';

    protected array $whereClauses = [];
    protected array $whereBindings = [];

    /**
     * Dapatkan instance PDO aktif
     */
    protected function db(): PDO
    {
        return Database::getConnection();
    }

    /**
     * Ambil record berdasarkan Primary Key
     */
    public function find(int|string $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Tambahkan kondisi WHERE untuk query builder
     */
    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $paramKey = ':w_' . preg_replace('/[^a-zA-Z0-9_]/', '', $column) . '_' . count($this->whereClauses);
        $this->whereClauses[] = "{$column} {$operator} {$paramKey}";
        $this->whereBindings[$paramKey] = $value;
        return $this;
    }

    /**
     * Ambil 1 record pertama dari query builder yang sedang disusun
     */
    public function first(): ?array
    {
        $sql = "SELECT * FROM {$this->table}";
        if (!empty($this->whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $this->whereClauses);
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        foreach ($this->whereBindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        // Reset state
        $this->whereClauses = [];
        $this->whereBindings = [];

        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Ambil semua record hasil query builder yang sedang disusun
     */
    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if (!empty($this->whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $this->whereClauses);
        }

        $stmt = $this->db()->prepare($sql);
        foreach ($this->whereBindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        // Reset state
        $this->whereClauses = [];
        $this->whereBindings = [];

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Ambil semua data pada tabel dengan opsional filter dan pagination
     */
    public function findAll(array $conditions = [], string $orderBy = '', ?int $limit = null, ?int $offset = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $bindings = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $column => $value) {
                if ($value === null) {
                    $clauses[] = "{$column} IS NULL";
                } else {
                    $paramKey = ':c_' . preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
                    $clauses[] = "{$column} = {$paramKey}";
                    $bindings[$paramKey] = $value;
                }
            }
            $sql .= " WHERE " . implode(" AND ", $clauses);
        }

        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }

        $stmt = $this->db()->prepare($sql);
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Tambahkan record baru ke database
     */
    public function insert(array $data): int|string|bool
    {
        // Filter kolom yang diizinkan
        if (!empty($this->allowedFields)) {
            $data = array_intersect_key($data, array_flip($this->allowedFields));
        }

        if ($this->useTimestamps) {
            $now = date('Y-m-d H:i:s');
            if (empty($this->allowedFields) || in_array($this->createdAtField, $this->allowedFields, true)) {
                $data[$this->createdAtField] = $data[$this->createdAtField] ?? $now;
            }
            if (empty($this->allowedFields) || in_array($this->updatedAtField, $this->allowedFields, true)) {
                $data[$this->updatedAtField] = $data[$this->updatedAtField] ?? $now;
            }
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(", ", $columns),
            implode(", ", $placeholders)
        );

        $stmt = $this->db()->prepare($sql);
        foreach ($data as $column => $value) {
            $stmt->bindValue(":{$column}", $value);
        }

        if ($stmt->execute()) {
            return $this->db()->lastInsertId();
        }

        return false;
    }

    /**
     * Perbarui record berdasarkan ID
     */
    public function update(int|string $id, array $data): bool
    {
        if (!empty($this->allowedFields)) {
            $data = array_intersect_key($data, array_flip($this->allowedFields));
        }

        if ($this->useTimestamps) {
            if (empty($this->allowedFields) || in_array($this->updatedAtField, $this->allowedFields, true)) {
                $data[$this->updatedAtField] = date('Y-m-d H:i:s');
            }
        }

        $setPairs = [];
        foreach (array_keys($data) as $column) {
            $setPairs[] = "{$column} = :{$column}";
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = :_pk_id",
            $this->table,
            implode(", ", $setPairs),
            $this->primaryKey
        );

        $stmt = $this->db()->prepare($sql);
        foreach ($data as $column => $value) {
            $stmt->bindValue(":{$column}", $value);
        }
        $stmt->bindValue(':_pk_id', $id);

        return $stmt->execute();
    }

    /**
     * Hapus record berdasarkan ID
     */
    public function delete(int|string $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':id', $id);
        return $stmt->execute();
    }

    /**
     * Hitung total record dengan kondisi tertentu
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $bindings = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $column => $value) {
                if ($value === null) {
                    $clauses[] = "{$column} IS NULL";
                } else {
                    $paramKey = ':c_' . preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
                    $clauses[] = "{$column} = {$paramKey}";
                    $bindings[$paramKey] = $value;
                }
            }
            $sql .= " WHERE " . implode(" AND ", $clauses);
        }

        $stmt = $this->db()->prepare($sql);
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        $res = $stmt->fetch();
        return (int) ($res['total'] ?? 0);
    }

    /**
     * Eksekusi Raw SQL query (SELECT)
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Eksekusi Raw SQL Command (INSERT/UPDATE/DELETE/ALTER)
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
