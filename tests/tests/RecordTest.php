<?php

namespace Simply\Database;

use Simply\Database\Test\TestCase\UnitTestCase;

/**
 * RecordTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class RecordTest extends UnitTestCase
{
    public function testPrimaryKeyOnEmptyRecord(): void
    {
        $schema = $this->getPersonSchema();
        $person = $schema->createRecord();

        $this->expectException(\RuntimeException::class);
        $person->getPrimaryKey();
    }

    public function testFetchingNoReferences(): void
    {
        $schema = $this->getPersonSchema();
        $person = $schema->createRecord();

        $this->expectException(\RuntimeException::class);
        $person->getReferencedRecords('spouse');
    }

    public function testMultipleModelAssociation(): void
    {
        $schema = $this->getPersonSchema();

        $personA = $schema->createRecord();
        $personB = $schema->createRecord();

        $this->expectException(\InvalidArgumentException::class);
        $personA->associate('parents', $personB->getModel());
    }

    public function testAssociateWithInvalidSchema(): void
    {
        $schema = $this->getPersonSchema();

        $person = $schema->createRecord();
        $house = $schema->getRelationship('home')->getReferencedSchema()->createRecord();

        $this->expectException(\InvalidArgumentException::class);
        $person->associate('spouse', $house->getModel());
    }

    public function testAssociatingWithNullValue(): void
    {
        $schema = $this->getPersonSchema();

        $personA = $schema->createRecord();
        $personB = $schema->createRecord();

        $this->expectException(\RuntimeException::class);
        $personA->associate('spouse', $personB->getModel());
    }

    public function testAddAssociationToUniqueRelationship(): void
    {
        $schema = $this->getPersonSchema();

        $personA = $schema->createRecord();
        $personB = $schema->createRecord();

        $this->expectException(\InvalidArgumentException::class);
        $personA->addAssociation('spouse', $personB->getModel());
    }

    public function testAssociateMultipleRelationship(): void
    {
        $schema = $this->getPersonSchema();

        $personA = $schema->createRecord();
        $personB = $schema->createRecord();

        $house = $schema->getRelationship('home')->getReferencedSchema()->createRecord();
        $house['id'] = 1;

        $personA->associate('home', $house->getModel());

        $this->assertSame([$house], $personA->getReferencedRecords('home'));
        $this->assertFalse($house->hasReferencedRecords('residents'));

        $house->setReferencedRecords('residents', [$personA]);

        $personB->associate('home', $house->getModel());

        $this->assertSame([$personA, $personB], $house->getReferencedRecords('residents'));
    }

    public function testGetRelatedModelForNonUnique(): void
    {
        $schema = $this->getPersonSchema();
        $person = $schema->createRecord();

        $this->expectException(\RuntimeException::class);
        $person->getRelatedModel('parents');
    }

    public function testGetEmptyRelatedModel(): void
    {
        $schema = $this->getPersonSchema();

        $person = $schema->createRecord();
        $person->setReferencedRecords('spouse', []);

        $this->assertNull($person->getRelatedModel('spouse'));
    }

    public function testGetMultipleModelsForNonUnique(): void
    {
        $schema = $this->getPersonSchema();
        $person = $schema->createRecord();

        $this->expectException(\RuntimeException::class);
        $person->getRelatedModels('spouse');
    }

    public function testUnsettingRecordFields(): void
    {
        $person = $this->getPersonSchema()->createRecord();

        $this->assertNull($person['id']);
        $this->assertFalse(isset($person['id']));

        $person['id'] = 1;

        $this->assertSame(1, $person['id']);
        $this->assertTrue(isset($person['id']));
        $this->assertContains('id', $person->getChangedFields());

        unset($person['id']);

        $this->assertNull($person['id']);
        $this->assertFalse(isset($person['id']));
        $this->assertNotContains('id', $person->getChangedFields());
    }

    public function testGettingInvalidField(): void
    {
        $person = $this->getPersonSchema()->createRecord();

        $this->expectException(\InvalidArgumentException::class);
        $person['not-a-field'];
    }

    public function testSettingInvalidField(): void
    {
        $person = $this->getPersonSchema()->createRecord();

        $this->expectException(\InvalidArgumentException::class);
        $person['not-a-field'] = 'value';
    }

    public function testIncorrectValueOrder(): void
    {
        $schema = $this->getPersonSchema();
        $person = $schema->createRecord();

        $values = array_fill_keys(array_reverse($schema->getFields()), 1);
        $person->setDatabaseValues($values);

        $this->assertSame($schema->getFields(), array_keys($person->getDatabaseValues()));
    }

    public function testMissingDatabaseValues(): void
    {
        $schema = $this->getPersonSchema();
        $person = $schema->createRecord();

        $this->expectException(\InvalidArgumentException::class);
        $person->setDatabaseValues(['id' => 1]);
    }

    public function testInvalidProxyRelation(): void
    {
        $person = $this->getPersonSchema()->createRecord();

        $this->expectException(\RuntimeException::class);
        $person->getRelatedModelsByProxy('spouse', 'spouse');
    }

    public function testInvalidProxiedRelation(): void
    {
        $house = $this->getPersonSchema()->getRelationship('home')->getReferencedSchema()->createRecord();

        $this->expectException(\RuntimeException::class);
        $house->getRelatedModelsByProxy('residents', 'parents');
    }

    public function testEmptyProxy(): void
    {
        $schema = $this->getPersonSchema();

        $person = $schema->createRecord();
        $parent = $schema->getRelationship('parents')->getReferencedSchema()->createRecord();

        $person['id'] = 1;
        $parent['child_id'] = 1;

        $person->setReferencedRecords('parents', [$parent]);
        $parent->setReferencedRecords('parent', []);

        $this->assertSame([], $person->getRelatedModelsByProxy('parents', 'parent'));
    }
}