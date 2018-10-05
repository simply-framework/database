<?php

namespace Simply\Database\Test;

use Simply\Database\StaticSchema;

/**
 * TestSchema.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TestPersonSchema extends StaticSchema
{
    protected static $model = TestPersonModel::class;

    protected static $table = 'phpunit_tests_person';

    protected static $primaryKey = 'id';

    protected static $fields = ['id', 'first_name', 'last_name', 'age', 'weight', 'license', 'spouse_id', 'home_id'];

    protected static $relationships = [
        'parents' => [
            'key' => 'id',
            'schema' => self::class,
            'field' => 'child_id',
        ],
        'children' => [
            'key' => 'id',
            'schema' => self::class,
            'field' => 'parent_id',
        ],
        'spouse' => [
            'key' => 'spouse_id',
            'schema' => self::class,
            'field' => 'id',
        ],
        'spouse_reverse' => [
            'key' => 'id',
            'schema' => self::class,
            'field' => 'spouse_id',
            'unique' => true,
        ],
        'home' => [
            'key' => 'home_id',
            'schema' => self::class,
            'field' => 'id',
        ],
    ];
}
