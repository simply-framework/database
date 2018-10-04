<?php

namespace Simply\Database;

use Simply\Database\Connection\MySqlConnection;
use Simply\Database\Test\MockPdoStatement;
use Simply\Database\Test\TestCase\UnitTestCase;
use Simply\Database\Test\TestHouseModel;

/**
 * QueryTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class QueryTest extends UnitTestCase
{
    public function testGenerateFromInvalidSchema(): void
    {
        $query = $this->getQuery('', []);

        $query = $query->withSchema($this->getPersonSchema(), 'person');
        $query = $query->withoutSchemas();

        $this->expectException(\InvalidArgumentException::class);
        $query->fetchModels('person');
    }

    public function testGenerateFromOneSchema(): void
    {
        $data = [['h_id' => 1, 'h_street' => 'Street Name']];
        $query = $this->getQuery('SELECT {h.fields} FROM {h.table}', $data)
            ->withSchema($this->getPersonSchema()->getRelationship('home')->getReferencedSchema(), 'h');

        /** @var TestHouseModel[] $houses */
        $houses = $query->fetchModels();

        $this->assertCount(1, $houses);
        $this->assertSame('Street Name', $houses[0]->getStreet());
    }

    public function testFetchCallback(): void
    {
        $data = [['age' => '5'], ['age' => '3']];

        $ages = $this->getQuery('SELECT age FROM {table}', $data)
            ->withSchema($this->getPersonSchema())
            ->fetchCallback(function (array $row): int {
                return $row['age'];
            });

        $this->assertSame([5, 3], $ages);
    }

    public function testNoParametersPassed(): void
    {
        $schema = $this->getPersonSchema();

        $connection = $this->getMockBuilder(MySqlConnection::class)
            ->disableOriginalConstructor()
            ->setMethods(['query'])
            ->getMock();
        $connection->expects($this->once())
            ->method('query')
            ->with("SELECT age FROM `{$schema->getTable()}`", [])
            ->willReturn($this->getMockStatement([]));

        $query = new Query($connection, 'SELECT age FROM {table}');
        $query = $query->withSchema($schema);
        $query = $query->withParameters(['name' => 'value']);
        $query = $query->withoutParameters();

        $this->assertCount(0, $query->fetchRows());
    }

    private function getQuery(string $sql, array $rows): Query
    {
        $connection = $this->getMockBuilder(MySqlConnection::class)
            ->disableOriginalConstructor()
            ->setMethods(['query'])
            ->getMock();
        $connection->expects($this->atMost(1))->method('query')->willReturn($this->getMockStatement($rows));

        return new Query($connection, $sql);
    }

    public function getMockStatement(array $rows): \PDOStatement
    {
        $statement = $this->getMockBuilder(MockPdoStatement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $statement->expects($this->atMost(1))->method('getIterator')->willReturn(new \ArrayIterator($rows));

        return $statement;
    }
}
