<?php

namespace Simply\Database\Test\TestCase;

use PHPUnit\Framework\TestCase;
use Simply\Container\Container;
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
}