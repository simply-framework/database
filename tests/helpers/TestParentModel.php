<?php

namespace Simply\Database\Test;

use Simply\Database\Model;
use Simply\Database\Record;

/**
 * TestParentModel.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TestParentModel extends Model
{
    public function __construct(TestParentSchema $schema, TestPersonModel $child, TestPersonModel $parent)
    {
        $record = new Record($schema, $this);

        $record->associate('child', $child);
        $record->associate('parent', $parent);

        parent::__construct($record);
    }
}
