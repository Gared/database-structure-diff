<?php

namespace DatabaseDiffer\Model;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\StringType;
use Exception;
use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Components\Key;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;

class FileParser
{
    /**
     * @var string
     */
    private $filePath;

    /**
     * @var Parser
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

        $this->loadStructureDefinition();
        $this->convertToStructure($databaseName);
    }

    /**
     * Load definition of database structure from sql file
     */
    private function loadStructureDefinition()
    {
        $data = file_get_contents($this->filePath);

        $data = preg_replace('/(INDEX.*) (ASC|DESC)\),/i', '$1),', $data);
        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new Exception('Failed to read sql (propably too big)');
        }

        $this->sqlParser = new Parser($data);
    }

    /**
     * Convert sql parsed file to doctrine database schema
     * @param string $databaseName
     * @throws \Doctrine\DBAL\DBALException
     */
    private function convertToStructure(string $databaseName)
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setName($databaseName);

        $this->schema = new Schema([], [], $schemaConfig);

        foreach ($this->sqlParser->statements as $statement) {
            if ($statement instanceof CreateStatement && $statement->name->table !== null
                && ($statement->name->database === null || $statement->name->database === $databaseName)) {
                $schemaTable = $this->schema->createTable($statement->name->table);
                foreach ($statement->fields as $field) {
                    if ($field->key instanceof Key) {
                        $this->parseIndex($field, $schemaTable);
                    } else {
                        $this->parseColumn($field, $schemaTable);
                    }
                }
            }
        }
    }

    /**
     * @return Schema
     */
    public function getSchema(): Schema
    {
        return $this->schema;
    }

    /**
     * @param CreateDefinition $field
     * @param Table $schemaTable
     * @throws \Doctrine\DBAL\DBALException
     */
    private function parseColumn(CreateDefinition $field, Table $schemaTable): void
    {
        $fieldType = strtolower($field->type->name);
        $column = $schemaTable->addColumn($field->name, $this->platform->getDoctrineTypeMapping($fieldType));
        if ($column->getType() instanceof StringType) {
            $column->setLength($field->type->parameters[0] ?? null);
            if ($fieldType === 'enum') {
                $column->setFixed(false);
            } else {
                $column->setFixed(stripos($fieldType, 'VAR') === false);
            }
        } else if ($column->getType() instanceof DecimalType) {
            $column->setPrecision($field->type->parameters[0] ?? null);
            $column->setScale($field['decimals'] ?? null);
        }

        foreach ($field->type->options->options as $option) {
            switch ($option['name']) {
                case 'CHARACTER SET':
//                    $column->setCustomSchemaOption($option['name'], $option['value']);
                    break;
            }
        }

        foreach ($field->options->options as $option) {
            if (is_array($option)) {
                switch ($option['name']) {
                    case 'COMMENT':
                        $column->setComment($option['value']);
                        break;
                    case 'DEFAULT':
                        $default = $option['value'];
                        if (strtolower($default) === 'null') {
                            $convertedDefault = null;
                        } else if (is_int($default)) {
                            $convertedDefault = (int)$default;
                        } else {
                            $convertedDefault = $default;
                        }
                        $column->setDefault($convertedDefault);
                        break;
                }
            }

            switch ($option) {
                case 'NOT NULL':
                    $column->setNotnull(true);
                    break;
                case 'NULL':
                    $column->setNotnull(false);
                    break;
                case 'AUTO_INCREMENT':
                    $column->setAutoincrement(true);
                    break;
                case 'UNSIGNED':
                    $column->setUnsigned(true);
                    break;
            }
        }
    }

    private function parseIndex(CreateDefinition $field, Table $schemaTable)
    {
        $columnNames = [];
        foreach ($field->key->columns as $col) {
            $columnNames[] = $col['name'];
        }

        if ($field->key->type === 'PRIMARY KEY') {
            $schemaTable->setPrimaryKey($columnNames);
        } else if ($field->key->type === 'FOREIGN KEY') {
            $refColumnNames = $field->references->columns;
            $schemaTable->addForeignKeyConstraint($field->references->table->table, $columnNames, $refColumnNames, [], $field->name);
        } else if ($field->key->type === 'INDEX') {
            $schemaTable->addIndex($columnNames, $field->key->name ?? null);
        } else if ($field->key->type === 'UNIQUE KEY') {
            $schemaTable->addUniqueIndex($columnNames, $field->key->name ?? null);
        }
    }
}