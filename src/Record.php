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
    private $references;

    private $model;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
        $this->values = array_fill_keys($schema->getFields(), null);
        $this->state = self::STATE_INSERT;
        $this->changed = [];
        $this->references = [];
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

    public function getReferencedModel(string $name): Model
    {
        $relationship = $this->getSchema()->getRelationship($name);

        if (!$relationship->isUniqueRelationship()) {
            throw new \RuntimeException('Cannot fetch a single model a non-unique relationship');
        }

        $records = $this->getReferencedRecords($name);

        if (empty($records)) {
            return null;
        }

        return $this->getReferencedRecords($name)[0]->getModel();
    }

    public function getReferredModels(string $name): array
    {
        $reference = $this->getSchema()->getRelationship($name);

        if ($reference->isUniqueRelationship()) {
            throw new \RuntimeException('Cannot refer to multiple models in a single relationship');
        }

        $models = [];

        foreach ($this->getReferencedRecords($name) as $record) {
            $models[] = $record->getModel();
        }

        return $models;
    }

    public function getReferredProxyModels(string $proxy, string $name): array
    {
        $proxyReference = $this->getSchema()->getRelationship($proxy);
        $reference = $proxyReference->getReferencedSchema()->getRelationship($name);

        if ($proxyReference->isUniqueRelationship()) {
            throw new \RuntimeException('Cannot refer to multiple models in a single relationship');
        }

        if (!$reference->isUniqueRelationship()) {
            throw new \RuntimeException('Can only refer to single models in a single relationship');
        }

        $models = [];

        foreach ($this->getReferencedRecords($proxy) as $record) {
            $records = $record->getReferencedRecords($name);

            if (empty($records)) {
                throw new \UnexpectedValueException('The single relationship does not refer to any record');
            }

            $models[] = $records[0]->getModel();
        }

        return $models;
    }

    public function hasReferencedRecords(string $name): bool
    {
        return isset($this->references[$name]);
    }

    /**
     * @param string $name
     * @return Record[]
     */
    public function getReferencedRecords(string $name): array
    {
        if (!isset($this->references[$name])) {
            throw new \RuntimeException("The referenced records for the relationship '$name' have not been filled");
        }

        return $this->references[$name];
    }

    /**
     * @param string $name
     * @param Record[] $records
     */
    public function fillReferencedRecords(string $name, array $records): void
    {
        $relationship = $this->getSchema()->getRelationship($name);

        if (\count($records) > 1 && $relationship->isUniqueRelationship()) {
            throw new \InvalidArgumentException('A unique relationship cannot reference more than a single record');
        }

        foreach ($records as $record) {
            if (!$this->isRelated($relationship, $record)) {
                throw new \InvalidArgumentException('The provided records are not related to this record');
            }
        }

        if ($relationship->getReverseRelationship()->isUniqueRelationship()) {
            $reverse = $relationship->getReverseRelationship()->getName();

            foreach ($records as $record) {
                $record->references[$reverse] = [$this];
            }
        }

        $this->references[$name] = array_values($records);
    }

    private function isRelated(Relationship $reference, Record $record): bool
    {
        if ($reference->getReferencedSchema() !== $record->getSchema()) {
            return false;
        }

        $keys = $reference->getFields();
        $fields = $reference->getReferencedFields();

        foreach ($keys as $index => $key) {
            if ((string) $this->values[$key] !== (string) $record->values[$fields[$index]]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Record[]
     */
    public function getMappedRecords(): array
    {
        /** @var Record[] $records */
        $records = [spl_object_id($this) => $this];

        do {
            foreach (current($records)->references as $relation) {
                foreach ($relation as $record) {
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
