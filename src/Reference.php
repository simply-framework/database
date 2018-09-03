<?php

namespace Simply\Database;

/**
 * Relation.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Reference
{
    private $schema;
    private $fields;
    private $referencedSchema;
    private $referencedFields;

    public function __construct(Schema $schema, array $fields, Schema $referencedSchema, array $referencedFields)
    {
        $this->schema = $schema;
        $this->fields = $fields;
        $this->referencedSchema = $referencedSchema;
        $this->referencedFields = $referencedFields;
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

    public function matchValues($value, $referencedValue): bool
    {
        return (string) $value === (string) $referencedValue;
    }

    public function isSingleRelationship(): bool
    {
        return array_diff($this->referencedSchema->getPrimaryKeys(), $this->referencedFields) === [];
    }
}
