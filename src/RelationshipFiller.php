<?php

namespace Simply\Database;

use Simply\Database\Connection\Connection;
use Simply\Database\Exception\InvalidRelationshipException;

/**
 * Facilitates filling relationships for multiple records at the same time.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class RelationshipFiller
{
    /** @var Connection Connection used to load data for the records from the database */
    private $connection;

    /** @var Record[] Records that are cached during the filling operation */
    private $cache;

    /**
     * RelationshipFiller constructor.
     * @param Connection $connection Connection used to load data for the records from the database
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->cache = [];
    }

    /**
     * Fills the given relationships for all records of a single type.
     * @param Record[] $records List of records that must all belong to the same schema
     * @param string[] $relationships List of relationships to fill with deeper levels separated by periods
     */
    public function fill(array $records, array $relationships): void
    {
        if (empty($records)) {
            return;
        }

        $this->cache = [];
        $schema = reset($records)->getSchema();

        foreach ($records as $record) {
            if ($record->getSchema() !== $schema) {
                throw new \InvalidArgumentException('The provided list of records did not share the same schema');
            }

            foreach ($record->getAllReferencedRecords() as $mappedRecord) {
                $this->cacheRecord($this->getSchemaId($mappedRecord->getSchema()), $mappedRecord);
            }
        }

        $this->fillRelationships($records, $relationships);
    }

    /**
     * Fills the relationships for the records.
     * @param Record[] $records List of records to fill
     * @param string[] $relationships The relationships to fill for the records
     */
    private function fillRelationships(array $records, array $relationships): void
    {
        $schema = reset($records)->getSchema();

        foreach ($this->parseChildRelationships($relationships) as $name => $childRelationships) {
            $relationship = $schema->getRelationship($name);
            $keys = $relationship->getFields();
            $fields = $relationship->getReferencedFields();
            $parent = $relationship->getReferencedSchema();
            $schemaId = $this->getSchemaId($parent);

            if (\count($fields) > 1) {
                throw new InvalidRelationshipException('Composite foreign keys are not supported by batch fill');
            }

            $isPrimaryReference = $fields === $parent->getPrimaryKey();
            $key = array_pop($keys);
            $field = array_pop($fields);
            $fillRecords = [];
            $options = [];
            $filled = [];

            foreach ($records as $record) {
                $value = $record[$key];

                if ($value === null) {
                    $record->setReferencedRecords($name, []);
                } elseif ($record->hasReferencedRecords($name)) {
                    $filled[$value] = $record->getReferencedRecords($name);
                } elseif ($isPrimaryReference && isset($this->cache[$schemaId][$value])) {
                    $filled[$value] = [$this->cache[$schemaId][$value]];
                    $fillRecords[] = $record;
                } else {
                    $options[$value] = true;
                    $fillRecords[] = $record;
                }
            }

            $loaded = empty($filled) ? [] : array_merge(... array_values($filled));
            $options = array_keys(array_diff_key($options, $filled));

            if ($options) {
                $result = $this->connection->select($parent->getFields(), $parent->getTable(), [$field => $options]);
                $result->setFetchMode(\PDO::FETCH_ASSOC);

                foreach ($result as $row) {
                    $loaded[] = $this->getCachedRecord($schemaId, $parent, $row);
                }
            }

            $relationship->fillRelationship($fillRecords, $loaded);

            if ($loaded && $childRelationships) {
                $this->fillRelationships($loaded, $childRelationships);
            }
        }
    }

    /**
     * Parses the list of relationships into associative array of relationships and their children.
     * @param string[] $relationships List of relationships to parse
     * @return array[] Associative array of top level relationships and their children
     */
    private function parseChildRelationships(array $relationships): array
    {
        $childRelationships = [];

        foreach ($relationships as $relationship) {
            $parts = explode('.', $relationship, 2);

            if (!isset($childRelationships[$parts[0]])) {
                $childRelationships[$parts[0]] = [];
            }

            if (isset($parts[1])) {
                $childRelationships[$parts[0]][] = $parts[1];
            }
        }

        return $childRelationships;
    }

    /**
     * Caches the given record for the schema with given id.
     * @param int $schemaId Cache id of of the schema
     * @param Record $record The record to cache
     */
    private function cacheRecord(int $schemaId, Record $record): void
    {
        $recordId = implode('-', $record->getPrimaryKey());

        if (isset($this->cache[$schemaId][$recordId]) && $this->cache[$schemaId][$recordId] !== $record) {
            throw new \RuntimeException('Duplicated record detected when filling relationships for records');
        }

        $this->cache[$schemaId][$recordId] = $record;
    }

    /**
     * Returns a new or cached record for the given schema and schema id based on the database row.
     * @param int $schemaId Cache id of of the schema
     * @param Schema $schema The schema for the record
     * @param array $row The database row
     * @return Record A new or cached record based on the database row
     */
    private function getCachedRecord(int $schemaId, Schema $schema, array $row): Record
    {
        $primaryKey = [];

        foreach ($schema->getPrimaryKey() as $key) {
            $primaryKey[] = $row[$key];
        }

        $recordId = implode('-', $primaryKey);

        if (isset($this->cache[$schemaId][$recordId])) {
            return $this->cache[$schemaId][$recordId];
        }

        $record = $schema->createRecordFromValues($row);
        $this->cache[$schemaId][$recordId] = $record;
        return $record;
    }

    /**
     * Returns cache id for the given schema.
     * @param Schema $schema The schema to use
     * @return int The cache id for the given schema
     */
    private function getSchemaId(Schema $schema): int
    {
        return spl_object_id($schema);
    }
}
