<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;

/**
 * ReferenceFiller.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class ReferenceFiller
{
    private $connection;
    private $cache;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param Record[] $records
     * @param string[] $references
     */
    public function fill(array $records, array $references): void
    {
        if (empty($records)) {
            return;
        }

        $this->cache = [];
        $schema = reset($records)->getSchema();
        $schemaId = $this->getSchemaId($schema);

        foreach ($records as $record) {
            if ($record->getSchema() !== $schema) {
                throw new \InvalidArgumentException('The provided list of records did not share the same schema');
            }

            $recordId = $this->getRecordId($schema, $record->getDatabaseValues());
            $this->cache[$schemaId][$recordId] = $record;
        }

        $this->fillReferences($records, $references);
    }

    /**
     * @param Record[] $records
     * @param string[] $references
     */
    private function fillReferences(array $records, array $references): void
    {
        $schema = reset($records)->getSchema();

        foreach ($this->parseReferences($references) as $name => $childReferences) {
            $reference = $schema->getReference($name);
            $keys = $reference->getFields();
            $fields = $reference->getReferencedFields();
            $parent = $reference->getReferencedSchema();
            $schemaId = $this->getSchemaId($parent);

            if (\count($fields) > 1) {
                throw new \RuntimeException('Filling references for composite foreign keys is not supported');
            }

            $key = array_pop($keys);
            $field = array_pop($fields);
            $options = [];

            foreach ($records as $record) {
                $options[] = $record[$key];
            }

            $result = $this->connection->select($parent->getFields(), $parent->getTable(), [$field => $options]);
            $result->setFetchMode(\PDO::FETCH_ASSOC);
            $sorted = [];

            foreach ($result as $row) {
                $record = $this->getCachedRecord($schemaId, $parent, $row);
                $sorted[$record[$field]][] = $record;
            }

            foreach ($records as $record) {
                $record->fillReference($name, $sorted[$record[$key]] ?? []);
            }

            if ($sorted && $childReferences) {
                $this->fillReferences(array_merge(... $sorted), $childReferences);
            }
        }
    }

    private function parseReferences(array $references): array
    {
        $subReferences = [];

        foreach ($references as $reference) {
            $parts = explode('.', $reference, 2);

            if (!isset($subReferences[$parts[0]])) {
                $subReferences[$parts[0]] = [];
            }

            if (isset($parts[1])) {
                $subReferences[$parts[0]][] = $parts[1];
            }
        }

        return $subReferences;
    }

    private function getCachedRecord(string $schemaId, Schema $schema, array $row): Record
    {
        $recordId = $this->getRecordId($schema, $row);

        if (isset($this->cache[$schemaId][$recordId])) {
            return $this->cache[$schemaId][$recordId];
        }

        $record = $schema->getRecord($row);
        $this->cache[$schemaId][$recordId] = $record;
        return $record;
    }

    private function getSchemaId(Schema $schema): string
    {
        return spl_object_hash($schema);
    }

    private function getRecordId(Schema $schema, array $row)
    {
        $values = [];

        foreach ($schema->getPrimaryKeys() as $key) {
            if (!isset($row[$key])) {
                throw new \RuntimeException('Cannot determine cache id for record');
            }

            $values[] = $row[$key];
        }

        return implode('-', $values);
    }
}