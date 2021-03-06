<?php

namespace Simply\Database\Test;

use Simply\Database\Model;

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
        $record = $schema->createRecord($this);

        $record['first_name'] = $firstName;
        $record['last_name'] = $lastName;
        $record['age'] = $age;
        $record['weight'] = null;
        $record['license'] = false;

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

    public function getWeight(): ?float
    {
        return $this->record['weight'];
    }

    public function increaseAge(): void
    {
        $this->record['age'] = $this->getAge() + 1;
    }

    public function setWeight(float $weight): void
    {
        $this->record['weight'] = $weight;
    }

    public function giveLicense(): void
    {
        $this->record['license'] = true;
    }

    /**
     * @return TestPersonModel[]
     */
    public function getParents(): array
    {
        return $this->record->getRelatedModelsByProxy('parents', 'parent');
    }

    /**
     * @return TestPersonModel[]
     */
    public function getChildren(): array
    {
        return $this->record->getRelatedModelsByProxy('children', 'child');
    }

    public function marry(self $person): void
    {
        $this->record->associate('spouse', $person);
        $person->record->associate('spouse', $this);
    }

    public function getSpouse(): ?self
    {
        return $this->record->getRelatedModel('spouse');
    }

    public function getHome(): TestHouseModel
    {
        return $this->record->getRelatedModel('home');
    }
}
