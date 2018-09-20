<?php

namespace Simply\Database;

/**
 * Relation.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Relationship
{
    /** @var string */
    private $name;

    /** @var Schema */
    private $schema;

    /** @var string[] */
    private $fields;

    /** @var Schema */
    private $referencedSchema;

    /** @var string[] */
    private $referencedFields;

    /** @var bool */
    private $unique;

    /** @var Relationship|null */
    private $reverse;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getReferencedSchema(): Schema
    {
        return $this->referencedSchema;
    }

    public function getReferencedFields(): array
    {
        return $this->referencedFields;
    }

    public function isUniqueRelationship(): bool
    {
        return $this->unique;
    }

    public function getReverseRelationship(): Relationship
    {
        if ($this->reverse === null) {
            $this->reverse = $this->detectReverseRelationship();
        }

        return $this->reverse;
    }

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
                throw new \RuntimeException('Multiple reverse relationship exists for this relationship');
            }

            throw new \RuntimeException('No reverse relationship exists for this relationship');
        }

        return array_pop($reverse);
    }

    private function isReverseRelationship(Relationship $relationship): bool
    {
        return $relationship->getSchema() === $this->getReferencedSchema()
            && $relationship->getReferencedSchema() === $this->getSchema()
            && $relationship->getFields() === $this->getReferencedFields()
            && $relationship->getReferencedFields() === $this->getFields();
    }

    /**
     * @param Record[] $records
     * @param Record[] $referencedRecords
     */
    public function fillRelationship(array $records, array $referencedRecords): void
    {
        if (\count($this->getFields()) !== 1) {
            throw new \RuntimeException('Relationship fill is not supported for composite foreign keys');
        }

        if (empty($records)) {
            return;
        }

        $this->assignSortedRecords($records, $this->getSortedRecords($referencedRecords));
    }

    /**
     * @param Record[] $records
     * @return Record[][]
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
                throw new \InvalidArgumentException('Unique relationship cannot reference more than a single record');
            }

            $sorted[$value][] = $record;
        }

        return $sorted;
    }

    /**
     * @param Record[] $records
     * @param Record[][] $sorted
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

    public function fillSingleRecord(Record $record, Record $referencedRecord): void
    {
        if (!$this->isUniqueRelationship()) {
            throw new \LogicException('Only unique relationships can be filled with single records');
        }

        if ($referencedRecord->isEmpty()) {
            $record->setReferencedRecords($this->getName(), []);
            return;
        }

        $keys = $this->getFields();
        $fields = $this->getReferencedFields();

        while ($keys) {
            if ((string) $record[array_pop($keys)] !== (string) $referencedRecord[array_pop($fields)]) {
                throw new \LogicException('Tried to fill a record with a record that is not the referenced record');
            }
        }

        $record->setReferencedRecords($this->getName(), [$referencedRecord]);
        $reverse = $this->getReverseRelationship();

        if ($reverse->isUniqueRelationship()) {
            $referencedRecord->setReferencedRecords($reverse->getName(), [$record]);
        }
    }
}
