<?php

namespace Simply\Database\Connection\Provider;

/**
 * A generic lazy loading connector that can take any kind of PDO data source name.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class GenericConnectionProvider implements ConnectionProvider
{
    /** @var string The data source name for the PDO connection */
    private $dsn;

    /** @var string The username for the PDO connection */
    private $username;

    /** @var string The password for the PDO connection */
    private $password;

    /** @var array Initial PDO options provided when initializing the connection */
    private $options;

    /** @var \PDO The active PDO connection */
    private $pdo;

    /**
     * GenericConnectionProvider constructor.
     * @param string $dsn The data source name for the PDO connection
     * @param string $username The username for the PDO connection
     * @param string $password The password for the PDO connection
     * @param array $options Initial PDO options provided when initializing the connection
     */
    public function __construct(string $dsn, string $username, string $password, array $options)
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
    }

    public function getConnection(): \PDO
    {
        if (!isset($this->pdo)) {
            $this->pdo = $this->initializeConnection();
        }

        return $this->pdo;
    }

    /**
     * Initializes the PDO connection when first requested.
     * @return \PDO The initialized PDO connection
     */
    protected function initializeConnection(): \PDO
    {
        return new \PDO($this->dsn, $this->username, $this->password, $this->options);
    }
}
