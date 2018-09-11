<?php

namespace Simply\Database\Test;

use Simply\Database\Schema;

/**
 * TestParentJoinSchema.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TestParentSchema extends Schema
{
    protected $model = TestParentModel::class;

    protected $table = 'phpunit_tests_parent';

    protected $primaryKey = ['parent_id', 'child_id'];

    protected $fields = ['parent_id', 'child_id'];

    protected $references = [
        'child' => [
            'key' => 'child_id',
            'schema' => TestPersonSchema::class,
            'field' => 'id',
        ],
        'parent' => [
            'key' => 'parent_id',
            'schema' => TestPersonSchema::class,
            'field' => 'id',
        ],
    ];
}