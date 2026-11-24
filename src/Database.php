<?php

declare(strict_types=1);

namespace Snmptraps;

use InvalidArgumentException;
use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. All values go through prepared statements; identifiers
 * (table/column names) are validated against a strict pattern.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly array $params)
    {
    }

    public static function fromConfig(Config $config): self
    {
        return new self($config->db);
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $p = $this->params;
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8', $p['host'], (int) $p['port'], $p['name']);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ] + $this->sslOptions();
            $this->pdo = new PDO($dsn, (string) $p['user'], (string) $p['pass'], $options);
        }
        return $this->pdo;
    }

    /**
     * MySQL SSL options. PHP 8.4+ provides Pdo\Mysql::ATTR_* (the PDO::MYSQL_ATTR_*
     * aliases are deprecated in 8.5), older versions only have the PDO constants.
     */
    private function sslOptions(): array
    {
        if (empty($this->params['ssl'])) {
            return [];
        }
        $map = ['ssl_key' => 'SSL_KEY', 'ssl_cert' => 'SSL_CERT', 'ssl_ca' => 'SSL_CA', 'ssl_capath' => 'SSL_CAPATH', 'ssl_cipher' => 'SSL_CIPHER'];
        $out = [];
        foreach ($map as $key => $name) {
            if (empty($this->params[$key])) {
                continue;
            }
            $const = class_exists('Pdo\\Mysql') ? 'Pdo\\Mysql::ATTR_' . $name : 'PDO::MYSQL_ATTR_' . $name;
            $out[constant($const)] = $this->params[$key];
        }
        return $out;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        foreach (array_values($params) as $i => $value) {
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($i + 1, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return list<array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** @return list<mixed> */
    public function column(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function insert(string $table, array $row): int
    {
        $cols = array_map($this->ident(...), array_keys($row));
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->ident($table), implode(', ', $cols), implode(', ', array_fill(0, count($row), '?')));
        $this->run($sql, array_values($row));
        return (int) $this->pdo()->lastInsertId();
    }

    public function update(string $table, int $id, array $row): int
    {
        if ($row === []) {
            return 0;
        }
        $set = implode(', ', array_map(fn ($c) => $this->ident($c) . ' = ?', array_keys($row)));
        return $this->run(sprintf('UPDATE %s SET %s WHERE `id` = ?', $this->ident($table), $set), [...array_values($row), $id])->rowCount();
    }

    public function delete(string $table, int $id): int
    {
        return $this->run(sprintf('DELETE FROM %s WHERE `id` = ?', $this->ident($table)), [$id])->rowCount();
    }

    public function find(string $table, int $id): ?array
    {
        return $this->one(sprintf('SELECT * FROM %s WHERE `id` = ? LIMIT 1', $this->ident($table)), [$id]);
    }

    /**
     * Quote an identifier after validating it.
     */
    public function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name)) {
            throw new InvalidArgumentException('Invalid SQL identifier: ' . $name);
        }
        return '`' . $name . '`';
    }

    /**
     * Build "?, ?, ?" for an IN() list.
     */
    public static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, max(1, count($values)), '?'));
    }
}
