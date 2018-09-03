<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;
use Simply\Database\Connection\MySqlConnection;
use Simply\Database\Test\IntegrationTestCase;

/**
 * MySqlIntegrationTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class MySqlIntegrationTest extends IntegrationTestCase
{
    protected function createConnection(): Connection
    {
        return new MySqlConnection(
            $_ENV['phpunit_mysql_hostname'],
            $_ENV['phpunit_mysql_database'],
            $_ENV['phpunit_mysql_username'],
            $_ENV['phpunit_mysql_password']
        );
    }

    protected function setUpDatabase(Connection $connection): void
    {
        $pdo = $connection->getConnection();

        $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $this->personSchema->getTable()));
        $pdo->exec(sprintf(<<<'SQL'
CREATE TABLE `%s` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `first_name` TEXT,
  `last_name` TEXT,
  `age` INT
)
SQL
        , $this->personSchema->getTable()));
    }
}
