<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;
use Simply\Database\Connection\MySqlConnection;
use Simply\Database\Connection\Provider\ConnectionProvider;
use Simply\Database\Connection\Provider\GenericConnectionProvider;
use Simply\Database\Connection\Provider\MySqlConnectionProvider;
use Simply\Database\Test\TestCase\IntegrationTestCase;
use Simply\Database\Test\TestHouseSchema;
use Simply\Database\Test\TestParentSchema;
use Simply\Database\Test\TestPersonSchema;

/**
 * MySqlIntegrationTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class MySqlIntegrationTest extends IntegrationTestCase
{
    protected static function createConnection(): Connection
    {
        return new MySqlConnection(new MySqlConnectionProvider(
            $_ENV['PHPUNIT_MYSQL_HOSTNAME'],
            $_ENV['PHPUNIT_MYSQL_DATABASE'],
            $_ENV['PHPUNIT_MYSQL_USERNAME'],
            $_ENV['PHPUNIT_MYSQL_PASSWORD']
        ));
    }

    protected static function createTables(
        Connection $connection,
        TestParentSchema $parentSchema,
        TestPersonSchema $personSchema,
        TestHouseSchema $houseSchema
    ): void {
        self::dropTables($connection, $parentSchema, $personSchema, $houseSchema);

        $houseTable = $houseSchema->getTable();
        $personTable = $personSchema->getTable();
        $parentTable = $parentSchema->getTable();

        $query = <<<SQL
CREATE TABLE `$houseTable` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `street` TEXT NOT NULL
);

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
);

CREATE TABLE `$parentTable` (
  `parent_id` INT NOT NULL,
  `child_id` INT NOT NULL,
  CONSTRAINT PRIMARY KEY (`parent_id`, `child_id`),
  CONSTRAINT FOREIGN KEY (`parent_id`) REFERENCES `$personTable` (`id`),
  CONSTRAINT FOREIGN KEY (`child_id`) REFERENCES `$personTable` (`id`)
);
SQL;

        $connection->getConnection()->exec($query);
    }

    protected static function dropTables(
        Connection $connection,
        TestParentSchema $parentSchema,
        TestPersonSchema $personSchema,
        TestHouseSchema $houseSchema
    ): void {
        $query = <<<SQL
DROP TABLE IF EXISTS `{$parentSchema->getTable()}`;
DROP TABLE IF EXISTS `{$personSchema->getTable()}`;
DROP TABLE IF EXISTS `{$houseSchema->getTable()}`;
SQL;

        $connection->getConnection()->exec($query);
    }

    protected function truncateTables(Connection $connection): void
    {
        $query = <<<SQL
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE TABLE `{$this->parentSchema->getTable()}`;
TRUNCATE TABLE `{$this->personSchema->getTable()}`;
TRUNCATE TABLE `{$this->houseSchema->getTable()}`;
SET FOREIGN_KEY_CHECKS=1;
SQL;

        $connection->getConnection()->exec($query);
    }

    public function testDSNSupport(): void
    {
        $property = new \ReflectionProperty(GenericConnectionProvider::class, 'dsn');
        $property->setAccessible(true);

        $connection = new MySqlConnectionProvider('localhost', 'database', 'username', 'password');
        $this->assertNotContains('port', $property->getValue($connection));

        $connection = new MySqlConnectionProvider('localhost:3306', 'database', 'username', 'password');
        $this->assertContains('port', $property->getValue($connection));

        $connection = new MySqlConnectionProvider('/tmp/mysql.sock', 'database', 'username', 'password');
        $this->assertContains('unix_socket', $property->getValue($connection));
    }

    public function testHandlingFalsePrepare()
    {
        $pdo = $this->getMockBuilder(\PDO::class)
            ->disableOriginalConstructor()
            ->getMock();

        $pdo->expects($this->once())->method('prepare')->willReturn(false);

        $connection = $this->createMock(ConnectionProvider::class);
        $connection->method('getConnection')->willReturn($pdo);

        $database = new MySqlConnection($connection);

        $this->expectException(\UnexpectedValueException::class);
        $database->query('SELECT * FROM `' . $this->personSchema->getTable() . '`');
    }
}
