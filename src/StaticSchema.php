<?php

namespace Simply\Database;

/**
 * Schema implementation that provides the schema definition via static class properties.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class StaticSchema extends Schema
{
    /** @var string The model class for models associated to the records */
    protected static $model = '';

    /** @var string The name of the table for the schema */
    protected static $table = '';

    /** @var string[]|string The primary key field or list of fields the make up the composite primary key */
    protected static $primaryKey = [];

    /** @var string[] List of all the fields in the table */
    protected static $fields = [];

    /** @var array[] The relationship definitions for the schema */
    protected static $relationships = [];

    public function getModel(): string
    {
        return static::$model;
    }

    public function getTable(): string
    {
        return static::$table;
    }

    public function getPrimaryKey(): array
    {
        return \is_array(static::$primaryKey) ? static::$primaryKey : [static::$primaryKey];
    }

    public function getFields(): array
    {
        return static::$fields;
    }

    public function getRelationshipDefinitions(): array
    {
        return static::$relationships;
    }
}
