<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;
use Simply\Database\Exception\InvalidRelationshipException;
use Simply\Database\Test\TestCase\UnitTestCase;

/**
 * RelationshipFillerTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class RelationshipFillerTest extends UnitTestCase
{
    public function testEmptyRecordsList(): void
    {
        $connection = $this->createMock(Connection::class);
        $filler = new RelationshipFiller($connection);

        $connection->expects($this->never())->method('select');
        $filler->fill([], ['spouse']);
    }

    public function testMixedRecordSchemas(): void
    {
        $schema = $this->getPersonSchema();

        $person = $schema->createRecord();
        $person['id'] = 1;
        $person->updateState(Record::STATE_INSERT);

        $parent = $schema->getRelationship('parents')->getReferencedSchema()->createRecord();
        $parent['child_id'] = 1;
        $parent['parent_id'] = 1;
        $parent->updateState(Record::STATE_INSERT);

        $connection = $this->createMock(Connection::class);
        $filler = new RelationshipFiller($connection);

        $this->expectException(\InvalidArgumentException::class);
        $filler->fill([$person, $parent], ['spouse']);
    }

    public function testFillingWithCompositeForeignKeys(): void
    {
        $schema = $this->getCompositeForeignKeySchema();

        $order = $schema->createRecord();
        $order['order_id'] = 1;
        $order['product_id'] = 1;
        $order['replaced_order_id'] = 1;
        $order['replaced_product_id'] = 1;
        $order->updateState(Record::STATE_INSERT);

        $connection = $this->createMock(Connection::class);
        $filler = new RelationshipFiller($connection);

        $this->expectException(InvalidRelationshipException::class);
        $filler->fill([$order], ['replaced']);
    }

    public function testDuplicatedRecordsInList(): void
    {
        $schema = $this->getPersonSchema();

        $personA = $schema->createRecord();
        $personA['id'] = 1;
        $personA->updateState(Record::STATE_INSERT);

        $personB = $schema->createRecord();
        $personB['id'] = 1;
        $personB->updateState(Record::STATE_INSERT);

        $connection = $this->createMock(Connection::class);
        $filler = new RelationshipFiller($connection);

        $this->expectException(\RuntimeException::class);
        $filler->fill([$personA, $personB], ['spouse']);
    }

    public function testNoQueriesWhenCached()
    {
        $schema = $this->getPersonSchema();

        $personA = $schema->createRecord();
        $personA['id'] = 1;
        $personA['spouse_id'] = 2;
        $personA->updateState(Record::STATE_INSERT);

        $personB = $schema->createRecord();
        $personB['id'] = 2;
        $personB['spouse_id'] = 1;
        $personB->updateState(Record::STATE_INSERT);

        $connection = $this->createMock(Connection::class);
        $filler = new RelationshipFiller($connection);

        $connection->expects($this->never())->method('select');
        $filler->fill([$personA, $personB], ['spouse']);
    }
}
