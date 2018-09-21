<?php

namespace Simply\Database;

/**
 * StaticSchema.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class StaticSchema extends Schema
{
    /** @var string */
    protected static $model = '';

    /** @var string */
    protected static $table = '';

    /** @var string[]|string */
    protected static $primaryKey = [];

    /** @var string[] */
    protected static $fields = [];

    /** @var array[] */
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