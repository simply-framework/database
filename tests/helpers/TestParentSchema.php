<?php

namespace Simply\Database\Test;

use Simply\Database\StaticSchema;

/**
 * TestParentJoinSchema.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TestParentSchema extends StaticSchema
{
    protected static $model = TestParentModel::class;

    protected static $table = 'phpunit_tests_parent';

    protected static $primaryKey = ['parent_id', 'child_id'];

    protected static $fields = ['parent_id', 'child_id'];

    protected static $relationships = [
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
