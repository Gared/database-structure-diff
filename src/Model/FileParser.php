<?php

namespace DatabaseDiffer\Model;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\StringType;
use Exception;
use iamcal\SQLParser;

class FileParser
{
    /**
     * @var string
     */
    private $filePath;

    /**
     * @var SQLParser
     */
    private $sqlParser;

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var AbstractPlatform
     */
    private $platform;

    /**
     * FileParser constructor.
     * @param string $filePath
     * @param string $databaseName
     * @param AbstractPlatform $platform
     */
    public function __construct(string $filePath, string $databaseName, AbstractPlatform $platform)
    {
        $this->filePath = $filePath;
        $this->platform = $platform;

        $this->loadStructureDefinition($databaseName);
        $this->convertToStructure();
    }

    /**
     * Load definition of database structure from sql file
     * @param string $databaseName
     */
    private function loadStructureDefinition(string $databaseName)
    {
        $data = file_get_contents($this->filePath);

        $data = preg_replace('/CREATE TABLE( IF NOT EXISTS)? `(?!' . $databaseName . ')[^;]+;/si', '', $data);
        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new Exception('Failed to read sql (propably too big)');
        }
        $data = preg_replace('/(`.*`)\.(`.*`)/i', '$2', $data);
        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new Exception('Failed to read sql (propably too big)');
        }

        $this->sqlParser = new SQLParser();
        $this->sqlParser->parse($data);
    }

    /**
     * Convert sql parsed file to doctrine database schema
     */
    private function convertToStructure()
    {
        $schemaConfig = new SchemaConfig();

        $this->schema = new Schema([], [], $schemaConfig);

        foreach ($this->sqlParser->tables as $tableName => $table) {
            $schemaTable = $this->schema->createTable($tableName);
            foreach ($table['fields'] as $field) {
                $fieldType = strtolower($field['type']);
                $column = $schemaTable->addColumn($field['name'], $this->platform->getDoctrineTypeMapping($fieldType));
                if ($column->getType() instanceof StringType) {
                    $column->setLength($field['length'] ?? null);
                    if ($fieldType === 'enum') {
                        $column->setFixed(false);
                    } else {
                        $column->setFixed(stripos($field['type'], 'VAR') === false);
                    }
                } else if ($column->getType() instanceof DecimalType) {
                    $column->setPrecision($field['length'] ?? null);
                    $column->setScale($field['decimals'] ?? null);
                }
                $column->setNotnull($field['null'] !== true);
                $column->setAutoincrement($field['auto_increment'] ?? false);
                $column->setUnsigned($field['unsigned'] ?? false);
                if (isset($field['default'])) {
                    $column->setDefault($field['default'] !== 'NULL' ? $field['default'] : null);
                }
                $column->setComment($this->getComment($field['more'] ?? []));
            }

            foreach ($table['indexes'] as $index) {
                $columnNames = [];
                foreach ($index['cols'] as $col) {
                    $columnNames[] = $col['name'];
                }

                if ($index['type'] === 'PRIMARY') {
                    $schemaTable->setPrimaryKey($columnNames);

                } else if ($index['type'] === 'FOREIGN') {
                    $refColumnNames = [];
                    foreach ($index['ref_cols'] as $col) {
                        $refColumnNames[] = $col['name'];
                    }
                    $schemaTable->addForeignKeyConstraint($index['ref_table'], $columnNames, $refColumnNames);
                } else if ($index['type'] === 'INDEX') {
                    $schemaTable->addIndex($columnNames, $index['name'] ?? null);
                } else if ($index['type'] === 'UNIQUE') {
                    $schemaTable->addUniqueIndex($columnNames, $index['name'] ?? null);
                }
            }
        }
    }

    /**
     * @param array $moreInfo
     * @return string|null
     */
    private function getComment(array $moreInfo): ?string
    {
        foreach ($moreInfo as $key => $info) {
            if ($info === 'COMMENT') {
                $comment = $moreInfo[$key+1];
                $comment = str_replace('"', "", $comment);
                $comment = str_replace("'", "", $comment);
                return $comment;
            }
        }

        return null;
    }

    /**
     * @return Schema
     */
    public function getSchema(): Schema
    {
        return $this->schema;
    }
}