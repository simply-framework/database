<?php

namespace Simply\Database;

use Simply\Container\Container;
use Simply\Database\Exception\InvalidRelationshipException;
use Simply\Database\Test\TestCase\UnitTestCase;

/**
 * SchemaTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class SchemaTest extends UnitTestCase
{
    public function testInvalidRelationship(): void
    {
        $schema = $this->getPersonSchema();

        $this->expectException(InvalidRelationshipException::class);
        $schema->getRelationship('not-a-valid-relationship');
    }

    public function testSchemaEquivalence(): void
    {
        $schema = $this->getPersonSchema();

        $relationship = $schema->getRelationship('parents');

        $this->assertSame($schema, $relationship->getSchema());
        $this->assertSame(
            $schema,
            $relationship->getReferencedSchema()->getRelationship('parent')->getReferencedSchema()
        );
    }

    public function testInvalidNumberOfFields(): void
    {
        $schema = $this->getPersonSchema();

        $this->expectException(\InvalidArgumentException::class);
        new Relationship('test', $schema, ['first_name', 'last_name'], $schema, ['first_name'], false);
    }

    public function testInvalidReferringFields(): void
    {
        $schema = $this->getPersonSchema();

        $this->expectException(\InvalidArgumentException::class);
        new Relationship('test', $schema, ['father_id'], $schema, ['id'], false);
    }

    public function testInvalidReferencedFields(): void
    {
        $schema = $this->getPersonSchema();

        $this->expectException(\InvalidArgumentException::class);
        new Relationship('test', $schema, ['id'], $schema, ['mother_id'], false);
    }

    public function testMissingReverseRelationship(): void
    {
        $container = new Container();
        $schema = new class($container) extends StaticSchema {
            protected static $model = 'TestModel';
            protected static $primaryKey = 'id';
            protected static $fields = ['id', 'parent_id'];
            protected static $table = 'test';
            protected static $relationships = [
                'parent' => [
                    'key' => 'parent_id',
                    'schema' => 'TestSchema',
                    'field' => 'id',
                ],
            ];
        };

        $container['TestSchema'] = $schema;
        $relationship = $schema->getRelationship('parent');

        $this->expectException(InvalidRelationshipException::class);
        $relationship->getReverseRelationship();
    }

    public function testMultipleReverseRelationships(): void
    {
        $container = new Container();
        $schema = new class($container) extends StaticSchema {
            protected static $model = 'TestModel';
            protected static $primaryKey = 'id';
            protected static $fields = ['id', 'parent_id'];
            protected static $table = 'test';
            protected static $relationships = [
                'parent' => [
                    'key' => 'parent_id',
                    'schema' => 'TestSchema',
                    'field' => 'id',
                ],
                'child' => [
                    'key' => 'id',
                    'schema' => 'TestSchema',
                    'field' => 'parent_id',
                ],
                'son' => [
                    'key' => 'id',
                    'schema' => 'TestSchema',
                    'field' => 'parent_id',
                ],
            ];
        };

        $container['TestSchema'] = $schema;
        $relationship = $schema->getRelationship('parent');

        $this->expectException(InvalidRelationshipException::class);
        $relationship->getReverseRelationship();
    }

    public function testTryingToFillCompositeForeignKey(): void
    {
        $schema = $this->getCompositeForeignKeySchema();
        $relationship = $schema->getRelationship('replaced');

        $this->expectException(InvalidRelationshipException::class);
        $relationship->fillRelationship([], []);
    }

    public function testTryingToFillWrongReferringSchema(): void
    {
        $schema = $this->getPersonSchema();
        $relationship = $schema->getRelationship('home');

        $house = $relationship->getReferencedSchema()->createRecord();

        $this->expectException(\InvalidArgumentException::class);
        $relationship->fillRelationship([$house], []);
    }

    public function testTryingToFillWrongReferredSchema(): void
    {
        $schema = $this->getPersonSchema();
        $relationship = $schema->getRelationship('home');

        $person = $schema->createRecord();
        $otherPerson = $schema->createRecord();

        $this->expectException(\InvalidArgumentException::class);
        $relationship->fillRelationship([$person], [$otherPerson]);
    }

    public function testTryFillingMultipleToUniqueRelationship(): void
    {
        $schema = $this->getPersonSchema();
        $relationship = $schema->getRelationship('spouse_reverse');

        $person = $schema->createRecord();
        $person['id'] = 1;

        $wife = $schema->createRecord();
        $wife['spouse_id'] = 1;

        $husband = new Record($schema);
        $husband['spouse_id'] = 1;

        $this->expectException(InvalidRelationshipException::class);
        $relationship->fillRelationship([$person], [$wife, $husband]);
    }

    public function testTryFillingMultipleToPrimaryRelationship(): void
    {
        $schema = $this->getPersonSchema();
        $relationship = $schema->getRelationship('spouse');

        $person = $schema->createRecord();
        $person['spouse_id'] = 1;

        $wife = $schema->createRecord();
        $wife['id'] = 1;

        $husband = $schema->createRecord();
        $husband['id'] = 1;

        $this->expectException(InvalidRelationshipException::class);
        $relationship->fillRelationship([$person], [$wife, $husband]);
    }

    public function testFillingWithNullValues(): void
    {
        $schema = $this->getPersonSchema();
        $relationship = $schema->getRelationship('spouse');

        $personA = $schema->createRecord();
        $personA['id'] = 1;
        $personA['spouse_id'] = 2;

        $personB = $schema->createRecord();
        $personB['id'] = 2;
        $personB['spouse_id'] = 1;

        $personC = $schema->createRecord();

        $relationship->fillRelationship([$personA, $personB, $personC], [$personA, $personB, $personC]);

        $this->assertSame([$personB], $personA->getReferencedRecords('spouse'));
        $this->assertSame([$personA], $personB->getReferencedRecords('spouse'));
        $this->assertSame([], $personC->getReferencedRecords('spouse'));
    }

    public function testTryFillingNonUniqueSingleRelatinoship()
    {
        $schema = $this->getPersonSchema();
        $values = [];

        foreach ($schema->getFields() as $field) {
            $values['p_' . $field] = null;
        }

        $values['p_id'] = 1;

        foreach ($schema->getRelationship('parents')->getReferencedSchema()->getFields() as $field) {
            $values['r_' . $field] = null;
        }

        $values['r_child_id'] = 1;

        $this->expectException(\LogicException::class);
        $schema->createModelFromRow($values, 'p_', ['r_' => 'parents']);
    }

    public function testTryFillingANonRelatedRecord()
    {
        $schema = $this->getPersonSchema();
        $values = [];

        foreach ($schema->getFields() as $field) {
            $values['p_' . $field] = null;
            $values['s_' . $field] = null;
        }

        $values['p_id'] = 1;
        $values['p_spouse_id'] = 2;
        $values['s_id'] = 3;

        $this->expectException(\LogicException::class);
        $schema->createModelFromRow($values, 'p_', ['s_' => 'spouse']);
    }

    public function testCreatingModelWithWrongSchema()
    {
        $schema = $this->getPersonSchema();
        $record = $schema->getRelationship('home')->getReferencedSchema()->createRecord();

        $this->expectException(\InvalidArgumentException::class);
        $schema->createModel($record);
    }
}
