<?php

namespace Simply\Database;

use PHPUnit\Framework\TestCase;
use Simply\Container\Container;
use Simply\Database\Test\TestHouseSchema;
use Simply\Database\Test\TestParentSchema;
use Simply\Database\Test\TestPersonSchema;

/**
 * SchemaTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class SchemaTest extends TestCase
{
    private function getPersonSchema(): TestPersonSchema
    {
        $container = new Container();
        $schema = new TestPersonSchema($container);

        $container[TestPersonSchema::class] = $schema;
        $container[TestParentSchema::class] = new TestParentSchema($container);
        $container[TestHouseSchema::class] = new TestHouseSchema($container);

        return $schema;
    }
    public function testInvalidRelationship(): void
    {
        $schema = $this->getPersonSchema();

        $this->expectException(\InvalidArgumentException::class);
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
        $schema = new class($container) extends Schema {
            protected $model = 'TestModel';
            protected $primaryKey = 'id';
            protected $fields = ['id', 'parent_id'];
            protected $table = 'test';
            protected $relationships = [
                'parent' => [
                    'key' => 'parent_id',
                    'schema' => 'TestSchema',
                    'field' => 'id',
                ],
            ];
        };

        $container['TestSchema'] = $schema;
        $relationship = $schema->getRelationship('parent');

        $this->expectException(\RuntimeException::class);
        $relationship->getReverseRelationship();
    }

    public function testMultipleReverseRelationships(): void
    {
        $container = new Container();
        $schema = new class($container) extends Schema {
            protected $model = 'TestModel';
            protected $primaryKey = 'id';
            protected $fields = ['id', 'parent_id'];
            protected $table = 'test';
            protected $relationships = [
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

        $this->expectException(\RuntimeException::class);
        $relationship->getReverseRelationship();
    }

    public function testTryingToFillCompositeForeignKey(): void
    {
        $container = new Container();
        $schema = new class($container) extends Schema {
            protected $model = 'TestModel';
            protected $primaryKey = ['order_id', 'product_id'];
            protected $fields = ['order_id', 'product_id', 'replaced_order_id', 'replaced_product_id'];
            protected $table = 'test';
            protected $relationships = [
                'replacement' => [
                    'key' => ['order_id', 'product_id'],
                    'schema' => 'TestSchema',
                    'field' => ['replaced_order_id', 'replaced_product_id'],
                ],
                'replaced' => [
                    'key' => ['replaced_order_id', 'replaced_product_id'],
                    'schema' => 'TestSchema',
                    'field' => ['order_id', 'product_id'],
                ],
            ];
        };

        $container['TestSchema'] = $schema;
        $relationship = $schema->getRelationship('replaced');

        $this->expectException(\RuntimeException::class);
        $relationship->fillRelationship([], []);
    }

    public function testTryingToFillWrongReferringSchema(): void
    {
        $schema = $this->getPersonSchema();
        $relationship = $schema->getRelationship('home');

        $house = new Record($relationship->getReferencedSchema());

        $this->expectException(\InvalidArgumentException::class);
        $relationship->fillRelationship([$house], []);
    }

    public function testTryingToFillWrongReferredSchema(): void
    {
        $schema = $this->getPersonSchema();
        $relationship = $schema->getRelationship('home');

        $person = new Record($schema);
        $otherPerson = new Record($schema);

        $this->expectException(\InvalidArgumentException::class);
        $relationship->fillRelationship([$person], [$otherPerson]);
    }

    public function testTryFillingMultipleToUniqueRelationship(): void
    {
        $schema = $this->getPersonSchema();
        $relationship = $schema->getRelationship('spouse_reverse');

        $person = new Record($schema);
        $person['id'] = 1;

        $wife = new Record($schema);
        $wife['spouse_id'] = 1;

        $husband = new Record($schema);
        $husband['spouse_id'] = 1;

        $this->expectException(\InvalidArgumentException::class);
        $relationship->fillRelationship([$person], [$wife, $husband]);
    }

    public function testTryFillingMultipleToPrimaryRelationship(): void
    {
        $schema = $this->getPersonSchema();
        $relationship = $schema->getRelationship('spouse');

        $person = new Record($schema);
        $person['spouse_id'] = 1;

        $wife = new Record($schema);
        $wife['id'] = 1;

        $husband = new Record($schema);
        $husband['id'] = 1;

        $this->expectException(\InvalidArgumentException::class);
        $relationship->fillRelationship([$person], [$wife, $husband]);
    }

    public function testFillingWithNullValues(): void
    {
        $schema = $this->getPersonSchema();
        $relationship = $schema->getRelationship('spouse');

        $personA = new Record($schema);
        $personA['id'] = 1;
        $personA['spouse_id'] = 2;

        $personB = new Record($schema);
        $personB['id'] = 2;
        $personB['spouse_id'] = 1;

        $personC = new Record($schema);

        $relationship->fillRelationship([$personA, $personB, $personC], [$personA, $personB, $personC]);

        $this->assertSame([$personB], $personA->getReferencedRecords('spouse'));
        $this->assertSame([$personA], $personB->getReferencedRecords('spouse'));
        $this->assertSame([], $personC->getReferencedRecords('spouse'));
    }
}
