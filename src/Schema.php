<?php

namespace Simply\Database;

use Psr\Container\ContainerInterface;

/**
 * Schema.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class Schema
{
    /** @var string|Model */
    protected $model;

    protected $table;

    protected $primaryKey;

    protected $fields;

    protected $relationships = [];

    private $relationshipCache;

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->relationshipCache = [];
        $this->container = $container;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimaryKey(): array
    {
        return $this->primaryKey === null ? [] : (array) $this->primaryKey;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return Relationship[]
     */
    public function getRelationships(): array
    {
        if (array_diff_key($this->relationships, $this->relationshipCache) === []) {
            return $this->relationshipCache;
        }

        $relationships = [];

        foreach (array_keys($this->relationships) as $name) {
            $relationships[$name] = $this->getRelationship($name);
        }

        return $relationships;
    }

    public function getRelationship(string $name): Relationship
    {
        if (isset($this->relationshipCache[$name])) {
            return $this->relationshipCache[$name];
        }

        if (!isset($this->relationships[$name])) {
            throw new \InvalidArgumentException("Invalid relationship '$name'");
        }

        $this->relationshipCache[$name] = new Relationship(
            $name,
            $this,
            (array) $this->relationships[$name]['key'],
            $this->getSchema($this->relationships[$name]['schema']),
            (array) $this->relationships[$name]['field'],
            empty($this->relationships[$name]['unique']) ? false : true
        );

        return $this->relationshipCache[$name];
    }

    private function getSchema(string $name): Schema
    {
        return $this->container->get($name);
    }

    public function getRecord(array $values): Record
    {
        $record = new Record($this);
        $record->setDatabaseValues($values);

        return $record;
    }

    public function getModel(Record $record): Model
    {
        return $this->model::createFromDatabaseRecord($record);
    }

    public function createModel(array $row, string $prefix = ''): Model
    {
        $values = [];

        foreach ($this->getFields() as $field) {
            $prefixed = $prefix . $field;

            if (array_key_exists($prefixed, $row)) {
                $values[$field] = $row[$prefixed];
            }
        }

        return $this->getRecord($values)->getModel();
    }
}
