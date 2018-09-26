<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;
use Simply\Database\Test\TestCase\UnitTestCase;

/**
 * RepositoryTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class RepositoryTest extends UnitTestCase
{
    public function testMissingPrimaryKey(): void
    {
        $connection = $this->createMock(Connection::class);
        $repository = new class($connection) extends Repository {
        };

        $method = new \ReflectionMethod($repository, 'findByPrimaryKey');
        $method->setAccessible(true);

        $schema = $this->getPersonSchema()->getRelationship('parents')->getReferencedSchema();

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($repository, $schema, [1]);
    }

    public function testInvalidPrimaryKeyValue(): void
    {
        $connection = $this->createMock(Connection::class);
        $repository = new class($connection) extends Repository {
        };

        $method = new \ReflectionMethod($repository, 'findByPrimaryKey');
        $method->setAccessible(true);

        $schema = $this->getPersonSchema()->getRelationship('parents')->getReferencedSchema();

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($repository, $schema, [1, [2, 3]]);
    }

    public function testMultipleRecordsByPrimaryKey(): void
    {
        $result = $this->createMock(\PDOStatement::class);
        $result->expects($this->once())->method('fetchAll')->willReturn([['id' => 1], ['id' => 1]]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('select')->willReturn($result);

        $repository = new class($connection) extends Repository {
        };

        $schema = $this->getPersonSchema();

        $method = new \ReflectionMethod($repository, 'findByPrimaryKey');
        $method->setAccessible(true);

        $this->expectException(\UnexpectedValueException::class);
        $method->invoke($repository, $schema, 1);
    }
}
