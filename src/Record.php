<?php

namespace Simply\Database;

/**
 * Record.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Record implements \ArrayAccess
{
    public const STATE_INSERT = 1;
    public const STATE_UPDATE = 2;
    public const STATE_DELETE = 3;

    private $schema;

    private $primaryKey;

    private $values;

    private $changed;

    private $state;

    /** @var Record[][] */
    private $referencedRecords;

    private $model;

    public function __construct(Schema $schema, Model $model = null)
    {
        $this->schema = $schema;
        $this->values = array_fill_keys($schema->getFields(), null);
        $this->state = self::STATE_INSERT;
        $this->changed = [];
        $this->referencedRecords = [];
        $this->model = $model;
    }

    public function getPrimaryKey(): array
    {
        if (empty($this->primaryKey)) {
            throw new \RuntimeException('Cannot refer to the record via primary key, if it is not defined');
        }

        return $this->primaryKey;
    }

    public function isNew(): bool
    {
        return $this->state === self::STATE_INSERT;
    }

    public function isDeleted(): bool
    {
        return $this->state === self::STATE_DELETE;
    }

    public function updateState(int $state): void
    {
        $this->state = $state === self::STATE_DELETE ? self::STATE_DELETE : self::STATE_UPDATE;
        $this->changed = [];

        $this->updatePrimaryKey();
    }

    private function updatePrimaryKey(): void
    {
        $this->primaryKey = [];

        foreach ($this->schema->getPrimaryKey() as $key) {
            $this->primaryKey[$key] = $this->values[$key];
        }
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getModel(): Model
    {
        if ($this->model === null) {
            $this->model = $this->schema->getModel($this);
        }

        return $this->model;
    }

    public function hasReferencedRecords(string $name): bool
    {
        $name = $this->getSchema()->getRelationship($name)->getName();

        return isset($this->referencedRecords[$name]);
    }

    public function setReferencedRecords(string $name, array $records): void
    {
        $name = $this->getSchema()->getRelationship($name)->getName();

        (function (Record ... $records) use ($name): void {
            $this->referencedRecords[$name] = $records;
        })(... $records);
    }

    /**
     * @param string $name
     * @return Record[]
     */
    public function getReferencedRecords(string $name): array
    {
        $name = $this->getSchema()->getRelationship($name)->getName();

        if (!isset($this->referencedRecords[$name])) {
            throw new \RuntimeException("The referenced records for the relationship '$name' have not been provided");
        }

        return $this->referencedRecords[$name];
    }

    public function associate(string $name, Model $model): void
    {
        $relationship = $this->getSchema()->getRelationship($name);

        if (!$relationship->isUniqueRelationship()) {
            throw new \InvalidArgumentException('A single model can only be associated to an unique relationships');
        }

        $keys = $relationship->getFields();
        $fields = $relationship->getReferencedFields();
        $record = $model->getDatabaseRecord();

        if ($record->getSchema() !== $relationship->getReferencedSchema()) {
            throw new \InvalidArgumentException('The associated model has a record with an unexpected schema');
        }

        while ($keys) {
            $value = $record[array_pop($fields)];

            if ($value === null) {
                throw new \RuntimeException('Cannot associate to models with nulls in referenced fields');
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

    public function addAssociation(string $name, Model $model): void
    {
        $relationship = $this->getSchema()->getRelationship($name);

        if ($relationship->isUniqueRelationship()) {
            throw new \InvalidArgumentException('Cannot add a new model to an unique relationship');
        }

        $model->getDatabaseRecord()->associate($relationship->getReverseRelationship()->getName(), $this->getModel());
    }

    public function getRelatedModel(string $name): ?Model
    {
        $relationship = $this->getSchema()->getRelationship($name);

        if (!$relationship->isUniqueRelationship()) {
            throw new \RuntimeException('A single related model can only be fetched for an unique relationship');
        }

        $records = $this->getReferencedRecords($name);

        if (empty($records)) {
            return null;
        }

        return $this->getReferencedRecords($name)[0]->getModel();
    }

    public function getRelatedModels(string $name): array
    {
        $relationship = $this->getSchema()->getRelationship($name);

        if ($relationship->isUniqueRelationship()) {
            throw new \RuntimeException('Cannot fetch multiple models for an unique relationship');
        }

        $models = [];

        foreach ($this->getReferencedRecords($name) as $record) {
            $models[] = $record->getModel();
        }

        return $models;
    }

    public function getRelatedModelsByProxy(string $proxy, string $name): array
    {
        $proxyRelationship = $this->getSchema()->getRelationship($proxy);
        $relationship = $proxyRelationship->getReferencedSchema()->getRelationship($name);

        if ($proxyRelationship->isUniqueRelationship()) {
            throw new \RuntimeException('Cannot fetch related models via an unique proxy relationship');
        }

        if (!$relationship->isUniqueRelationship()) {
            throw new \RuntimeException('Related models can only be fetched via proxy with an unique relationship');
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
     * @return Record[]
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

    public function setDatabaseValues(array $row)
    {
        if (array_keys($row) !== array_keys($this->values)) {
            throw new \InvalidArgumentException('Invalid set of record database values provided');
        }

        $this->values = $row;
        $this->state = self::STATE_UPDATE;
        $this->changed = [];
        $this->updatePrimaryKey();
    }

    public function getDatabaseValues(): array
    {
        return $this->values;
    }

    public function getChangedFields(): array
    {
        return array_keys($this->changed);
    }

    public function offsetExists($offset)
    {
        return $this->offsetGet($offset) !== null;
    }

    public function offsetGet($offset)
    {
        if (!array_key_exists($offset, $this->values)) {
            throw new \InvalidArgumentException("Invalid record field '$offset'");
        }

        return $this->values[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if (!array_key_exists($offset, $this->values)) {
            throw new \InvalidArgumentException("Invalid record field '$offset'");
        }

        $this->values[$offset] = $value;
        $this->changed[$offset] = true;
    }

    public function offsetUnset($offset)
    {
        $this->offsetSet($offset, null);
    }
}
