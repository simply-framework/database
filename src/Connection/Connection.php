<?php

namespace Simply\Database\Connection;

/**
 * Interface for database connections that provide basic query functionality to an SQL database.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
interface Connection
{
    /** Ascending sorting order for select queries. */
    public const ORDER_ASCENDING = 1;

    /** Descending sorting order for select queries. */
    public const ORDER_DESCENDING = 2;

    /**
     * Returns the underlying PDO connection to the database.
     * @return \PDO The underlying PDO connection to the database
     */
    public function getConnection(): \PDO;

    /**
     * Inserts a row the database with given column values.
     * @param string $table Name of the table to insert
     * @param array $values Associative array of column values for the insert
     * @param string|null $primaryKey Name of automatic primary key column that will be overwritten with its value
     * @return \PDOStatement The resulting PDO statement after executing the query
     */
    public function insert(string $table, array $values, & $primaryKey = null): \PDOStatement;

    /**
     * Selects rows from the database.
     *
     * The conditions provided to the query must be an associative array of columns and expected values. Any scalar
     * value and null are supported for equal comparison. If an array is provided as an value for the column, then
     * the value must by any of the provided values in the array, i.e. an "IN" SQL comparison. Null is also supported
     * as one of the values in the array.
     *
     * The sorting order provides list of columns to sort by in order of importance with the appropriate sorting
     * direction. Note that the limit argument is ignore if no sorting columns have been provided, as the order of
     * rows is undefined.
     *
     * @param string[] $fields List of fields to select from the database
     * @param string $table The name of the table to select
     * @param array $where Conditions for the select query
     * @param int[] $orderBy Associative array of columns to sort by and the sorting direction for each column
     * @param int|null $limit Maximum number of rows to return on sorted statement
     * @return \PDOStatement The resulting PDO statement after executing the query
     */
    public function select(
        array $fields,
        string $table,
        array $where,
        array $orderBy = [],
        int $limit = null
    ): \PDOStatement;

    /**
     * Updates rows in the database.
     * @param string $table The table to update
     * @param array $values Associative array of columns and values to update
     * @param array $where Conditions for the updated row, similar to select queries
     * @return \PDOStatement The resulting PDO statement after executing the query
     */
    public function update(string $table, array $values, array $where): \PDOStatement;

    /**
     * Deletes rows from the database.
     * @param string $table The table where to delete rows
     * @param array $where Conditions for the delete query, similar to select query
     * @return \PDOStatement The resulting PDO statement after executing the query
     */
    public function delete(string $table, array $where): \PDOStatement;

    /**
     * Executes an arbitrary SQL query in the database.
     * @param string $sql The SQL query to execute
     * @param array $parameters Array of parameters to pass to the query
     * @return \PDOStatement The resulting PDO statement after executing the query
     */
    public function query(string $sql, array $parameters = []): \PDOStatement;

    /**
     * Returns an escaped table name aliased to the given alias.
     * @param string $table The name of the table to escape
     * @param string $alias The alias for the table or empty string for no alias
     * @return string The escaped table name for an SQL query
     */
    public function formatTable(string $table, string $alias = ''): string;

    /**
     * Returns a comma separated list of escaped field names from given table aliased with given prefix.
     * @param string[] $fields List of fields to escape
     * @param string $table The name or alias of the table for the fields
     * @param string $prefix Prefix to use for each field in the alias
     * @return string Comma separated list of escaped fields
     */
    public function formatFields(array $fields, string $table = '', string $prefix = ''): string;
}
