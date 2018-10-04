<?php

namespace Simply\Database;

use Simply\Database\Exception\InvalidRelationshipException;

/**
 * Represents a relationship between two schemas.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Relationship
{
    /** @var string Name of the relationship */
    private $name;

    /** @var Schema The referring schema */
    private $schema;

    /** @var string[] The referring fields */
    private $fields;

    /** @var Schema The referenced schema */
    private $referencedSchema;

    /** @var string[] The referenced fields */
    private $referencedFields;

    /** @var bool Whether the relationship is unique or not */
    private $unique;

    /** @var Relationship|null The reverse relationship or null if not initialized yet */
    private $reverse;

    /**
     * Relationship constructor.
     * @param string $name Name of the relationship
     * @param Schema $schema The referring schema
     * @param string[] $fields The referring fields
     * @param Schema $referencedSchema The referenced schema
     * @param string[] $referencedFields The referenced fields
     * @param bool $unique Whether the relationship can only reference a single record or not
     */
    public function __construct(
        string $name,
        Schema $schema,
        array $fields,
        Schema $referencedSchema,
        array $referencedFields,
        bool $unique
    ) {
        $formatStrings = function (string ... $strings): array {
            return $strings;
        };

        $this->name = $name;
        $this->schema = $schema;
        $this->fields = $formatStrings(... $fields);
        $this->referencedSchema = $referencedSchema;
        $this->referencedFields = $formatStrings(... $referencedFields);
        $this->unique = $unique || array_diff($this->referencedSchema->getPrimaryKey(), $this->referencedFields) === [];

        if (empty($this->fields) || \count($this->fields) !== \count($this->referencedFields)) {
            throw new \InvalidArgumentException('Unexpected list of fields in relationship');
        }

        if (array_diff($this->fields, $this->schema->getFields()) !== []) {
            throw new \InvalidArgumentException('The referencing fields must be defined in the referencing schema');
        }

        if (array_diff($this->referencedFields, $this->referencedSchema->getFields()) !== []) {
            throw new \InvalidArgumentException('The referenced fields must be defined in the referenced schema');
        }
    }

    /**
     * Returns the name of the relationship.
     * @return string Name of the relationship
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the referring schema.
     * @return Schema The referring schema
     */
    public function getSchema(): Schema
    {
        return $this->schema;
    }

    /**
     * Returns the referring fields.
     * @return string[] The referring fields
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Returns the referenced schema.
     * @return Schema The referenced schema
     */
    public function getReferencedSchema(): Schema
    {
        return $this->referencedSchema;
    }

    /**
     * Returns referenced fields.
     * @return string[] The referenced fields
     */
    public function getReferencedFields(): array
    {
        return $this->referencedFields;
    }

    /**
     * Tells if the relationship is unique or not.
     * @return bool True if the relationship can only refer to a single record, false otherwise
     */
    public function isUniqueRelationship(): bool
    {
        return $this->unique;
    }

    /**
     * Returns the reverse relationship.
     * @return Relationship The reverse relationship
     */
    public function getReverseRelationship(): Relationship
    {
        if ($this->reverse === null) {
            $this->reverse = $this->detectReverseRelationship();
        }

        return $this->reverse;
    }

    /**
     * Detects the reverse relationship in the referenced schema.
     * @return Relationship The reverse relationship in the referenced schema
     */
    private function detectReverseRelationship(): Relationship
    {
        $reverse = array_filter(
            $this->referencedSchema->getRelationships(),
            function (Relationship $relationship): bool {
                return $this->isReverseRelationship($relationship);
            }
        );

        if (\count($reverse) !== 1) {
            if (\count($reverse) > 1) {
                throw new InvalidRelationshipException('Multiple reverse relationship exists for this relationship');
            }

            throw new InvalidRelationshipException('No reverse relationship exists for this relationship');
        }

        return array_pop($reverse);
    }

    /**
     * Tells if the given relationship is a reverse relationship to this relationship.
     * @param Relationship $relationship The relationship to test
     * @return bool True if the given relationship is a reverse relatinoship, false if not
     */
    private function isReverseRelationship(Relationship $relationship): bool
    {
        return $relationship->getSchema() === $this->getReferencedSchema()
            && $relationship->getReferencedSchema() === $this->getSchema()
            && $relationship->getFields() === $this->getReferencedFields()
            && $relationship->getReferencedFields() === $this->getFields();
    }

    /**
     * Fills this relationship for the given records from the list of given records.
     * @param Record[] $records The records to fill
     * @param Record[] $referencedRecords All the records referenced by the list of records to fill
     */
    public function fillRelationship(array $records, array $referencedRecords): void
    {
        if (\count($this->getFields()) !== 1) {
            throw new InvalidRelationshipException('Relationship fill is not supported for composite foreign keys');
        }

        if (empty($records)) {
            return;
        }

        $this->assignSortedRecords($records, $this->getSortedRecords($referencedRecords));
    }

    /**
     * Returns list of records sorted by the value of the referenced field.
     * @param Record[] $records The list of records to sort
     * @return Record[][] Lists of records sorted by value of the referenced field
     */
    private function getSortedRecords(array $records): array
    {
        $schema = $this->getReferencedSchema();
        $field = $this->getReferencedFields()[0];
        $unique = $this->isUniqueRelationship();
        $sorted = [];

        foreach ($records as $record) {
            if ($record->getSchema() !== $schema) {
                throw new \InvalidArgumentException('The referenced records must all belong to the referenced schema');
            }

            $value = $record[$field];

            if ($value === null) {
                continue;
            }

            if ($unique && isset($sorted[$value])) {
                throw new InvalidRelationshipException('Multiple records detected for unique relationship');
            }

            $sorted[$value][] = $record;
        }

        return $sorted;
    }

    /**
     * Fills the relationships in given records from the sorted list of records.
     * @param Record[] $records List of records to fill
     * @param Record[][] $sorted List of records sorted by the value of the referenced field
     */
    private function assignSortedRecords(array $records, array $sorted): void
    {
        $schema = $this->getSchema();
        $name = $this->getName();
        $field = $this->getFields()[0];

        $fillReverse = $this->getReverseRelationship()->isUniqueRelationship();
        $reverse = $this->getReverseRelationship()->getName();

        foreach ($records as $record) {
            if ($record->getSchema() !== $schema) {
                throw new \InvalidArgumentException('The filled records must all belong to the referencing schema');
            }

            $value = $record[$field];
            $sortedRecords = $value === null || empty($sorted[$value]) ? [] : $sorted[$value];
            $record->setReferencedRecords($name, $sortedRecords);

            if ($fillReverse) {
                foreach ($sortedRecords as $reverseRecord) {
                    $reverseRecord->setReferencedRecords($reverse, [$record]);
                }
            }
        }
    }

    /**
     * Fills this unique relationship for a single record.
     * @param Record $record The record to fill
     * @param Record $referencedRecord The referenced record
     */
    public function fillSingleRecord(Record $record, Record $referencedRecord): void
    {
        if (!$this->isUniqueRelationship()) {
            throw new InvalidRelationshipException('Only unique relationships can be filled with single records');
        }

        if ($referencedRecord->isEmpty()) {
            $record->setReferencedRecords($this->getName(), []);
            return;
        }

        $keys = $this->getFields();
        $fields = $this->getReferencedFields();

        while ($keys) {
            if ((string) $record[array_pop($keys)] !== (string) $referencedRecord[array_pop($fields)]) {
                throw new \InvalidArgumentException('The provided records are not related');
            }
        }

        $record->setReferencedRecords($this->getName(), [$referencedRecord]);
        $reverse = $this->getReverseRelationship();

        if ($reverse->isUniqueRelationship()) {
            $referencedRecord->setReferencedRecords($reverse->getName(), [$record]);
        }
    }
}
