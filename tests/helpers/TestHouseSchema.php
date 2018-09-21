<?php

namespace Simply\Database\Test;

use Simply\Database\StaticSchema;

/**
 * TestHouseSchema.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TestHouseSchema extends StaticSchema
{
    protected static $model = TestHouseModel::class;

    protected static $table = 'phpunit_tests_house';

    protected static $primaryKey = 'id';

    protected static $fields = ['id', 'street'];

    protected static $relationships = [
        'residents' => [
            'key' => 'id',
            'schema' => TestPersonSchema::class,
            'field' => 'home_id',
        ],
    ];
}