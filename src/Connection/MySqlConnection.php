<?php

namespace Simply\Database\Connection;

use Simply\Database\Connection\Provider\ConnectionProvider;

/**
 * Provides the basic functionality to operate on a MySQL database.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class MySqlConnection implements Connection
{
    /** @var ConnectionProvider The provider for the PDO connection */
    private $provider;

    /**
     * MySqlConnection constructor.
     * @param ConnectionProvider $provider The provider for the PDO connection
     */
    public function __construct(ConnectionProvider $provider)
    {
        $this->provider = $provider;
    }

    public function getConnection(): \PDO
    {
        return $this->provider->getConnection();
    }

    public function insert(string $table, array $values, & $primaryKey = null): \PDOStatement
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

    public function select(
        array $fields,
        string $table,
        array $where,
        array $orderBy = [],
        int $limit = null
    ): \PDOStatement {
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
            'WHERE' => $this->formatConditions($where, $parameters),
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

    public function formatFields(array $fields, string $table = '', string $prefix = ''): string
    {
        if (!$fields) {
            throw new \InvalidArgumentException('No fields provided for the query');
        }

        $format = '%2$s';

        if ($table !== '') {
            $format = '%1$s.' . $format;
        }

        if ($prefix !== '') {
            $format .= ' AS %3$s';
        }

        return implode(', ', array_map(function (string $field) use ($format, $table, $prefix): string {
            return sprintf(
                $format,
                $this->escapeIdentifier($table),
                $this->escapeIdentifier($field),
                $this->escapeIdentifier($prefix . $field)
            );
        }, $fields));
    }

    public function formatTable(string $table, string $alias = ''): string
    {
        if ($table === '') {
            throw new \InvalidArgumentException('No table provided for the query');
        }

        if ($alias !== '') {
            return sprintf('%s AS %s', $this->escapeIdentifier($table), $this->escapeIdentifier($alias));
        }

        return $this->escapeIdentifier($table);
    }

    /**
     * Formats the conditions for the SQL query.
     * @param array $conditions The conditions for the SQL query
     * @param array $parameters Array of parameters to fill
     * @return string Formatted conditions for the query
     */
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

    /**
     * Formats a single condition for the SQL query.
     * @param string $field The name of the field
     * @param mixed $value Expected value for the fields
     * @param array $parameters Array of parameters to fill
     * @return string The formatted condition for the query
     */
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

    /**
     * Formats a list of parenthesis enclosed parameters for an SQL query.
     * @param array $values List of values
     * @param array $parameters Array of parameters to fill
     * @return string The formatted parenthesis enclosed list of parameter placeholders
     */
    private function formatParameters(array $values, array & $parameters): string
    {
        array_push($parameters, ... array_values($values));
        return sprintf('(%s)', implode(', ', array_fill(0, \count($values), '?')));
    }

    /**
     * Formats the order clause for a select query.
     * @param array $order The order for the select query
     * @return string The formatted ORDER BY clause for the query
     */
    private function formatOrder(array $order): string
    {
        $clauses = [];

        foreach ($order as $field => $direction) {
            $clauses[] = sprintf('%s %s', $this->escapeIdentifier($field), $this->formatDirection($direction));
        }

        return implode(', ', $clauses);
    }

    /**
     * Returns the appropriate SQL sorting order for the sorting constant.
     * @param int $order The sorting constant
     * @return string The SQL representation for the given sorting order
     */
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

    /**
     * Formats the LIMIT clause for a select query.
     * @param int|null $limit The maximum number of rows to return or null for no limit
     * @param array $parameters Array of parameters to fill
     * @return string The formatted LIMIT clause for the select query
     */
    private function formatLimit(?int $limit, array & $parameters): string
    {
        if ($limit === null) {
            return '';
        }

        $parameters[] = $limit;
        return '?';
    }

    /**
     * Formats value assignments in UPDATE query.
     * @param array $values Associate array of columns and values
     * @param array $parameters Array of parameters to fill
     * @return string The formatted list of assignments for the UPDATE query
     */
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

    /**
     * Escapes a MySQL query identifier.
     * @param string $identifier The identifier to escape
     * @return string The escaped identifier
     */
    private function escapeIdentifier(string $identifier): string
    {
        return "`$identifier`";
    }

    /**
     * Formats an arbitrary SQL query based on given clauses.
     * @param array $clauses Associative array of SQL clauses and their contents
     * @return string The complete formatted SQL query
     */
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

    /**
     * Binds an SQL query parameter to the PDO statement fo the query.
     * @param \PDOStatement $query The PDO statement for binding the value
     * @param int|string $name The name of the placeholder
     * @param mixed $value The value to bind
     * @return bool True if the binding was successful, false if not
     */
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
