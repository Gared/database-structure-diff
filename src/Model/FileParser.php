<?php
declare(strict_types=1);

namespace DatabaseDiffer\Model;

use DatabaseDiffer\Doctrine\EnumType;
use DatabaseDiffer\Model\Parser\Item;
use DatabaseDiffer\Model\Parser\ItemSet;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\StringType;
use PHPSQLParser\PHPSQLParser;

class FileParser
{
    /**
     * @var string
     */
    private $filePath;

    /**
     * @var array
     */
    private $parsedData = [];

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
     * @throws \Doctrine\DBAL\Exception
     * @throws SchemaException
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

        $data = preg_replace('!/\*.*?\*/!s', '', $data);
        $data = preg_replace('%(--.*)%','', $data);

        $parser = new PHPSQLParser();
        $queries = explode(";", $data);
        foreach ($queries as $query) {
            $parsed = $parser->parse($query);
            if (!$parsed || !array_key_exists('TABLE', $parsed)) {
                continue;
            }

            $parsed['TABLE']['create-def']['fields'] = [];
            foreach ($parsed['TABLE']['create-def']['sub_tree'] as $field) {
                $parsed['TABLE']['create-def']['fields'][] = $this->parseItem($field);
            }
            $this->parsedData[] = $parsed;
        }
    }

    private function parseItem(array $field): Item
    {
        $itemSet = new ItemSet();
        if (array_key_exists('sub_tree', $field) && is_array($field['sub_tree'])) {
            foreach ($field['sub_tree'] as $subField) {
                if (is_array($subField)) {
                    $itemSet->add($this->parseItem($subField));
                }
            }
        }

        return new Item($field, $itemSet);
    }

    /**
     * Convert sql parsed file to doctrine database schema
     * @param string $databaseName
     * @throws \Doctrine\DBAL\Exception
     * @throws SchemaException
     */
    private function convertToStructure(string $databaseName): void
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setName($databaseName);

        $this->schema = new Schema([], [], $schemaConfig);

        foreach ($this->parsedData as $query) {
            if (is_array($query) && array_key_exists('CREATE', $query)) {
                $schemaTable = $this->schema->createTable($query['TABLE']['no_quotes']['parts'][1]);
                /** @var Item $field */
                foreach ($query['TABLE']['create-def']['fields'] as $field) {
                    if ($field->data['expr_type'] !== 'column-def') {
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
     * @param Item $field
     * @param Table $schemaTable
     * @throws \Doctrine\DBAL\Exception
     * @throws SchemaException
     */
    private function parseColumn(Item $field, Table $schemaTable): void
    {
        $name = $field->subTree->getItem('colref')->data['no_quotes']['parts'][0];
        $dataTypeItem = $field->subTree->getItem('column-type')->subTree->getItem('data-type');
        $fieldType = $dataTypeItem !== null ? $dataTypeItem->getBaseExpr() : null;

        if ($fieldType === null) {
            $fieldType = 'ENUM';
        }

        $column = $schemaTable->addColumn($name, $this->platform->getDoctrineTypeMapping($fieldType));
        $columnTypeItem = $field->subTree->getItem('column-type');
        $column->setNotnull(!$columnTypeItem->data['nullable']);
        $column->setFixed(true);
        if ($column->getType() instanceof StringType) {
            $bracketExpressionItem = $columnTypeItem->subTree->getItem('bracket_expression');
            $column->setLength($bracketExpressionItem ? (int)trim($bracketExpressionItem->getBaseExpr(), '()') : null);
            if ($fieldType === 'geometry') {
                $column->setFixed(false);
            } else {
                $column->setFixed(stripos($fieldType, 'VAR') === false);
            }
        } else if ($column->getType() instanceof DecimalType) {
            $bracketExpressionItem = $columnTypeItem->subTree->getItem('bracket_expression');
            if ($bracketExpressionItem !== null) {
                $constItems = iterator_to_array($bracketExpressionItem->subTree->getItems('const'));
                $column->setPrecision($constItems[0]->getBaseExpr() ?? null);
                $column->setScale($constItems[1]->getBaseExpr() ?? null);
            }
        } else if ($column->getType() instanceof EnumType) {
            $column->setColumnDefinition($field->getBaseExpr());
            $column->setFixed(false);
            $enumValues = [];
            foreach ($field->subTree->getItem('column-type')->subTree as $subItem) {
                if ($subItem->getBaseExpr() === 'ENUM') {
                    foreach ($subItem->subTree->getIterator()[0]->data as $enumDefinition) {
                        $enumValues[] = $enumDefinition['base_expr'];
                    }
                }
            }
            $column->setCustomSchemaOption('enum', $enumValues);
        }

        $column->setUnsigned($columnTypeItem->subTree->getItem('data-type')->data['unsigned'] ?? false);
        if ($columnTypeItem->data['primary']) {
            $schemaTable->setPrimaryKey([$column->getName()]);
        }
        $column->setAutoincrement($columnTypeItem->data['auto_inc']);
        if ($columnTypeItem->subTree->getItem('comment')) {
            $column->setComment($columnTypeItem->subTree->getItem('comment')->getBaseExpr());
        }
        if ($columnTypeItem->subTree->getItem('default-value')) {
            $column->setDefault($columnTypeItem->subTree->getItem('default-value')->getBaseExpr());
        }
    }

    private function parseIndex(Item $field, Table $schemaTable): void
    {
        $columnListItem = $field->subTree->getItem('column-list');
        if ($columnListItem === null) {
            $columnNames = [$field->subTree->getItem('colref')->getName()];
        } else {
            $columnNames = [];
            foreach ($columnListItem->data['sub_tree'] as $col) {
                $columnNames[] = $col['no_quotes']['parts'][0];
            }
        }

        switch ($field->getExprType()) {
            case 'unique-index':
                $constItem = $field->subTree->getItem('const');
                $indexName = ($constItem === null ? $field->subTree->getItem('constraint')->data['sub_tree']['base_expr'] : $constItem->getBaseExpr()) ?? null;
                $schemaTable->addUniqueIndex($columnNames, $indexName);
                break;
            case 'spatial-index':
                $schemaTable->addIndex($columnNames, $columnNames[0] ?? null, ['spatial'], ['lengths' => [32]]);
                break;
            case 'fulltext-index':
                $schemaTable->addIndex($columnNames, $field->subTree->getItem('const')->getBaseExpr() ?? null, ['fulltext']);
                break;
            case 'foreign-key':
                $foreignRefItem = $field->subTree->getItem('foreign-ref');
                $refColumnNames = [];
                foreach ($foreignRefItem->subTree->getItem('column-list')->subTree->getItems('index-column') as $indexItem) {
                    $refColumnNames[] = $indexItem->getName();
                }
                $options = [
                    'onDelete' => $foreignRefItem->data['on_delete'],
                    'onUpdate' => $foreignRefItem->data['on_update'],
                ];
                $name = trim($field->subTree->getItem('constraint')->data['sub_tree']['base_expr'], '`');
                $schemaTable->addForeignKeyConstraint($foreignRefItem->subTree->getItem('table')->getName(), $columnNames, $refColumnNames, $options, $name);
                break;
            case 'index':
                $schemaTable->addIndex($columnNames, $field->subTree->getItem('const')->getBaseExpr() ?? null);
                break;
            case 'primary-key':
                $schemaTable->setPrimaryKey($columnNames);
                break;
        }
    }
}
