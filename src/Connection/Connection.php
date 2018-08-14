<?php

namespace Simply\Database\Connection;

/**
 * Connection.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
interface Connection
{
    public const ORDER_ASCENDING = 1;
    public const ORDER_DESCENDING = 2;

    public function getConnection(): \PDO;
    public function getLastInsertId();
    public function insert(string $table, array $values, string & $primaryKey = null): \PDOStatement;
    public function select(array $fields, string $table, array $where, array $orderBy = [], int $limit = null): \PDOStatement;
    public function update(string $table, array $values, array $where): \PDOStatement;
    public function delete(string $table, array $where): \PDOStatement;
    public function query(string $sql, array $parameters = []): \PDOStatement;
}
