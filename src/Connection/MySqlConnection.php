<?php

namespace Simply\Database\Connection;

/**
 * MySqlConnection.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class MySqlConnection implements Connection
{
    private $lazyLoader;
    private $pdo;

    public function __construct(string $hostname, string $database, string $username, string $password)
    {
        $this->lazyLoader = function () use ($hostname, $database, $username, $password): \PDO {
            return new \PDO($this->getDataSource($hostname, $database), $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => sprintf("SET time_zone = '%s'", date('P')),
            ]);
        };
    }

    private function getDataSource(string $hostname, string $database): string
    {
        if (strncmp($hostname, '/', 1) === 0) {
            return sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $hostname, $database);
        }

        $parts = explode(':', $hostname, 2);

        if (\count($parts) === 1) {
            return sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $hostname, $database);
        }

        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $parts[0], $parts[1], $database);
    }

    public function getConnection(): \PDO
    {
        if (!$this->pdo) {
            $this->pdo = ($this->lazyLoader)();
        }

        return $this->pdo;
    }

    public function insert(string $table, array $values, string & $primaryKey = null): \PDOStatement
    {
        $parameters = [];
        $result = $this->query($this->formatQuery([
            'INSERT INTO' => sprintf('%s (%s)', $this->formatTable($table), $this->formatFields(array_keys($values))),
            'VALUES' => $this->formatParameters($values, $parameters),
        ]), $parameters);

        if ($primaryKey !== null) {
            $primaryKey = $this->getConnection()->lastInsertId();
        }

        return $result;
    }

    public function select(array $fields, string $table, array $where, array $orderBy = [], int $limit = null): \PDOStatement
    {
        $parameters = [];

        return $this->query($this->formatQuery([
            'SELECT' => $this->formatFields($fields),
            'FROM' => $this->formatTable($table),
            'WHERE' => $where ? $this->formatConditions($where, $parameters) : '',
            'ORDER BY' => $this->formatOrder($orderBy),
            'LIMIT' => $orderBy ? $this->formatLimit($limit, $parameters) : '',
        ]), $parameters);
    }

    public function update(string $table, array $values, array $where): \PDOStatement
    {
        $parameters = [];

        return $this->query($this->formatQuery([
            'UPDATE' => $this->formatTable($table),
            'SET' => $this->formatAssignments($values, $parameters),
            'WHERE' => $this->formatConditions($where, $parameters)
        ]), $parameters);
    }

    public function delete(string $table, array $where): \PDOStatement
    {
        $parameters = [];

        return $this->query($this->formatQuery([
            'DELETE FROM' => $this->formatTable($table),
            'WHERE' => $this->formatConditions($where, $parameters),
        ]), $parameters);
    }

    private function formatFields(array $fields): string
    {
        if (!$fields) {
            throw new \InvalidArgumentException('No fields provided for the query');
        }

        return implode(', ', array_map(function (string $field) {
            return $this->escapeIdentifier($field);
        }, $fields));
    }

    private function formatTable(string $table): string
    {
        if ($table === '') {
            throw new \InvalidArgumentException('No table provided for the query');
        }

        return $this->escapeIdentifier($table);
    }

    private function formatConditions(array $conditions, array & $parameters): string
    {
        if (!$conditions) {
            throw new \InvalidArgumentException('No conditions provided for the query');
        }

        $clauses = [];

        foreach ($conditions as $field => $value) {
            $clauses[] = $this->formatClause($field, $value, $parameters);
        }

        return implode(' AND ', $clauses);
    }

    private function formatClause(string $field, $value, array & $parameters): string
    {
        $escaped = $this->escapeIdentifier($field);

        if (\is_array($value)) {
            if (\in_array(null, $value, true)) {
                $value = array_filter($value, function ($value): bool {
                    return $value !== null;
                });

                if ($value) {
                    $placeholders = $this->formatParameters($value, $parameters);
                    return "($escaped IN $placeholders OR $escaped IS NULL)";
                }

                return "$escaped IS NULL";
            }

            $placeholders = $this->formatParameters($value, $parameters);
            return "$escaped IN $placeholders";
        }

        if ($value === null) {
            return "$escaped IS NULL";
        }

        $parameters[] = $value;
        return "$escaped = ?";
    }

    private function formatParameters(array $values, array & $parameters): string
    {
        array_push($parameters, ... array_values($values));
        return sprintf('(%s)', implode(', ', array_fill(0, \count($values), '?')));
    }

    private function formatOrder(array $order): string
    {
        $clauses = [];

        foreach ($order as $field => $direction) {
            $clauses[] = sprintf('%s %s', $this->escapeIdentifier($field), $this->formatDirection($direction));
        }

        return implode(', ', $clauses);
    }

    private function formatDirection(int $order): string
    {
        if ($order === self::ORDER_ASCENDING) {
            return 'ASC';
        }

        if ($order === self::ORDER_DESCENDING) {
            return 'DESC';
        }

        throw new \InvalidArgumentException('Invalid sorting direction');
    }

    private function formatLimit(?int $limit, array & $parameters): string
    {
        if ($limit === null) {
            return '';
        }

        $parameters[] = $limit;
        return '?';
    }

    private function formatAssignments(array $values, array & $parameters): string
    {
        if (!$values) {
            throw new \InvalidArgumentException('No values provided for the query');
        }

        $assignments = [];

        foreach ($values as $field => $value) {
            $assignments[] = sprintf('%s = ?', $this->escapeIdentifier($field));
            $parameters[] = $value;
        }

        return implode(', ', $assignments);
    }

    private function escapeIdentifier(string $identifier): string
    {
        return "`$identifier`";
    }

    private function formatQuery(array $clauses): string
    {
        $parts = [];

        foreach ($clauses as $clause => $value) {
            if ($value === '') {
                continue;
            }

            $parts[] = sprintf('%s %s', $clause, $value);
        }

        return implode(' ', $parts);
    }

    public function query(string $sql, array $parameters = []): \PDOStatement
    {
        $query = $this->getConnection()->prepare($sql);

        foreach ($parameters as $name => $value) {
            $this->bindQueryParameter($query, \is_int($name) ? $name + 1 : $name, $value);
        }

        $query->execute();

        return $query;
    }

    private function bindQueryParameter(\PDOStatement $query, $name, $value): bool
    {
        switch (true) {
            case \is_string($value):
                return $query->bindValue($name, $value, \PDO::PARAM_STR);
            case \is_float($value):
                return $query->bindValue($name, var_export($value, true), \PDO::PARAM_STR);
            case \is_int($value):
                return $query->bindValue($name, $value, \PDO::PARAM_INT);
            case \is_bool($value):
                return $query->bindValue($name, $value ? 1 : 0, \PDO::PARAM_INT);
            case $value === null:
                return $query->bindValue($name, null, \PDO::PARAM_NULL);
            default:
                throw new \InvalidArgumentException('Invalid parameter value type');
        }
    }
}
