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
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `first_name` TEXT NOT NULL,
  `last_name` TEXT NOT NULL,
  `age` INT NOT NULL,
  `weight` DECIMAL(5,2) NULL,
  `license` BOOL DEFAULT FALSE
)
SQL
        , $this->personSchema->getTable()));
    }
}
