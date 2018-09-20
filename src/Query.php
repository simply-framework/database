<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;

/**
 * Query.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Query
{
    /** @var Connection */
    private $connection;
    private $sql;

    /** @var Schema[] */
    private $schemas;
    private $parameters;

    public function __construct(Connection $connection, string $sql)
    {
        $this->connection = $connection;
        $this->sql = $sql;
        $this->schemas = [];
        $this->parameters = [];
    }

    public function withSchema(Schema $schema, string $alias = ''): Query
    {
        $query = clone $this;
        $query->schemas[$this->formatAlias($alias)] = $schema;

        return $query;
    }

    public function withParameters(array $parameters): Query
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

    public function withoutSchemas(): Query
    {
        $query = clone $this;
        $query->schemas = [];

        return $query;
    }

    public function withoutParameters(): Query
    {
        $query = clone $this;
        $query->parameters = [];

        return $query;
    }

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

    public function fetchRows(): array
    {
        return iterator_to_array($this->generateRows());
    }

    public function fetchModels(string $alias = '', array $relationships = []): array
    {
        return iterator_to_array($this->generateModels($alias, $relationships));
    }

    public function fetchCallback(callable $callback): array
    {
        return iterator_to_array($this->generateCallback($callback));
    }

    public function generateRows(): \Generator
    {
        foreach ($this->fetchResult() as $row) {
            yield $row;
        }
    }

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

    public function generateCallback(callable $callback): \Generator
    {
        foreach ($this->fetchResult() as $row) {
            yield $callback($row);
        }
    }

    private function formatAlias(string $alias): string
    {
        if ($alias === '') {
            return $alias;
        }

        return substr($alias, -1) === '_' ? substr($alias, 0, -1) : $alias;
    }

    private function formatPrefix(string $prefix): string
    {
        if ($prefix === '') {
            return $prefix;
        }

        return substr($prefix, -1) === '_' ? $prefix : $prefix . '_';
    }
}