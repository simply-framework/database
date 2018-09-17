<?php

namespace Simply\Database\Test;

use Simply\Database\Schema;

/**
 * TestSchema.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TestPersonSchema extends Schema
{
    protected $model = TestPersonModel::class;

    protected $table = 'phpunit_tests_person';

    protected $primaryKey = 'id';

    protected $fields = ['id', 'first_name', 'last_name', 'age', 'weight', 'license'];

    protected $relationships = [
        'parents' => [
            'key' => 'id',
            'schema' => TestParentSchema::class,
            'field' => 'child_id',
        ],
        'children' => [
            'key' => 'id',
            'schema' => TestParentSchema::class,
            'field' => 'parent_id',
        ],
    ];
}