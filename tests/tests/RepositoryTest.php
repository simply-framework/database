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
}