<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;
use Simply\Database\Exception\MissingRecordException;

/**
 * Repository.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class Repository
{
    /** @var Connection */
    protected $connection;

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
            $models[] = $schema->createModelFromRow($row);
        }

        return $models;
    }

    protected function findOne(Schema $schema, array $conditions): ?Model
    {
        $keys = $schema->getPrimaryKey();
        $order = array_fill_keys($keys, Connection::ORDER_ASCENDING);

        $result = $this->connection->select($schema->getFields(), $schema->getTable(), $conditions, $order, 1);
        $result->setFetchMode(\PDO::FETCH_ASSOC);
        $row = $result->fetch();

        return $row ? $schema->createModelFromRow($row) : null;
    }

    /**
     * @param Schema $schema
     * @param mixed $values
     * @return null|Model
     */
    protected function findByPrimaryKey(Schema $schema, $values): ?Model
    {
        return $this->findOne($schema, $this->getPrimaryKeyCondition($schema, $values));
    }

    /**
     * @param Schema $schema
     * @param mixed $values
     * @return array
     */
    protected function getPrimaryKeyCondition(Schema $schema, $values): array
    {
        $keys = $schema->getPrimaryKey();
        $condition = [];

        if (!\is_array($values)) {
            $values = [$values];
        }

        if (array_keys($values) === range(0, \count($keys) - 1)) {
            $values = array_combine($keys, $values);
        }

        foreach ($keys as $key) {
            if (!isset($values[$key])) {
                throw new \InvalidArgumentException('Missing value for a primary key');
            }

            if (\is_array($values[$key])) {
                throw new \InvalidArgumentException('Invalid value provided for primary key');
            }

            $condition[$key] = $values[$key];
        }

        return $condition;
    }

    protected function save(Model $model): void
    {
        $record = $model->getDatabaseRecord();

        if ($record->isDeleted()) {
            throw new \RuntimeException('Tried to save a record that has already been deleted');
        }

        if ($record->isNew()) {
            $this->insert($model);
            return;
        }

        $this->update($model);
    }

    protected function insert(Model $model): void
    {
        $record = $model->getDatabaseRecord();
        $schema = $record->getSchema();
        $values = array_intersect_key($record->getDatabaseValues(), array_flip($record->getChangedFields()));

        $primaryKeys = $schema->getPrimaryKey();

        if (\count($primaryKeys) === 1) {
            $primary = reset($primaryKeys);

            if (!isset($values[$primary])) {
                unset($values[$primary]);

                $this->connection->insert($schema->getTable(), $values, $primary);
                $record[reset($primaryKeys)] = $primary;
                $record->updateState(Record::STATE_INSERT);
                return;
            }
        }

        $this->connection->insert($schema->getTable(), $values);
        $record->updateState(Record::STATE_INSERT);
    }

    protected function refresh(Model $model): void
    {
        $record = $model->getDatabaseRecord();
        $schema = $record->getSchema();

        $result = $this->connection->select($schema->getFields(), $schema->getTable(), $record->getPrimaryKey());
        $row = $result->fetch(\PDO::FETCH_ASSOC);

        if (empty($row)) {
            throw new MissingRecordException('Tried to refresh a record that does not exist in the database');
        }

        $record->setDatabaseValues($row);
    }

    protected function update(Model $model): void
    {
        $record = $model->getDatabaseRecord();
        $schema = $record->getSchema();
        $values = array_intersect_key($record->getDatabaseValues(), array_flip($record->getChangedFields()));

        $result = $this->connection->update($schema->getTable(), $values, $record->getPrimaryKey());

        if ($result->rowCount() !== 1) {
            throw new MissingRecordException('Tried to update a record that does not exist in the database');
        }

        $record->updateState(Record::STATE_UPDATE);
    }

    protected function delete(Model $model): void
    {
        $record = $model->getDatabaseRecord();
        $schema = $record->getSchema();

        $result = $this->connection->delete($schema->getTable(), $record->getPrimaryKey());

        if ($result->rowCount() !== 1) {
            throw new MissingRecordException('Tried to delete a record that does not exist in the database');
        }

        $record->updateState(Record::STATE_DELETE);
    }

    /**
     * @param Model[] $models
     * @param string[] $relationships
     */
    protected function fillRelationships(array $models, array $relationships): void
    {
        $records = array_map(function (Model $model): Record {
            return $model->getDatabaseRecord();
        }, array_values($models));

        $filler = new RelationshipFiller($this->connection);
        $filler->fill($records, $relationships);
    }

    protected function query(string $sql): Query
    {
        return new Query($this->connection, $sql);
    }
}
