<?php

namespace Simply\Database;

/**
 * A class the defines that possible interactions with a database record.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Model
{
    /** @var Record The database record for the model */
    protected $record;

    /**
     * Model constructor.
     * @param Record $record The database record for the model
     */
    protected function __construct(Record $record)
    {
        $this->record = $record;
    }

    /**
     * Initializes the model with the given database record instead of calling the default constructor.
     * @param Record $record The database record for the model
     * @return Model A new initialized model with the given database record
     */
    public static function createFromDatabaseRecord(Record $record): self
    {
        /** @var Model $model */
        $model = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $model->record = $record;

        return $model;
    }

    /**
     * Returns the database record for the model.
     * @return Record The database record for the model
     */
    public function getDatabaseRecord(): Record
    {
        return $this->record;
    }
}
