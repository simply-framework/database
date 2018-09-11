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
    private $primaryRelation;

    public function __construct(Schema $schema, array $fields, Schema $referencedSchema, array $referencedFields)
    {
        $formatStrings = function (string ... $strings): array {
            return $strings;
        };

        $this->schema = $schema;
        $this->fields = $formatStrings(... $fields);
        $this->referencedSchema = $referencedSchema;
        $this->referencedFields = $formatStrings(... $referencedFields);
        $this->primaryRelation = array_diff($this->referencedSchema->getPrimaryKey(), $this->referencedFields) === [];

        if (empty($this->fields) || \count($this->fields) !== \count($this->referencedFields)) {
            throw new \InvalidArgumentException('Unexpected list of fields in relationship');
        }
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

    public function isSingleRelationship(): bool
    {
        return $this->primaryRelation;
    }
}
