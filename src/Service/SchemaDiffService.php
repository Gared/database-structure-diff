<?php

namespace DatabaseDiffer\Service;

use DatabaseDiffer\Model\Config\Connection;
use DatabaseDiffer\Model\Config\Group;
use DatabaseDiffer\Model\FileParser;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;

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
     * @param Group $group
     * @return AbstractPlatform
     * @throws DBALException
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        $connection = null;
        if (!$this->group->getFromConnection()->isFile()) {
            $connection = $this->group->getFromConnection();
        } else if (!$this->group->getToConnection()->isFile()) {
            $connection = $this->group->getToConnection();
        }
        $conn = DriverManager::getConnection($connection->getConfig(), new Configuration());
        $conn->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        $conn->getDatabasePlatform()->registerDoctrineTypeMapping('geometry', 'string');
        $conn->getDatabasePlatform()->registerDoctrineTypeMapping('point', 'float');
        return $conn->getSchemaManager()->getDatabasePlatform();
    }

    /**
     * @param Connection $connection
     * @param AbstractPlatform $platform
     * @return Schema
     * @throws DBALException
     */
    private function getSchemaFromConnection(Connection $connection, AbstractPlatform $platform): Schema
    {
        if ($connection->isFile()) {
            $path = $connection->getConfig()['path'];
            $databaseName = $connection->getConfig()['dbname'];
            $parser = new FileParser($path, $databaseName, $platform);
            return $parser->getSchema();
        }

        $conn = DriverManager::getConnection($connection->getConfig(), new Configuration());
        $conn->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        $conn->getDatabasePlatform()->registerDoctrineTypeMapping('geometry', 'string');
        $conn->getDatabasePlatform()->registerDoctrineTypeMapping('point', 'float');
        $sm = $conn->getSchemaManager();
        return $sm->createSchema();
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
     */
    private function ignoreTables(Schema $schema, string $ignoreTable)
    {
        foreach ($schema->getTableNames() as $tableName) {
            if (preg_match('/' . $ignoreTable . '/i', $tableName)) {
                $schema->dropTable($tableName);
            }
        }
    }
}