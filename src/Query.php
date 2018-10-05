<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;

/**
 * Provides convenience for writing custom queries while taking advantage of existing defined schemas.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Query
{
    /** @var Connection The database connection */
    private $connection;

    /** @var string The SQL query to perform */
    private $sql;

    /** @var Schema[] The schemas provided for the query */
    private $schemas;

    /** @var array Parameters for the query */
    private $parameters;

    /**
     * Query constructor.
     * @param Connection $connection The database connection
     * @param string $sql The SQL query to perform
     */
    public function __construct(Connection $connection, string $sql)
    {
        $this->connection = $connection;
        $this->sql = $sql;
        $this->schemas = [];
        $this->parameters = [];
    }

    /**
     * Returns a new query object with the given schema.
     * @param Schema $schema The schema to attach to the new query
     * @param string $alias Alias to use for the table in the query
     * @return Query A new query object with the given schema
     */
    public function withSchema(Schema $schema, string $alias = ''): self
    {
        $query = clone $this;
        $query->schemas[$this->formatAlias($alias)] = $schema;

        return $query;
    }

    /**
     * Returns a  new query with the given parameters.
     * @param array $parameters The list of parameters to add
     * @return Query A new query object with the given parameters
     */
    public function withParameters(array $parameters): self
    {
        $query = clone $this;

        foreach ($parameters as $key => $parameter) {
            if (\is_int($key)) {
                $query->parameters[] = $parameter;
                continue;
            }

            $query->parameters[$key] = $parameter;
        }

        return $query;
    }

    /**
     * Returns a new query object without any schemas.
     * @return Query A new query object without any attached schemas
     */
    public function withoutSchemas(): self
    {
        $query = clone $this;
        $query->schemas = [];

        return $query;
    }

    /**
     * Returns a new query object without any inserted parameters.
     * @return Query A new query object without any inserted parameters
     */
    public function withoutParameters(): self
    {
        $query = clone $this;
        $query->parameters = [];

        return $query;
    }

    /**
     * Executes the query and returns a PDOStatement instance for the executed query.
     * @return \PDOStatement Result statement from the executed query
     */
    public function fetchResult(): \PDOStatement
    {
        $placeholders = [];

        foreach ($this->schemas as $alias => $schema) {
            if ($alias === '') {
                $placeholders['{table}'] = $this->connection->formatTable($schema->getTable());
                $placeholders['{fields}'] = $this->connection->formatFields($schema->getFields());

                continue;
            }

            $placeholders["{{$alias}.table}"] = $this->connection->formatTable($schema->getTable(), $alias);
            $placeholders["{{$alias}.fields}"] = $this->connection->formatFields(
                $schema->getFields(),
                $alias,
                $this->formatPrefix($alias)
            );
        }

        $query = $this->connection->query(strtr($this->sql, $placeholders), $this->parameters);
        $query->setFetchMode(\PDO::FETCH_ASSOC);

        return $query;
    }

    /**
     * Executes the query and returns all result rows as an associated array.
     * @return array[] All the result rows as an associated array
     */
    public function fetchRows(): array
    {
        return iterator_to_array($this->generateRows());
    }

    /**
     * Executes the query and returns models for each row with optionally included relationships.
     * @param string $alias The alias of the schema to use for creating models
     * @param string[] $relationships List of unique relationships to fill for the model from the result row
     * @return Model[] The resulting models from the query
     */
    public function fetchModels(string $alias = '', array $relationships = []): array
    {
        return iterator_to_array($this->generateModels($alias, $relationships));
    }

    /**
     * Executes the query and returns an array of results that have been passed through the given callback.
     * @param callable $callback The callback to call for each result row
     * @return array The return values from the callback called for each result row
     */
    public function fetchCallback(callable $callback): array
    {
        return iterator_to_array($this->generateCallback($callback));
    }

    /**
     * Returns a generator that fetches the rows one by one for memory efficient processing.
     * @return \Generator A generator that fetches the rows one by one
     */
    public function generateRows(): \Generator
    {
        foreach ($this->fetchResult() as $row) {
            yield $row;
        }
    }

    /**
     * Returns a generator that fetches the models one by one for memory efficient processing.
     * @param string $alias The alias of the schema to use for creating models
     * @param array $relationships List of unique relationships to fill for the model from the result row
     * @return \Generator Generator that fetches the models one by one
     */
    public function generateModels(string $alias = '', array $relationships = []): \Generator
    {
        $alias = $this->formatAlias($alias);

        if (!isset($this->schemas[$alias])) {
            if ($alias !== '' || \count($this->schemas) !== 1) {
                throw new \InvalidArgumentException('No schema selected for generating database models');
            }

            $alias = array_keys($this->schemas)[0];
        }

        $schema = $this->schemas[$alias];
        $prefix = $this->formatPrefix($alias);
        $modelRelationships = [];

        foreach ($relationships as $key => $name) {
            $modelRelationships[$this->formatPrefix($key)] = $name;
        }

        foreach ($this->fetchResult() as $row) {
            yield $schema->createModelFromRow($row, $prefix, $modelRelationships);
        }
    }

    /**
     * Returns a generator that returns the result of the callback called for each result row one by one.
     * @param callable $callback The callback to call for each result row
     * @return \Generator Generator that calls the callback for each result row and returns the result
     */
    public function generateCallback(callable $callback): \Generator
    {
        foreach ($this->fetchResult() as $row) {
            yield $callback($row);
        }
    }

    /**
     * Returns the string followed by a single underscore.
     * @param string $prefix The prefix to format into canonical format
     * @return string The prefix string formatted into canonical format
     */
    private function formatPrefix(string $prefix): string
    {
        $alias = $this->formatAlias($prefix);

        if ($alias === '') {
            return $alias;
        }

        return $alias . '_';
    }

    /**
     * Returns the string without any following underscores.
     * @param string $alias The alias to format into canonical format
     * @return string The alias string formatted into canonical format
     */
    private function formatAlias(string $alias): string
    {
        return rtrim($alias, '_');
    }
}
