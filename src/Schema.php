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
    /** @var Relationship[] */
    private $relationshipCache;

    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->relationshipCache = [];
        $this->container = $container;
    }

    abstract public function getModel(): string;
    abstract public function getTable(): string;

    /**
     * @return string[]
     */
    abstract public function getPrimaryKey(): array;

    /**
     * @return string[]
     */
    abstract public function getFields(): array;

    /**
     * @return array[]
     */
    abstract public function getRelationshipDefinitions(): array;

    /**
     * @return Relationship[]
     */
    public function getRelationships(): array
    {
        $relationships = [];

        foreach (array_keys($this->getRelationshipDefinitions()) as $name) {
            $relationships[$name] = $this->getRelationship($name);
        }

        return $relationships;
    }

    public function getRelationship(string $name): Relationship
    {
        if (isset($this->relationshipCache[$name])) {
            return $this->relationshipCache[$name];
        }

        $definition = $this->getRelationshipDefinitions()[$name] ?? null;

        if (empty($definition)) {
            throw new \InvalidArgumentException("Invalid relationship '$name'");
        }

        $key = \is_array($definition['key']) ? $definition['key'] : [$definition['key']];
        $schema = $this->loadSchema($definition['schema']);
        $fields = \is_array($definition['field']) ? $definition['field'] : [$definition['field']];
        $unique = empty($definition['unique']) ? false : true;

        $this->relationshipCache[$name] = new Relationship($name, $this, $key, $schema, $fields, $unique);

        return $this->relationshipCache[$name];
    }

    private function loadSchema(string $name): Schema
    {
        return $this->container->get($name);
    }

    public function createRecord(Model $model = null): Record
    {
        return new Record($this, $model);
    }

    public function createRecordFromValues(array $values): Record
    {
        $record = $this->createRecord();
        $record->setDatabaseValues($values);

        return $record;
    }

    public function createRecordFromRow(array $row, string $prefix = ''): Record
    {
        if ($prefix === '') {
            return $this->createRecordFromValues(array_intersect_key($row, array_flip($this->getFields())));
        }

        $values = [];

        foreach ($this->getFields() as $field) {
            $prefixed = $prefix . $field;

            if (array_key_exists($prefixed, $row)) {
                $values[$field] = $row[$prefixed];
            }
        }

        return $this->createRecordFromValues($values);
    }

    public function createModel(Record $record): Model
    {
        /** @var Model $model */
        $model = $this->getModel();

        return $model::createFromDatabaseRecord($record);
    }

    public function createModelFromRow(array $row, string $prefix = '', array $relationships = []): Model
    {
        $record = $this->createRecordFromRow($row, $prefix);

        foreach ($relationships as $key => $name) {
            $relationship = $this->getRelationship($name);
            $schema = $relationship->getReferencedSchema();
            $relationship->fillSingleRecord($record, $schema->createRecordFromRow($row, $key));
        }

        return $record->getModel();
    }
}
