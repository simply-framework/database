<?php

namespace Simply\Database\Test;

use Simply\Database\Model;
use Simply\Database\Record;

/**
 * TestModel.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TestPersonModel extends Model
{
    public function __construct(TestPersonSchema $schema, string $firstName, string $lastName, int $age)
    {
        $record = new Record($schema);
        $record['first_name'] = $firstName;
        $record['last_name'] = $lastName;
        $record['age'] = $age;

        parent::__construct($record);
    }

    public function getId(): ?int
    {
        return $this->record['id'];
    }

    public function getFirstName(): string
    {
        return $this->record['first_name'];
    }

    public function getLastName(): string
    {
        return $this->record['last_name'];
    }

    public function getAge(): int
    {
        return $this->record['age'];
    }

    public function increaseAge(): void
    {
        $this->record['age'] = $this->getAge() + 1;
    }
}