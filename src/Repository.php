<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;
use Simply\Database\Exception\MissingRecordException;

/**
 * Provides functionality to perform basic database operations on models.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class Repository
{
    /** @var Connection The connection to the database */
    protected $connection;

    /**
     * Repository constructor.
     * @param Connection $connection The connection to the database
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns a list of models from the database based on given conditions.
     * @param Schema $schema The schema of the model records
     * @param array $conditions Conditions for the select query
     * @param array $order Associative list of fields and sorting directions
     * @param int|null $limit Maximum number of results to return for ordered query
     * @return Model[] List of models returned by the database
     */
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

    /**
     * Returns a single model based on the given conditions.
     * @param Schema $schema The schema of the record to select
     * @param array $conditions Conditions for the select query
     * @param array $order The order for the select query
     * @return Model|null The found model or null if none was found
     */
    protected function findOne(Schema $schema, array $conditions, array $order = []): ?Model
    {
        if ($order === []) {
            $keys = $schema->getPrimaryKey();
            $order = array_fill_keys($keys, Connection::ORDER_ASCENDING);
        }

        $result = $this->connection->select($schema->getFields(), $schema->getTable(), $conditions, $order, 1);
        $row = $result->fetch(\PDO::FETCH_ASSOC);

        return $row ? $schema->createModelFromRow($row) : null;
    }

    /**
     * Finds a single model based on the primary key.
     * @param Schema $schema The schema for the selected record
     * @param mixed $values Value for single primary key field or list of values for composite primary key
     * @return Model|null The found model or null if none was found
     */
    protected function findByPrimaryKey(Schema $schema, $values): ?Model
    {
        $conditions = $this->getPrimaryKeyCondition($schema, $values);

        $result = $this->connection->select($schema->getFields(), $schema->getTable(), $conditions);
        $rows = $result->fetchAll(\PDO::FETCH_ASSOC);

        if (\count($rows) > 1) {
            throw new \UnexpectedValueException('Unexpected number of results returned by primary key');
        }

        return $rows ? $schema->createModelFromRow(reset($rows)) : null;
    }

    /**
     * Formats the primary key selection condition for the given schema.
     * @param Schema $schema The schema for the record
     * @param mixed $values Value or list of values for the primary key
     * @return array The primary key condition based on the given schema and values
     */
    private function getPrimaryKeyCondition(Schema $schema, $values): array
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

    /**
     * Inserts or updates the given model based on its state.
     * @param Model $model The model to save
     */
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

    /**
     * Inserts the given model to the database.
     * @param Model $model The model to insert
     */
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

    /**
     * Updates the field values for the model from the database.
     * @param Model $model The model that should be updated
     */
    protected function refresh(Model $model): void
    {
        $record = $model->getDatabaseRecord();
        $schema = $record->getSchema();

        $result = $this->connection->select($schema->getFields(), $schema->getTable(), $record->getPrimaryKey());
        $rows = $result->fetchAll(\PDO::FETCH_ASSOC);

        if (\count($rows) !== 1) {
            throw new MissingRecordException('Tried to refresh a record that does not exist in the database');
        }

        $record->setDatabaseValues(reset($rows));
    }

    /**
     * Updates the model in the database.
     * @param Model $model The model to update
     */
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

    /**
     * Deletes the model from the database.
     * @param Model $model The model to delete
     */
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
     * Fills the given relationships for the given models.
     * @param Model[] $models The models to fill with relationships
     * @param string[] $relationships List of relationships to fill for the models
     */
    protected function fillRelationships(array $models, array $relationships): void
    {
        $records = array_map(function (Model $model): Record {
            return $model->getDatabaseRecord();
        }, array_values($models));

        $filler = new RelationshipFiller($this->connection);
        $filler->fill($records, $relationships);
    }

    /**
     * Returns a custom query object for the given SQL statement.
     * @param string $sql The sql to use to initialize the query object
     * @return Query The query object initialized with the connection and the given SQL statement
     */
    protected function query(string $sql): Query
    {
        return new Query($this->connection, $sql);
    }
}
