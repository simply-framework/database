<?php

namespace Simply\Database;

use Psr\Container\ContainerInterface;
use Simply\Database\Exception\InvalidRelationshipException;

/**
 * A schema for a database table.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class Schema
{
    /** @var Relationship[] Cached initialized relationships for the schema */
    private $relationshipCache;

    /** @var ContainerInterface Container used to load schemas for relationships */
    private $container;

    /**
     * Schema constructor.
     * @param ContainerInterface $container Container used to load schemas for relationships
     */
    public function __construct(ContainerInterface $container)
    {
        $this->relationshipCache = [];
        $this->container = $container;
    }

    /**
     * Returns the model class used to operate on the records from this schema.
     * @return string The model class used to operate on the records from this schema
     */
    abstract public function getModel(): string;

    /**
     * Returns the name of the table for the schema.
     * @return string Name of the table for the schema
     */
    abstract public function getTable(): string;

    /**
     * Returns a list fields that define the primary key for the schema.
     * @return string[] List fields that define the primary key for the schema
     */
    abstract public function getPrimaryKey(): array;

    /**
     * Returns the list of fields for the schema.
     * @return string[] List of fields for the schema
     */
    abstract public function getFields(): array;

    /**
     * Returns an associative list of relationship definitions for the schema.
     *
     * The returned array is an associative array of definitions, where each key defines the name of the relationship.
     *
     * Each definition must contain the following fields:
     * - key : Defines the field (or list of fields) in the schema that references fields in the referenced schema
     * - schema : Name of the schema in the container that the the relationship references
     * - field : Defines the field (or list of fields) in the referenced schema
     * - unique : If relationship can ever only refer to a single record (optional)
     *
     * If the unique value is missing or even if it's defined as `false`, the relationship will still be considered
     * unique if the referenced fields contain the entire primary key of the referenced schema (as it is not possible
     * to have multiple records with the same primary key).
     *
     * Note that each relationship must have an appropriate reverse relationship defined in the referenced schema.
     *
     * @return array[] Associative list of relationship definitions for the schema
     */
    abstract public function getRelationshipDefinitions(): array;

    /**
     * Returns a list of all relationships for the schema.
     * @return Relationship[] List of all relationships for the schema
     */
    public function getRelationships(): array
    {
        $relationships = [];

        foreach (array_keys($this->getRelationshipDefinitions()) as $name) {
            $relationships[] = $this->getRelationship($name);
        }

        return $relationships;
    }

    /**
     * Returns a single relationship with the given name.
     * @param string $name Name of the relationship
     * @return Relationship Relationship with the given name
     * @throws InvalidRelationshipException If a relationship with the given name does not exist
     */
    public function getRelationship(string $name): Relationship
    {
        if (isset($this->relationshipCache[$name])) {
            return $this->relationshipCache[$name];
        }

        $definition = $this->getRelationshipDefinitions()[$name] ?? null;

        if (empty($definition)) {
            throw new InvalidRelationshipException("Undefined relationship '$name'");
        }

        $key = \is_array($definition['key']) ? $definition['key'] : [$definition['key']];
        $schema = $this->loadSchema($definition['schema']);
        $fields = \is_array($definition['field']) ? $definition['field'] : [$definition['field']];
        $unique = empty($definition['unique']) ? false : true;

        $this->relationshipCache[$name] = new Relationship($name, $this, $key, $schema, $fields, $unique);

        return $this->relationshipCache[$name];
    }

    /**
     * Loads a schema with the given name from the container.
     * @param string $name Name of the schema
     * @return Schema The schema loaded from the container
     */
    private function loadSchema(string $name): self
    {
        return $this->container->get($name);
    }

    /**
     * Returns a new empty record for the schema.
     * @param Model|null $model A model to associate to the record, if initialized
     * @return Record A new empty record based on this schema
     */
    public function createRecord(Model $model = null): Record
    {
        return new Record($this, $model);
    }

    /**
     * Returns a new record with the given values for the fields.
     * @param array $values Values for the record fields
     * @return Record New record with given values
     */
    public function createRecordFromValues(array $values): Record
    {
        $record = $this->createRecord();
        $record->setDatabaseValues($values);

        return $record;
    }

    /**
     * Returns a new record with values taken from a result row with optional prefix for fields.
     * @param array $row The result row from a database query
     * @param string $prefix Prefix for the fields used by this schema
     * @return Record New record based on the fields taken from the result row
     */
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

    /**
     * Returns a new model that is associated to the given record.
     * @param Record $record The record associated to the model
     * @return Model New model based on the given record
     */
    public function createModel(Record $record): Model
    {
        if ($record->getSchema() !== $this) {
            throw new \InvalidArgumentException('The provided record must have a matching schema');
        }

        /** @var Model $model */
        $model = $this->getModel();

        return $model::createFromDatabaseRecord($record);
    }

    /**
     * Returns a new model with a new record that has values from the given result row with optional relationships.
     * @param array $row Result row from the database
     * @param string $prefix Prefix for the fields of this schema
     * @param array $relationships Names of unique relationships also provided in the result row
     * @return Model New model based on the values provided in the result row
     */
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
