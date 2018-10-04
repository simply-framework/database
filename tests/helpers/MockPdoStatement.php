<?php

namespace Simply\Database\Test;

/**
 * MockPdoStatement.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class MockPdoStatement extends \PDOStatement implements \IteratorAggregate
{
    public function getIterator()
    {
        throw new \LogicException('This method should be mocked');
    }
}
