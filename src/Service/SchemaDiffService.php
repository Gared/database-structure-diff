<?php
declare(strict_types=1);

namespace DatabaseDiffer\Service;

use DatabaseDiffer\Doctrine\DoctrineSchemaEventListener;
use DatabaseDiffer\Doctrine\EnumType;
use DatabaseDiffer\Exception\NoDatabaseConnectionConfiguredException;
use DatabaseDiffer\Model\Config\Connection;
use DatabaseDiffer\Model\Config\Group;
use DatabaseDiffer\Model\FileParser;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Types\Type;

class SchemaDiffService
{
    /**
     * @var Group
     */
    private $group;

    /**
     * @var string|null
     */
    private $ignoreTable;

    /**
     * @var SchemaDiff
     */
    private $schemaDiff;

    public function __construct(Group $group, string $ignoreTable = null)
    {
        $this->group = $group;
        $this->ignoreTable = $ignoreTable;

        $platform = $this->getDatabasePlatform();
        $fromSchema = $this->getSchemaFromConnection($group->getFromConnection(), $platform);
        $toSchema = $this->getSchemaFromConnection($group->getToConnection(), $platform);

        if ($ignoreTable) {
            $this->ignoreTables($fromSchema, $ignoreTable);
            $this->ignoreTables($toSchema, $ignoreTable);
        }

        $comparator = new Comparator();
        $this->schemaDiff = $comparator->compare($fromSchema, $toSchema);
    }

    /**
     * @return AbstractPlatform
     * @throws NoDatabaseConnectionConfiguredException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        $connection = null;
        if (!$this->group->getFromConnection()->isFile()) {
            $connection = $this->group->getFromConnection();
        } else if (!$this->group->getToConnection()->isFile()) {
            $connection = $this->group->getToConnection();
        } else {
            throw new NoDatabaseConnectionConfiguredException('One of the configured connections must not be of type "file"');
        }

        $schemaManager = $this->getSchemaManager($connection);
        return $schemaManager->getDatabasePlatform();
    }

    /**
     * @param Connection $connection
     * @param AbstractPlatform $platform
     * @return Schema
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    private function getSchemaFromConnection(Connection $connection, AbstractPlatform $platform): Schema
    {
        if ($connection->isFile()) {
            $path = $connection->getConfig()['path'];
            $databaseName = $connection->getConfig()['dbname'];
            $parser = new FileParser($path, $databaseName, $platform);
            return $parser->getSchema();
        }

        $schemaManager = $this->getSchemaManager($connection);
        return $schemaManager->createSchema();
    }

    public function hasDifference(): bool
    {
        return count($this->schemaDiff->changedSequences) > 0
            || count($this->schemaDiff->changedTables) > 0
            || count($this->schemaDiff->newNamespaces) > 0
            || count($this->schemaDiff->newSequences) > 0
            || count($this->schemaDiff->newTables) > 0
            || count($this->schemaDiff->orphanedForeignKeys) > 0
            || count($this->schemaDiff->removedNamespaces) > 0
            || count($this->schemaDiff->removedTables) > 0
            || count($this->schemaDiff->removedSequences) > 0;
    }

    /**
     * @return SchemaDiff
     */
    public function getSchemaDiff(): SchemaDiff
    {
        return $this->schemaDiff;
    }

    public function getSqlAlterCommands(): string
    {
        $data = PHP_EOL . PHP_EOL . '===== ' . $this->group->getFromConnection()->getDescription() . ' => ' . $this->group->getToConnection()->getDescription() . ' =====' . PHP_EOL . PHP_EOL;
        $data .= implode(';' . PHP_EOL, $this->schemaDiff->toSql($this->getDatabasePlatform()));
        return $data;
    }

    /**
     * @param Schema $schema
     * @param string $ignoreTable
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    private function ignoreTables(Schema $schema, string $ignoreTable)
    {
        foreach ($schema->getTableNames() as $tableName) {
            if (preg_match('/' . $ignoreTable . '/i', $tableName)) {
                $schema->dropTable($tableName);
            }
        }
    }

    /**
     * @param Connection $connection
     * @return AbstractSchemaManager
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getSchemaManager(Connection $connection): AbstractSchemaManager
    {
        $evm = new EventManager();
        $evm->addEventListener(Events::onSchemaColumnDefinition, new DoctrineSchemaEventListener());

        $conn = DriverManager::getConnection($connection->getConfig(), null, $evm);
        if (!Type::hasType('general_enum')) {
            Type::addType('general_enum', EnumType::class);
        }
        $conn->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'general_enum');
        $conn->getDatabasePlatform()->registerDoctrineTypeMapping('geometry', 'string');
        $conn->getDatabasePlatform()->registerDoctrineTypeMapping('point', 'float');
        return $conn->getSchemaManager();
    }
}