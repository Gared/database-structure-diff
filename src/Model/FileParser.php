<?php
declare(strict_types=1);

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
    private function loadStructureDefinition(): void
    {
        $data = file_get_contents($this->filePath);

        $data = preg_replace_callback(
            '/(INDEX .*)\(((,?\s*\S+ (ASC|DESC))+)\)/',
            function ($match) {
                return $match[1] . '(' . str_replace('ASC', '', $match[2]) . ')';
            },
            $data
        );
        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new Exception('Failed to read sql (probably too big)');
        }

        $this->sqlParser = new Parser($data);
    }

    /**
     * Convert sql parsed file to doctrine database schema
     * @param string $databaseName
     * @throws \Doctrine\DBAL\DBALException
     */
    private function convertToStructure(string $databaseName): void
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
        $column->setNotnull(false);
        $column->setFixed(true);
        if ($column->getType() instanceof StringType) {
            $column->setLength($field->type->parameters[0] ?? null);
            if ($fieldType === 'enum') {
                $column->setColumnDefinition('ENUM(' . implode(', ', $field->type->parameters) . ')');
                $column->setFixed(false);
            } else if ($fieldType === 'geometry') {
                $column->setFixed(false);
            } else {
                $column->setFixed(stripos($fieldType, 'VAR') === false);
            }
        } else if ($column->getType() instanceof DecimalType) {
            $column->setPrecision($field->type->parameters[0] ?? null);
            $column->setScale($field->type->parameters[1] ?? null);
        }

        foreach ($field->type->options->options as $option) {
            if (is_array($option)) {
                switch (strtoupper($option['name'])) {
                    case 'CHARACTER SET':
//                    $column->setCustomSchemaOption($option['name'], $option['value']);
                        break;
                }
            } else {
                switch (strtoupper($option)) {
                    case 'UNSIGNED':
                        $column->setUnsigned(true);
                        break;
                }
            }
        }

        foreach ($field->options->options as $option) {
            if (is_array($option)) {
                switch (strtoupper($option['name'])) {
                    case 'COMMENT':
                        $column->setComment($option['value']);
                        break;
                    case 'DEFAULT':
                        $default = $option['value'];
                        if (strtolower($default) === 'null') {
                            $convertedDefault = null;
                        } else if (is_int($default)) {
                            $convertedDefault = (int)$default;
                        } else if (is_string($default)) {
                            $default = str_replace('"', "", $default);
                            $default = str_replace("'", "", $default);
                            $convertedDefault = $default;
                        } else {
                            $convertedDefault = $default;
                        }
                        $column->setDefault($convertedDefault);
                        break;
                }
            } else {
                switch (strtoupper($option)) {
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
    }

    private function parseIndex(CreateDefinition $field, Table $schemaTable): void
    {
        $columnNames = [];
        foreach ($field->key->columns as $col) {
            $columnNames[] = $col['name'];
        }

        switch (strtoupper($field->key->type)) {
            case 'PRIMARY KEY':
                $schemaTable->setPrimaryKey($columnNames);
                break;
            case 'FOREIGN KEY':
                $refColumnNames = $field->references->columns;
                $options = [];
                foreach ($field->references->options as $optionContainer) {
                    foreach ($optionContainer as $option) {
                        switch (strtoupper($option['name'])) {
                            case 'ON UPDATE':
                                $options['onUpdate'] = $option['value'];
                                break;
                            case 'ON DELETE':
                                $options['onDelete'] = $option['value'];
                                break;
                        }
                    }
                }
                $schemaTable->addForeignKeyConstraint($field->references->table->table, $columnNames, $refColumnNames, $options, $field->name);
                break;
            case 'INDEX':
            case 'KEY':
                $schemaTable->addIndex($columnNames, $field->key->name ?? null);
                break;
            case 'UNIQUE KEY':
            case 'UNIQUE INDEX':
                $schemaTable->addUniqueIndex($columnNames, $field->key->name ?? null);
                break;
            case 'FULLTEXT KEY':
            case 'FULLTEXT INDEX':
                $schemaTable->addIndex($columnNames, $field->key->name ?? null, ['fulltext']);
                break;
            case 'SPATIAL KEY':
            case 'SPATIAL INDEX':
                $schemaTable->addIndex($columnNames, $field->key->name ?? null, ['spatial'], ['lengths' => [32]]);
                break;
            default:
                if ($field->isConstraint === true && strtoupper($field->key->type) === 'UNIQUE') {
                    $schemaTable->addUniqueIndex($columnNames, $field->name ?? null);
                }
                break;
        }
    }
}
