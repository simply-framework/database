<?php

namespace Simply\Database\Test\TestCase;

use PHPUnit\Framework\TestCase;
use Simply\Container\Container;
use Simply\Database\Schema;
use Simply\Database\StaticSchema;
use Simply\Database\Test\TestHouseSchema;
use Simply\Database\Test\TestParentSchema;
use Simply\Database\Test\TestPersonSchema;

/**
 * UnitTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class UnitTestCase extends TestCase
{
    protected function getPersonSchema(): TestPersonSchema
    {
        $container = new Container();
        $schema = new TestPersonSchema($container);

        $container[TestPersonSchema::class] = $schema;
        $container[TestParentSchema::class] = new TestParentSchema($container);
        $container[TestHouseSchema::class] = new TestHouseSchema($container);

        return $schema;
    }

    protected function getCompositeForeignKeySchema(): Schema
    {
        $container = new Container();
        $schema = new class($container) extends StaticSchema {
            protected static $model = 'TestModel';
            protected static $primaryKey = ['order_id', 'product_id'];
            protected static $fields = ['order_id', 'product_id', 'replaced_order_id', 'replaced_product_id'];
            protected static $table = 'test';
            protected static $relationships = [
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
        return $schema;
    }
}