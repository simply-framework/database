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
    private $container;

    /** @var string|Model */
    protected $model;

    protected $table;

    protected $primaryKey;

    protected $fields;

    protected $references;

    private $referenceCache;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->referenceCache = [];
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimaryKey(): array
    {
        return (array) $this->primaryKey;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getReference(string $name): Reference
    {
        if (isset($this->referenceCache[$name])) {
            return $this->referenceCache[$name];
        }

        if (!isset($this->references[$name])) {
            throw new \InvalidArgumentException("Invalid reference '$name'");
        }

        $this->referenceCache[$name] = new Reference(
            $this,
            (array) $this->references[$name]['key'],
            $this->getSchema($this->references[$name]['schema']),
            (array) $this->references[$name]['field']
        );

        return $this->referenceCache[$name];
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

        return $this->getModel($this->getRecord($values));
    }
}
