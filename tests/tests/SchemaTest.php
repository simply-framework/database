<?php

namespace Simply\Database;

use PHPUnit\Framework\TestCase;
use Simply\Container\Container;
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

        return $schema;
    }
    public function testInvalidRererence()
    {
        $schema = $this->getPersonSchema();

        $this->expectException(\InvalidArgumentException::class);
        $schema->getReference('not-a-valid-reference');
    }

    public function testSchemaEquivalence()
    {
        $schema = $this->getPersonSchema();

        $reference = $schema->getReference('parents');

        $this->assertSame($schema, $reference->getSchema());
        $this->assertSame($schema, $reference->getReferencedSchema()->getReference('parent')->getReferencedSchema());
    }

    public function testInvalidNumberOfFields(): void
    {
        $schema = $this->getPersonSchema();

        $this->expectException(\InvalidArgumentException::class);
        new Reference($schema, ['first_name', 'last_name'], $schema, ['first_name']);
    }

    public function testInvalidReferringFields(): void
    {
        $schema = $this->getPersonSchema();

        $this->expectException(\InvalidArgumentException::class);
        new Reference($schema, ['father_id'], $schema, ['id']);
    }

    public function testInvalidReferredFields(): void
    {
        $schema = $this->getPersonSchema();

        $this->expectException(\InvalidArgumentException::class);
        new Reference($schema, ['id'], $schema, ['mother_id']);
    }
}
