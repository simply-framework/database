<?php

namespace Simply\Database\Test;

use Simply\Database\Connection\Connection;
use Simply\Database\Repository;

/**
 * TestRepository.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TestRepository extends Repository
{
    private $personSchema;
    private $parentSchema;
    private $houseSchema;

    public function __construct(
        Connection $connection,
        TestPersonSchema $personSchema,
        TestParentSchema $parentSchema,
        TestHouseSchema $houseSchema
    ) {
        parent::__construct($connection);

        $this->personSchema = $personSchema;
        $this->parentSchema = $parentSchema;
        $this->houseSchema = $houseSchema;
    }

    public function createPerson(string $firstName, string $lastName, int $age): TestPersonModel
    {
        return new TestPersonModel($this->personSchema, $firstName, $lastName, $age);
    }

    public function createHouse(string $street): TestHouseModel
    {
        return new TestHouseModel($this->houseSchema, $street);
    }

    public function findById(int $id): ?TestPersonModel
    {
        return $this->findByPrimaryKey($this->personSchema, $id);
    }

    /**
     * @param string $name
     * @return TestPersonModel[]
     */
    public function findByFirstName(string $name): array
    {
        return $this->find($this->personSchema, ['first_name' => $name]);
    }

    public function findByAnyFirstName(iterable $names): array
    {
        return $this->find($this->personSchema, ['first_name' => array_map(function (string $name): string {
            return $name;
        }, $names)]);
    }

    public function findByLastName(string $name): array
    {
        return $this->find($this->personSchema, ['last_name' => $name]);
    }

    public function findAllAlphabetically(int $limit = null, bool $ascending = true): array
    {
        $order = $ascending ? Connection::ORDER_ASCENDING : Connection::ORDER_DESCENDING;
        return $this->find($this->personSchema, [], ['last_name' => $order], $limit);
    }

    public function findOneByWeight(?float $weight): ?TestPersonModel
    {
        return $this->findOne($this->personSchema, ['weight' => $weight]);
    }

    public function findByAnyWeight(array $weights): array
    {
        return $this->find($this->personSchema, ['weight' => array_map(function (?float $weight): ?float {
            return $weight;
        }, $weights)]);
    }

    public function findByHasLicense(bool $hasLicense): array
    {
        return $this->find($this->personSchema, ['license' => $hasLicense]);
    }

    public function savePerson(TestPersonModel $model): void
    {
        $this->save($model);
    }

    public function saveHouse(TestHouseModel $model): void
    {
        $this->save($model);
    }

    public function makeParent(TestPersonModel $child, TestPersonModel $parent)
    {
        $relationship = new TestParentModel($this->parentSchema, $child, $parent);
        $this->save($relationship);
    }

    public function loadFamily(array $people)
    {
        $this->fillRelationships($people, [
            'parents.parent.children.child',
            'children.child.parents.parent',
            'spouse.spouse',
            'home.residents',
        ]);
    }

    public function deletePerson(TestPersonModel $model): void
    {
        $this->delete($model);
    }
}