<?php

namespace Simply\Database\Connection\Provider;

/**
 * MySqlConnectionProvider.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class MySqlConnectionProvider extends GenericConnectionProvider
{
    public function __construct(string $hostname, string $database, string $username, string $password)
    {
        parent::__construct($this->getDataSourceName($hostname, $database), $username, $password, $this->getOptions());
    }

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
