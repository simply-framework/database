<?php

namespace Simply\Database\Test;

use Simply\Database\Model;
use Simply\Database\Record;

/**
 * TestHouseModel.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TestHouseModel extends Model
{
    public function __construct(TestHouseSchema $schema, string $street)
    {
        $record = new Record($schema, $this);
        $record['street'] = $street;

        parent::__construct($record);
    }

    public function getStreet(): string
    {
        return $this->record['street'];
    }

    public function movePeople(array $people): void
    {
        foreach ($people as $person) {
            $this->record->addAssociation('residents', $person);
        }
    }

    /**
     * @return TestHouseModel[]
     */
    public function getResidents(): array
    {
        return $this->record->getRelatedModels('residents');
    }
}