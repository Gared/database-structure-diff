<?php
declare(strict_types=1);

namespace DatabaseDiffer\Doctrine;

use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use Doctrine\DBAL\Schema\Column;

class DoctrineSchemaEventListener
{
    public function onSchemaColumnDefinition(SchemaColumnDefinitionEventArgs $event)
    {
        if (stripos($event->getTableColumn()['Type'], 'enum') === 0) {
            $event->preventDefault();

            $tableColumn = $event->getTableColumn();

            $type = $event->getTableColumn()['Type'];
            $temp = explode('(', $type)[1];
            $types = explode(')', $temp)[0];
            $enumTypes = explode(',', $types);

            $column = new Column($tableColumn['Field'], new EnumType());
            $column->setNotnull($tableColumn['Null'] !== 'YES');
            $column->setDefault($tableColumn['Default']);
            $column->setCustomSchemaOption('enum', $enumTypes);
            $event->setColumn($column);
        }
    }
}