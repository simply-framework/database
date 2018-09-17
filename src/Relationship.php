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
    private $name;
    private $schema;
    private $fields;
    private $referencedSchema;
    private $referencedFields;
    private $unique;
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
            function (Relationship $relationship) {
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
}
