<?php

namespace Simply\Database;

use Psr\Container\ContainerInterface;

/**
 * Schema.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Schema
{
    private $container;

    /** @var string|Model */
    protected $model;

    protected $table;

    protected $primaryKeys;

    protected $fields;

    protected $references;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimaryKeys(): array
    {
        return $this->primaryKeys;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getReference(string $name): Reference
    {
        if (!isset($this->references[$name])) {
            throw new \InvalidArgumentException("Invalid reference '$name'");
        }

        return new Reference(
            $this,
            $this->references[$name]['keys'],
            $this->getSchema($this->references[$name]['schema']),
            $this->references[$name]['fields']
        );
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

            if (isset($row[$prefixed])) {
                $values[$field] = $row[$prefixed];
            }
        }

        return $this->getModel($this->getRecord($values));
    }
}
