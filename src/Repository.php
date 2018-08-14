<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;

/**
 * Repository.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Repository
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    protected function find(Schema $schema, array $conditions, array $order = [], int $limit = null): array
    {
        $result = $this->connection->select($schema->getFields(), $schema->getTable(), $conditions, $order, $limit);
        $result->setFetchMode(\PDO::FETCH_ASSOC);

        $models = [];

        foreach ($result as $row) {
            $models[] = $schema->createModel($row);
        }

        return $models;
    }

    protected function findOne(Schema $schema, array $conditions): ?Model
    {
        $keys = $schema->getPrimaryKeys();

        if ($keys) {
            $order = array_fill_keys($keys, Connection::ORDER_ASCENDING);
            $result = $this->connection->select($schema->getFields(), $schema->getTable(), $conditions, $order, 1);
        } else {
            $result = $this->connection->select($schema->getFields(), $schema->getTable(), $conditions);
        }

        $result->setFetchMode(\PDO::FETCH_ASSOC);

        $row = $result->fetch();

        return $row ? $schema->createModel($row) : null;
    }

    protected function findByPrimaryKey(Schema $schema, $values): ?Model
    {
        $this->findOne($schema, $this->getPrimaryKeyCondition($schema, $values));
    }

    protected function getPrimaryKeyCondition(Schema $schema, $values)
    {
        $keys = $schema->getPrimaryKeys();
        $condition = [];

        if (!\is_array($values)) {
            $values = [$values];
        }

        if (array_keys($values) === range(0, \count($keys))) {
            $values = array_combine($keys, $values);
        }

        foreach ($keys as $key) {
            if (!isset($values[$key])) {
                throw new \InvalidArgumentException('No value provided for primary key');
            }

            if (\is_array($values[$key])) {
                throw new \InvalidArgumentException('Invalid value provided for primary key');
            }

            $condition[$key] = $values[$key];
        }

        return $condition;
    }

    protected function save(Model $model)
    {
        if ($model->getDatabaseRecord()->isNew()) {
            $this->insert($model);
            return;
        }

        $this->update($model);
    }

    protected function insert(Model $model)
    {
        $record = $model->getDatabaseRecord();
        $schema = $record->getSchema();
        $values = $record->getDatabaseValues();

        $primaryKeys = $schema->getPrimaryKeys();

        if (\count($primaryKeys) === 1) {
            $primary = reset($primaryKeys);

            if (!isset($values[$primary])) {
                unset($values[$primary]);

                $this->connection->insert($schema->getTable(), $values, $primary);
                $record[reset(($primaryKeys))] = $primary;
                return;
            }
        }

        $this->connection->insert($schema->getTable(), $values);
    }

    protected function update(Model $model)
    {
        $record = $model->getDatabaseRecord();
        $schema = $record->getSchema();

        $values = $record->getDatabaseValues();
        $condition = $this->getPrimaryKeyCondition($schema, $values);
        $values = array_diff_key($values, $condition);

        $this->connection->update($schema->getTable(), $values, $condition);
    }

    protected function remove(Model $model)
    {
        $record = $model->getDatabaseRecord();
        $schema = $record->getSchema();

        $this->connection->delete(
            $schema->getTable(),
            $this->getPrimaryKeyCondition($schema, $record->getDatabaseValues())
        );
    }
}
