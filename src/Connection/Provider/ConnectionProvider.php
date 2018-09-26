<?php

namespace Simply\Database\Connection\Provider;

/**
 * ConnectionProvider.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
interface ConnectionProvider
{
    public function getConnection(): \PDO;
}