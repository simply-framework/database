<?php

namespace Simply\Database\Connection\Provider;

/**
 * LazyConnectionProvider.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class GenericConnectionProvider implements ConnectionProvider
{
    private $dsn;
    private $username;
    private $password;
    private $options;
    private $pdo;

    public function __construct(string $dsn, string $username, string $password, array $options)
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
    }

    public function getConnection(): \PDO
    {
        if (empty($this->pdo)) {
            $this->pdo = $this->initializeConnection();
        }

        return $this->pdo;
    }

    protected function initializeConnection(): \PDO
    {
        return new \PDO($this->dsn, $this->username, $this->password, $this->options);
    }
}
