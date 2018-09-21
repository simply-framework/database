<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;
use Simply\Database\Connection\MySqlConnection;
use Simply\Database\Test\TestCase\IntegrationTestCase;

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
        $queries = [];

        $personTable = $this->personSchema->getTable();
        $parentTable = $this->parentSchema->getTable();
        $houseTable = $this->houseSchema->getTable();

        $queries[] = "DROP TABLE IF EXISTS `$parentTable`";
        $queries[] = "DROP TABLE IF EXISTS `$personTable`";
        $queries[] = "DROP TABLE IF EXISTS `$houseTable`";

        $queries[] = <<<SQL
CREATE TABLE `$houseTable` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `street` TEXT NOT NULL
)
SQL;

        $queries[] = <<<SQL
CREATE TABLE `$personTable` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `first_name` TEXT NOT NULL,
  `last_name` TEXT NOT NULL,
  `age` INT NOT NULL,
  `weight` DECIMAL(5,2) NULL,
  `license` BOOL DEFAULT FALSE,
  `spouse_id` INT NULL,
  `home_id` INT NULL,
  CONSTRAINT UNIQUE KEY (`spouse_id`),
  CONSTRAINT FOREIGN KEY (`spouse_id`) REFERENCES `$personTable` (`id`),
  CONSTRAINT FOREIGN KEY (`home_id`) REFERENCES `$houseTable` (`id`)
)
SQL;

        $queries[] = <<<SQL
CREATE TABLE `$parentTable` (
  `parent_id` INT NOT NULL,
  `child_id` INT NOT NULL,
  CONSTRAINT PRIMARY KEY (`parent_id`, `child_id`),
  CONSTRAINT FOREIGN KEY (`parent_id`) REFERENCES `$personTable` (`id`),
  CONSTRAINT FOREIGN KEY (`child_id`) REFERENCES `$personTable` (`id`)
)
SQL;

        $pdo = $connection->getConnection();

        foreach ($queries as $query) {
            $pdo->exec($query);
        }
    }

    public function testDSNSupport(): void
    {
        $method = new \ReflectionMethod($this->connection, 'getDataSource');
        $method->setAccessible(true);

        $this->assertNotContains('port', $method->invoke($this->connection, 'localhost', 'database'));
        $this->assertContains('port', $method->invoke($this->connection, 'localhost:3306', 'database'));
        $this->assertContains('unix_socket', $method->invoke($this->connection, '/tmp/mysql.sock', 'database'));
    }
}
