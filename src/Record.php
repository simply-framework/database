<?php

namespace Simply\Database;

use Simply\Database\Exception\InvalidRelationshipException;

/**
 * Represents data loaded from a database.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Record implements \ArrayAccess
{
    /** A record state when new record is being inserted to database */
    public const STATE_INSERT = 1;

    /** A record state when existing record is being updated in the database */
    public const STATE_UPDATE = 2;

    /** A record state when the record no longer exists in the database */
    public const STATE_DELETE = 3;

    /** @var Schema The schema for the record data */
    private $schema;

    /** @var array The primary key for the record at the time of retrieving */
    private $primaryKey;

    /** @var array Values for the record fields */
    private $values;

    /** @var bool[] Associative list of fields for the record that have been modified */
    private $changed;

    /** @var int The current state of the record */
    private $state;

    /** @var Record[][] Lists of referenced records for each loaded relationship */
    private $referencedRecords;

    /** @var Model|null The model associated with the record */
    private $model;

    /**
     * Record constructor.
     * @param Schema $schema The schema for the record data
     * @param Model|null $model The model associated with the record or null if it has not been initialized
     */
    public function __construct(Schema $schema, Model $model = null)
    {
        $this->primaryKey = [];
        $this->schema = $schema;
        $this->values = array_fill_keys($schema->getFields(), null);
        $this->state = self::STATE_INSERT;
        $this->changed = [];
        $this->referencedRecords = [];
        $this->model = $model;
    }

    /**
     * Returns the primary key for the record as it was at the time the record was loaded.
     * @return array Associative array of primary key fields and their values
     */
    public function getPrimaryKey(): array
    {
        if (empty($this->primaryKey)) {
            throw new \RuntimeException('Cannot refer to the record via primary key, if it is not defined');
        }

        return $this->primaryKey;
    }

    /**
     * Tells if the record is empty, i.e. none of the fields have any values.
     * @return bool True if all the fields values are null, false otherwise
     */
    public function isEmpty(): bool
    {
        foreach ($this->values as $value) {
            if ($value !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tells if the record is new and not yet inserted into the database.
     * @return bool True if the record has not been inserted into database, false otherwise
     */
    public function isNew(): bool
    {
        return $this->state === self::STATE_INSERT;
    }

    /**
     * Tells if the record has been deleted from the database.
     * @return bool True if the record no longer exists in the database, false otherwise
     */
    public function isDeleted(): bool
    {
        return $this->state === self::STATE_DELETE;
    }

    /**
     * Updates the state of the record after the appropriate database operation.
     * @param int $state The appropriate state depending on the performed database operation
     */
    public function updateState(int $state): void
    {
        $this->state = $state === self::STATE_DELETE ? self::STATE_DELETE : self::STATE_UPDATE;
        $this->changed = [];

        $this->updatePrimaryKey();
    }

    /**
     * Updates the stored primary key based on the field values.
     */
    private function updatePrimaryKey(): void
    {
        $this->primaryKey = [];

        foreach ($this->schema->getPrimaryKey() as $key) {
            $this->primaryKey[$key] = $this->values[$key];
        }
    }

    /**
     * Returns the schema for the record data.
     * @return Schema The schema for the record data
     */
    public function getSchema(): Schema
    {
        return $this->schema;
    }

    /**
     * Returns the model associated with the record and initializes it if has not been initialized yet.
     * @return Model The model associated with the record data
     */
    public function getModel(): Model
    {
        if ($this->model === null) {
            $this->model = $this->schema->createModel($this);
        }

        return $this->model;
    }

    /**
     * Tells if the referenced records for the given relationship has been loaded.
     * @param string $name Name of the relationship
     * @return bool True if the referenced records have been loaded, false if not
     */
    public function hasReferencedRecords(string $name): bool
    {
        $name = $this->getSchema()->getRelationship($name)->getName();

        return isset($this->referencedRecords[$name]);
    }

    /**
     * Loads the referenced records for the given relationship.
     * @param string $name Name of the relationship
     * @param Record[] $records List of records referenced by this record
     */
    public function setReferencedRecords(string $name, array $records): void
    {
        $name = $this->getSchema()->getRelationship($name)->getName();

        (function (self ... $records) use ($name): void {
            $this->referencedRecords[$name] = $records;
        })(... $records);
    }

    /**
     * Returns the list of referenced records for the given relationship.
     * @param string $name Name of the relationship
     * @return Record[] List of records referenced by this record
     */
    public function getReferencedRecords(string $name): array
    {
        $name = $this->getSchema()->getRelationship($name)->getName();

        if (!isset($this->referencedRecords[$name])) {
            throw new \RuntimeException('The referenced records have not been provided');
        }

        return $this->referencedRecords[$name];
    }

    /**
     * Sets the referenced fields in this record to reference the record of the given model.
     * @param string $name Name of the relationship
     * @param Model $model The model that this record should be reference
     */
    public function associate(string $name, Model $model): void
    {
        $relationship = $this->getSchema()->getRelationship($name);

        if (!$relationship->isUniqueRelationship()) {
            throw new InvalidRelationshipException('A single model can only be associated to an unique relationships');
        }

        $keys = $relationship->getFields();
        $fields = $relationship->getReferencedFields();
        $record = $model->getDatabaseRecord();

        if ($record->getSchema() !== $relationship->getReferencedSchema()) {
            throw new \InvalidArgumentException('The associated record belongs to incorrect schema');
        }

        while ($keys) {
            $value = $record[array_pop($fields)];

            if ($value === null) {
                throw new \RuntimeException('Cannot associate with models with nulls in referenced fields');
            }

            $this[array_pop($keys)] = $value;
        }

        $this->referencedRecords[$relationship->getName()] = [$record];
        $reverse = $relationship->getReverseRelationship();

        if ($reverse->isUniqueRelationship()) {
            $record->referencedRecords[$reverse->getName()] = [$this];
        } elseif ($record->hasReferencedRecords($reverse->getName())) {
            $record->referencedRecords[$reverse->getName()][] = $this;
        }
    }

    /**
     * Sets the referencing fields in the record of the given model to reference this record.
     * @param string $name Name of the relationship
     * @param Model $model The model that should reference this record
     */
    public function addAssociation(string $name, Model $model): void
    {
        $relationship = $this->getSchema()->getRelationship($name);

        if ($relationship->isUniqueRelationship()) {
            throw new InvalidRelationshipException('Cannot add a new model to an unique relationship');
        }

        $model->getDatabaseRecord()->associate($relationship->getReverseRelationship()->getName(), $this->getModel());
    }

    /**
     * Returns the model of the referenced record in a unique relationship.
     * @param string $name Name of the relationship
     * @return Model|null The referenced model, or null if no model is referenced by this record
     */
    public function getRelatedModel(string $name): ?Model
    {
        $relationship = $this->getSchema()->getRelationship($name);

        if (!$relationship->isUniqueRelationship()) {
            throw new InvalidRelationshipException('A single model can only be fetched for an unique relationship');
        }

        $records = $this->getReferencedRecords($name);

        if (empty($records)) {
            return null;
        }

        return $this->getReferencedRecords($name)[0]->getModel();
    }

    /**
     * Returns list of models referenced by this record via the given relationship.
     * @param string $name Name of the relationship
     * @return array List of models referenced by this record
     */
    public function getRelatedModels(string $name): array
    {
        $relationship = $this->getSchema()->getRelationship($name);

        if ($relationship->isUniqueRelationship()) {
            throw new InvalidRelationshipException('Cannot fetch multiple models for an unique relationship');
        }

        $models = [];

        foreach ($this->getReferencedRecords($name) as $record) {
            $models[] = $record->getModel();
        }

        return $models;
    }

    /**
     * Gets list of models that are referenced by records that this record references.
     * @param string $proxy Name of the relationship in this record
     * @param string $name Name of the relationship in the proxy record
     * @return array List of models referenced by the records referenced by this record
     */
    public function getRelatedModelsByProxy(string $proxy, string $name): array
    {
        $proxyRelationship = $this->getSchema()->getRelationship($proxy);
        $relationship = $proxyRelationship->getReferencedSchema()->getRelationship($name);

        if ($proxyRelationship->isUniqueRelationship()) {
            throw new InvalidRelationshipException('Cannot fetch models via an unique proxy relationship');
        }

        if (!$relationship->isUniqueRelationship()) {
            throw new InvalidRelationshipException('Cannot fetch models via proxy without a unique relationship');
        }

        $models = [];

        foreach ($this->getReferencedRecords($proxy) as $record) {
            $records = $record->getReferencedRecords($name);

            if (empty($records)) {
                continue;
            }

            $models[] = $records[0]->getModel();
        }

        return $models;
    }

    /**
     * Returns list of records recursively referenced by this record or any referenced record.
     * @return Record[] List of all referenced records and any record they recursively reference
     */
    public function getAllReferencedRecords(): array
    {
        /** @var Record[] $records */
        $records = [spl_object_id($this) => $this];

        do {
            foreach (current($records)->referencedRecords as $recordList) {
                foreach ($recordList as $record) {
                    $id = spl_object_id($record);

                    if (!isset($records[$id])) {
                        $records[$id] = $record;
                    }
                }
            }
        } while (next($records) !== false);

        return array_values($records);
    }

    /**
     * Sets the values for the fields in the record loaded from the database.
     * @param array $row Value for the fields in this record
     */
    public function setDatabaseValues(array $row): void
    {
        if (array_keys($row) !== array_keys($this->values)) {
            if (array_diff_key($row, $this->values) !== [] || \count($row) !== \count($this->values)) {
                throw new \InvalidArgumentException('Invalid set of record database values provided');
            }

            $row = array_replace($this->values, $row);
        }

        $this->values = $row;
        $this->state = self::STATE_UPDATE;
        $this->changed = [];
        $this->updatePrimaryKey();
    }

    /**
     * Returns the values for the fields in this record for storing in database.
     * @return array Associative list of fields and their values
     */
    public function getDatabaseValues(): array
    {
        return $this->values;
    }

    /**
     * Returns list of all fields that have been modified since the records state was last updated.
     * @return string[] List of fields updated since last time the state was updated
     */
    public function getChangedFields(): array
    {
        return array_keys($this->changed);
    }

    /**
     * Tells if the value in the given field is other than null.
     * @param string $offset Name of the field
     * @return bool True if the value is something else than null, false otherwise
     */
    public function offsetExists($offset)
    {
        return $this->offsetGet($offset) !== null;
    }

    /**
     * Returns the value for the given field.
     * @param string $offset Name of the field
     * @return mixed Value for the given field
     */
    public function offsetGet($offset)
    {
        $offset = (string) $offset;

        if (!array_key_exists($offset, $this->values)) {
            throw new \InvalidArgumentException("Invalid record field '$offset'");
        }

        return $this->values[$offset];
    }

    /**
     * Sets the value for the given field.
     * @param string $offset Name of the field
     * @param mixed $value Value for the given field
     */
    public function offsetSet($offset, $value)
    {
        $offset = (string) $offset;

        if (!array_key_exists($offset, $this->values)) {
            throw new \InvalidArgumentException("Invalid record field '$offset'");
        }

        $this->values[$offset] = $value;
        $this->changed[$offset] = true;
    }

    /**
     * Sets the value of the given field to null and marks it unchanged, if the record has not yet been inserted.
     * @param string $offset The name of the field
     */
    public function offsetUnset($offset)
    {
        $this->offsetSet($offset, null);

        if ($this->state === self::STATE_INSERT) {
            unset($this->changed[$offset]);
        }
    }
}
