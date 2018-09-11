<?php

namespace Simply\Database;

use PHPUnit\Framework\TestCase;
use Simply\Container\Container;
use Simply\Database\Test\TestPersonSchema;

/**
 * SchemaTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class SchemaTest extends TestCase
{
    public function testInvalidRererence()
    {
        $container = new Container();
        $schema = new TestPersonSchema($container);

        $this->expectException(\InvalidArgumentException::class);
        $schema->getReference('not-a-valid-reference');
    }
}
