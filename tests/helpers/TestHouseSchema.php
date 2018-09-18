<?php

namespace Simply\Database\Test;

use Simply\Database\Schema;

/**
 * TestHouseSchema.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TestHouseSchema extends Schema
{
    protected $model = TestHouseModel::class;

    protected $table = 'phpunit_tests_house';

    protected $primaryKey = 'id';

    protected $fields = ['id', 'street'];

    protected $relationships = [
        'residents' => [
            'key' => 'id',
            'schema' => TestPersonSchema::class,
            'field' => 'home_id',
        ],
    ];
}