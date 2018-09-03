<?php

namespace Simply\Database\Test;

use PHPUnit\Framework\TestCase;
use Simply\Container\Container;
use Simply\Database\Connection\Connection;

/**
 * IntegrationTestCase.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class IntegrationTestCase extends TestCase
{
    /** @var Connection */
    private $connection;

    /** @var TestPersonSchema */
    protected $personSchema;

    abstract protected function createConnection(): Connection;
    abstract protected function setUpDatabase(Connection $connection): void;

    protected function setUp()
    {
        $container = new Container();
        $this->personSchema = new TestPersonSchema($container);

        $container[TestPersonSchema::class] = $this->personSchema;

        $this->connection = $this->createConnection();
        $this->setUpDatabase($this->connection);
    }

    public function testCrudOperations(): void
    {
        $repository = new TestPersonRepository($this->connection, $this->personSchema);

        $person = $repository->createPerson('Jane', 'Doe', 20);
        $repository->savePerson($person);

        $id = $person->getId();
        $this->assertNotNull($id);

        $person->increaseAge();
        $repository->savePerson($person);

        $saved = $repository->findById($id);

        $this->assertSame($id, $saved->getId());
        $this->assertSame('Jane', $saved->getFirstName());
        $this->assertSame('Doe', $saved->getLastName());
        $this->assertSame(21, $saved->getAge());

        $repository->deletePerson($saved);

        $this->assertNull($repository->findById($id));
    }
}