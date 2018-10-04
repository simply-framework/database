<?php

namespace Simply\Database\Connection\Provider;

/**
 * Basic connection provider for MySQL databases.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class MySqlConnectionProvider extends GenericConnectionProvider
{
    /**
     * MySqlConnectionProvider constructor.
     * @param string $hostname The hostname for the database with optional port or path to unix socket
     * @param string $database The name of the database to connect
     * @param string $username The username used for the connection
     * @param string $password The password for the username or empty string for none
     */
    public function __construct(string $hostname, string $database, string $username, string $password)
    {
        parent::__construct($this->getDataSourceName($hostname, $database), $username, $password, $this->getOptions());
    }

    /**
     * Returns the data source name based on the hostname and the database.
     * @param string $hostname The hostname for the connection
     * @param string $database The database for the connection
     * @return string The data source name string for the connection
     */
    protected function getDataSourceName(string $hostname, string $database): string
    {
        if (strncmp($hostname, '/', 1) === 0) {
            return sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $hostname, $database);
        }

        $parts = explode(':', $hostname, 2);

        if (\count($parts) === 1) {
            return sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $hostname, $database);
        }

        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $parts[0], $parts[1], $database);
    }

    /**
     * Returns the default PDO options to use for the connection.
     * @return array The default PDO options to use for the connection
     */
    protected function getOptions(): array
    {
        return [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => sprintf("SET time_zone = '%s'", date('P')),
            \PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ];
    }
}
