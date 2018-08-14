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
    private $schema;

    private $values;

    private $new;

    private $relations;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
        $this->values = array_fill_keys($schema->getFields(), null);
        $this->new = true;
    }

    public function isNew(): bool
    {
        return $this->new;
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getModel(): Model
    {
        return $this->schema->getModel($this);
    }

    public function getRelation(string $name): array
    {
        if (!isset($this->relations[$name])) {
            throw new \RuntimeException("Cannot access relation '$name' that has not been provided");
        }

        return $this->relations[$name];
    }

    public function setRelation(string $name, array $records): void
    {
        $relation = $this->getSchema()->getRelation($name);

        foreach ($records as $record) {
            if ($this->isRelated($relation, $record)) {
                throw new \InvalidArgumentException('The provided records are not related to this record');
            }
        }

        if (\count($records) > 1 && $relation->isSingleRelation()) {
            throw new \InvalidArgumentException('The relation cannot reference more than a single record');
        }

        $this->relations[$name] = array_values($records);
    }

    private function isRelated(Relation $relation, Record $record): bool
    {
        $schema = $relation->getReferencedSchema();

        if ($schema !== $record->getSchema() && \get_class($schema) !== \get_class($record->getSchema())) {
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
        $this->new = false;
    }

    public function getDatabaseValues(): array
    {
        return $this->values;
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
    }

    public function offsetUnset($offset)
    {
        $this->offsetSet($offset, null);
    }


}
