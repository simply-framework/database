<?php

namespace Simply\Database;

/**
 * Model.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Model
{
    protected $record;

    protected function __construct(Record $record)
    {
        $this->record = $record;
    }

    public static function createFromDatabaseRecord(Record $record): Model
    {
        /** @var Model $model */
        $model = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $model->record = $record;

        return $model;
    }

    public function getDatabaseRecord(): Record
    {
        return $this->record;
    }
}
