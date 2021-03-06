<?php

namespace Simply\Database\Test\TestCase;

use Simply\Database\Connection\Connection;
use Simply\Database\Exception\MissingRecordException;
use Simply\Database\Test\TestHouseSchema;
use Simply\Database\Test\TestParentSchema;
use Simply\Database\Test\TestPersonModel;
use Simply\Database\Test\TestPersonSchema;
use Simply\Database\Test\TestRepository;

/**
 * IntegrationTestCase.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class IntegrationTestCase extends UnitTestCase
{
    /** @var Connection */
    protected $connection;

    /** @var TestPersonSchema */
    protected $personSchema;

    /** @var TestParentSchema */
    protected $parentSchema;

    /** @var TestHouseSchema */
    protected $houseSchema;

    abstract protected static function createConnection(): Connection;

    abstract protected static function createTables(
        Connection $connection,
        TestParentSchema $parentSchema,
        TestPersonSchema $personSchema,
        TestHouseSchema $houseSchema
    ): void;

    abstract protected static function dropTables(
        Connection $connection,
        TestParentSchema $parentSchema,
        TestPersonSchema $personSchema,
        TestHouseSchema $houseSchema
    ): void;

    abstract protected function truncateTables(Connection $connection): void;

    public static function setUpBeforeClass()
    {
        $container = static::initializeContainer();
        $connection = static::createConnection();

        static::createTables(
            $connection,
            $container[TestParentSchema::class],
            $container[TestPersonSchema::class],
            $container[TestHouseSchema::class]
        );
    }

    public static function tearDownAfterClass()
    {
        $container = static::initializeContainer();
        $connection = static::createConnection();

        static::dropTables(
            $connection,
            $container[TestParentSchema::class],
            $container[TestPersonSchema::class],
            $container[TestHouseSchema::class]
        );
    }

    protected function setUp()
    {
        $container = static::initializeContainer();

        $this->personSchema = $container[TestPersonSchema::class];
        $this->parentSchema = $container[TestParentSchema::class];
        $this->houseSchema = $container[TestHouseSchema::class];

        $this->connection = static::createConnection();
        $this->truncateTables($this->connection);
    }

    private function getTestPersonRepository(): TestRepository
    {
        return new TestRepository($this->connection, $this->personSchema, $this->parentSchema, $this->houseSchema);
    }

    private function setUpFamily(TestRepository $repository): void
    {
        $home = $repository->createHouse('Anonymous Street');
        $repository->saveHouse($home);

        $jane = $repository->createPerson('Jane', 'Doe', 20);
        $john = $repository->createPerson('John', 'Doe', 20);
        $mama = $repository->createPerson('Mama', 'Doe', 40);
        $papa = $repository->createPerson('Papa', 'Doe', 40);

        $home->movePeople([$jane, $john, $mama, $papa]);

        $repository->savePerson($jane);
        $repository->savePerson($john);
        $repository->savePerson($mama);
        $repository->savePerson($papa);

        $mama->marry($papa);
        $repository->savePerson($mama);
        $repository->savePerson($papa);

        $repository->makeParent($jane, $mama);
        $repository->makeParent($jane, $papa);
        $repository->makeParent($john, $mama);
        $repository->makeParent($john, $papa);
    }

    public function testCrudOperations(): void
    {
        $repository = $this->getTestPersonRepository();

        $person = $repository->createPerson('Jane', 'Doe', 20);
        $repository->savePerson($person);

        $id = $person->getId();
        $this->assertNotNull($id);

        $person->increaseAge();
        $repository->savePerson($person);

        $saved = $repository->findById($id);

        $this->assertSame($id, $saved->getId());
        $this->assertSame('Jane', $saved->getFirstName());
        $this->assertSame('Doe', $saved->getLastName());
        $this->assertSame(21, $saved->getAge());

        $repository->deletePerson($saved);

        $this->assertNull($repository->findById($id));
    }

    public function testFindMultiple(): void
    {
        $repository = $this->getTestPersonRepository();

        $repository->savePerson($repository->createPerson('John', 'Doe', 20));
        $repository->savePerson($repository->createPerson('Jane', 'Doe', 20));

        $this->assertCount(0, $repository->findByLastName('John'));
        $this->assertCount(1, $repository->findByFirstName('John'));
        $this->assertCount(1, $repository->findByFirstName('Jane'));
        $this->assertCount(2, $repository->findByLastName('Doe'));
        $this->assertCount(2, $repository->findByAnyFirstName(['John', 'Jane']));
    }

    public function testOrderedLimits(): void
    {
        $repository = $this->getTestPersonRepository();

        $repository->savePerson($repository->createPerson('Elizabeth', 'Jones', 35));
        $repository->savePerson($repository->createPerson('Carmen', 'Martinez', 47));
        $repository->savePerson($repository->createPerson('Isabell', 'Williams', 45));
        $repository->savePerson($repository->createPerson('Corina', 'Keith', 64));
        $repository->savePerson($repository->createPerson('Ruth', 'Ward', 44));
        $repository->savePerson($repository->createPerson('Frances', 'Gray', 41));

        $lastName = function (TestPersonModel $person): string {
            return $person->getLastName();
        };

        $this->assertSame(
            ['Gray', 'Jones', 'Keith', 'Martinez', 'Ward', 'Williams'],
            array_map($lastName, $repository->findAllAlphabetically())
        );

        $this->assertSame(
            ['Williams', 'Ward', 'Martinez', 'Keith', 'Jones', 'Gray'],
            array_map($lastName, $repository->findAllAlphabetically(null, false))
        );

        $this->assertSame(
            ['Gray', 'Jones', 'Keith'],
            array_map($lastName, $repository->findAllAlphabetically(3))
        );

        $this->assertSame(
            ['Williams', 'Ward', 'Martinez'],
            array_map($lastName, $repository->findAllAlphabetically(3, false))
        );
    }

    public function testDecimalNulls(): void
    {
        $repository = $this->getTestPersonRepository();

        $jane = $repository->createPerson('Jane', 'Doe', 20);
        $jane->setWeight(72.1);
        $repository->savePerson($jane);

        $john = $repository->createPerson('John', 'Doe', 20);
        $john->setWeight(82.0);
        $repository->savePerson($john);

        $repository->savePerson($repository->createPerson('Jane', 'Smith', 22));

        $this->assertSame(72.1, $repository->findOneByWeight(72.1)->getWeight());
        $this->assertNull($repository->findOneByWeight(null)->getWeight());
        $this->assertCount(2, $repository->findByAnyWeight([72.1, 82.0]));
        $this->assertCount(2, $repository->findByAnyWeight([72.1, null]));
        $this->assertCount(1, $repository->findByAnyWeight([null]));
    }

    public function testBooleanFields(): void
    {
        $repository = $this->getTestPersonRepository();

        $repository->savePerson($repository->createPerson('Jane', 'Doe', 20));

        $this->assertCount(0, $repository->findByHasLicense(true));

        $unlicensed = $repository->findByHasLicense(false);
        $this->assertCount(1, $unlicensed);

        $jane = array_pop($unlicensed);
        $jane->giveLicense();

        $repository->savePerson($jane);

        $this->assertCount(1, $repository->findByHasLicense(true));
        $this->assertCount(0, $repository->findByHasLicense(false));
    }

    public function testRelationships(): void
    {
        $repository = $this->getTestPersonRepository();
        $this->setUpFamily($repository);

        $person = $repository->findByFirstName('John')[0];

        $repository->loadFamily([$person]);

        $this->assertSame('Anonymous Street', $person->getHome()->getStreet());
        $this->assertCount(4, $person->getHome()->getResidents());

        $firstName = function (TestPersonModel $model): string {
            return $model->getFirstName();
        };

        $parents = $person->getParents();

        $this->assertCount(2, $parents);

        $residentNames = array_map($firstName, $person->getHome()->getResidents());
        $names = array_map($firstName, $parents);
        sort($names);
        sort($residentNames);

        $this->assertSame(['Mama', 'Papa'], $names);
        $this->assertSame(['Jane', 'John', 'Mama', 'Papa'], $residentNames);

        foreach ($parents as $parent) {
            $this->assertContains($person, $parent->getChildren());
        }

        $repository->loadFamily($parents);

        foreach ($parents as $parent) {
            $this->assertContains($person, $parent->getChildren());
        }
    }

    public function testInsertWithNoValues()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->connection->insert($this->personSchema->getTable(), []);
    }

    public function testSelectWithNoFields()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->connection->select([], $this->personSchema->getTable(), []);
    }

    public function testSelectWithNoTable()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->connection->select(['id'], '', []);
    }

    public function testUpdateWithNoValues()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->connection->update($this->personSchema->getTable(), [], ['id' => 1]);
    }

    public function testUpdateWithNoConditions()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->connection->update($this->personSchema->getTable(), ['first_name' => 'Jane'], []);
    }

    public function testDeleteWithNoConditions()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->connection->delete($this->personSchema->getTable(), []);
    }

    public function testUnsupportedValueType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->connection->insert($this->personSchema->getTable(), ['first_name' => ['array', 'values']]);
    }

    public function testInvalidSortOrder()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->connection->select(['id'], $this->personSchema->getTable(), [], ['id' => 0]);
    }

    public function testSavingDeletedRecord()
    {
        $repository = $this->getTestPersonRepository();
        $person = $repository->createPerson('Jane', 'Doe', 20);
        $repository->savePerson($person);
        $repository->deletePerson($person);

        $this->assertCount(0, $repository->findByFirstName('Jane'));

        $this->expectException(\RuntimeException::class);
        $repository->savePerson($person);
    }

    public function testUnlimitedWithoutSortOrder()
    {
        $repository = $this->getTestPersonRepository();

        $repository->savePerson($repository->createPerson('Jane', 'Doe', 20));
        $repository->savePerson($repository->createPerson('John', 'Doe', 20));

        $results = $this->connection->select(['id'], $this->personSchema->getTable(), [], [], 1);

        $this->assertCount(2, iterator_to_array($results));
    }

    public function testCustomQuery()
    {
        $repository = $this->getTestPersonRepository();

        $repository->savePerson($repository->createPerson('Elizabeth', 'Jones', 35));
        $repository->savePerson($repository->createPerson('Carmen', 'Martinez', 47));
        $repository->savePerson($repository->createPerson('Isabell', 'Williams', 45));
        $repository->savePerson($repository->createPerson('Corina', 'Keith', 64));
        $repository->savePerson($repository->createPerson('Ruth', 'Ward', 44));
        $repository->savePerson($repository->createPerson('Frances', 'Gray', 41));

        foreach ($repository->findYoungerThan(46) as $person) {
            $ages[] = $person->getAge();
        }

        sort($ages);

        $this->assertSame([35, 41, 44, 45], $ages);
        $this->assertSame(1, $repository->countYoungerThan(40));
        $this->assertSame(5, $repository->countYoungerThan(50));
    }

    public function testIterationWithOneToOneRelation()
    {
        $repository = $this->getTestPersonRepository();
        $this->setUpFamily($repository);
        $count = 0;

        foreach ($repository->iterateWithSpouse() as $person) {
            $count++;

            if ($person->getFirstName() === 'Mama') {
                $this->assertSame('Papa', $person->getSpouse()->getFirstName());
            } elseif ($person->getFirstName() === 'Papa') {
                $this->assertSame('Mama', $person->getSpouse()->getFirstName());
            } else {
                $this->assertNull($person->getSpouse());
            }
        }

        $this->assertSame(4, $count);
    }

    public function testIterationWithRelation()
    {
        $repository = $this->getTestPersonRepository();
        $this->setUpFamily($repository);
        $count = 0;

        foreach ($repository->iterateWithHouse() as $person) {
            $count++;
            $this->assertSame('Anonymous Street', $person->getHome()->getStreet());
        }

        $this->assertSame(4, $count);
    }

    public function testRefreshingARecords()
    {
        $repository = $this->getTestPersonRepository();

        $person = $repository->createPerson('Jane', 'Doe', 20);
        $person->setWeight(70);
        $repository->savePerson($person);

        $secondInstance = $repository->findByFirstName('Jane')[0];
        $secondInstance->setWeight(75);
        $repository->savePerson($secondInstance);

        $repository->refreshPerson($person);
        $this->assertSame(75.0, $person->getWeight());
    }

    public function testRefreshingDeletedRecord()
    {
        $repository = $this->getTestPersonRepository();

        $person = $repository->createPerson('Jane', 'Doe', 20);
        $repository->savePerson($person);

        $repository->deletePerson($repository->findByFirstName('Jane')[0]);

        $this->expectException(MissingRecordException::class);
        $repository->refreshPerson($person);
    }

    public function testUpdatingDeletedRecord()
    {
        $repository = $this->getTestPersonRepository();

        $person = $repository->createPerson('Jane', 'Doe', 20);
        $repository->savePerson($person);

        $repository->deletePerson($repository->findByFirstName('Jane')[0]);

        $person->setWeight(70);

        $this->expectException(MissingRecordException::class);
        $repository->savePerson($person);
    }

    public function testDeletingDeletedRecord()
    {
        $repository = $this->getTestPersonRepository();

        $person = $repository->createPerson('Jane', 'Doe', 20);
        $repository->savePerson($person);

        $repository->deletePerson($repository->findByFirstName('Jane')[0]);

        $this->expectException(MissingRecordException::class);
        $repository->deletePerson($person);
    }
}
