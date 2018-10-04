<?php

namespace Simply\Database\Connection\Provider;

/**
 * Interface for objects that can initialize and provide the PDO instance for database connectors.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
interface ConnectionProvider
{
    /**
     * Provides the active PDO connection and initializes it if necessary.
     * @return \PDO An active PDO connection
     */
    public function getConnection(): \PDO;
}
