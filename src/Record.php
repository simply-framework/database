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

    private $values;

    private $changed;

    private $state;

    private $relations;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
        $this->values = array_fill_keys($schema->getFields(), null);
        $this->state = self::STATE_INSERT;
        $this->changed = [];
    }

    public function getPrimaryKeys(): array
    {
        return array_intersect_key($this->values, array_flip($this->schema->getPrimaryKeys()));
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
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getModel(): Model
    {
        return $this->schema->getModel($this);
    }

    public function getReference(string $name): array
    {
        if (!isset($this->relations[$name])) {
            throw new \RuntimeException("Cannot access relation '$name' that has not been provided");
        }

        return $this->relations[$name];
    }

    public function fillReference(string $name, array $records): void
    {
        $relation = $this->getSchema()->getReference($name);

        foreach ($records as $record) {
            if ($this->isRelated($relation, $record)) {
                throw new \InvalidArgumentException('The provided records are not related to this record');
            }
        }

        if (\count($records) > 1 && $relation->isSingleRelationship()) {
            throw new \InvalidArgumentException('The relationship cannot reference more than a single record');
        }

        $this->relations[$name] = array_values($records);
    }

    private function isRelated(Reference $relation, Record $record): bool
    {
        if ($relation->getReferencedSchema() !== $record->getSchema()) {
            return false;
        }

        $keys = $relation->getFields();
        $references = $relation->getReferencedFields();

        while ($keys) {
            if (!$relation->matchValues($this->values[array_pop($keys)], $record->values[array_pop($references)])) {
                return false;
            }
        }

        return true;
    }

    public function setDatabaseValues(array $row)
    {
        if (array_keys($row) !== array_keys($this->values)) {
            throw new \InvalidArgumentException('Invalid set of record database values provided');
        }

        $this->values = $row;
        $this->state = self::STATE_UPDATE;
        $this->changed = [];
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

        if ($this->state === self::STATE_UPDATE && \in_array($offset, $this->schema->getPrimaryKeys(), true)) {
            throw new \RuntimeException('Cannot change values of primary keys for saved records');
        }

        $this->values[$offset] = $value;
        $this->changed[$offset] = true;
    }

    public function offsetUnset($offset)
    {
        $this->offsetSet($offset, null);
    }


}
