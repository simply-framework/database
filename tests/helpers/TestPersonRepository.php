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
class TestPersonRepository extends Repository
{
    private $schema;

    public function __construct(Connection $connection, TestPersonSchema $schema)
    {
        parent::__construct($connection);

        $this->schema = $schema;
    }

    public function createPerson(string $firstName, string $lastName, int $age): TestPersonModel
    {
        return new TestPersonModel($this->schema, $firstName, $lastName, $age);
    }

    public function findById(int $id): ?TestPersonModel
    {
        return $this->findByPrimaryKey($this->schema, $id);
    }

    public function savePerson(TestPersonModel $model): void
    {
        $this->save($model);
    }

    public function deletePerson(TestPersonModel $model): void
    {
        $this->delete($model);
    }
}