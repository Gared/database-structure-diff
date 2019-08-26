<?php

namespace DatabaseDiffer\Command;

use DatabaseDiffer\Model\Config\Connection;
use DatabaseDiffer\Model\Config\Group;
use DatabaseDiffer\Model\ConfigReader;
use DatabaseDiffer\Model\FileParser;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DatabaseDiffCommand extends Command
{
    /**
     * Command configuration
     */
    protected function configure()
    {
        $this
            ->setName('database:calculate-diff')
            ->setDescription('Calculates the diff between to database schemes')
            ->addArgument('config', InputArgument::REQUIRED, 'Configuration file where to load schemes from')
            ->addOption('output-file', 'o', InputOption::VALUE_OPTIONAL, 'Define output file to store migration script')
            ->addOption('ignore-table', null, InputOption::VALUE_OPTIONAL, 'Regex to ignore table names');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws DBALException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $config = new ConfigReader($input->getArgument('config'));

        foreach ($config->getGroups() as $group) {
            $platform = $this->getDatabasePlatform($group);
            $fromSchema = $this->getSchemaFromConnection($group->getFromConnection(), $platform);
            $toSchema = $this->getSchemaFromConnection($group->getToConnection(), $platform);

            if ($input->getOption('ignore-table')) {
                $this->ignoreTables($fromSchema, $input->getOption('ignore-table'));
                $this->ignoreTables($toSchema, $input->getOption('ignore-table'));
            }

            $schemaDiff = $this->diffSchema($fromSchema, $toSchema);

            if ($input->getOption('output-file')) {
                $data = PHP_EOL . PHP_EOL . '===== ' . $group->getFromConnection()->getDescription() . ' => ' . $group->getToConnection()->getDescription() . ' =====' . PHP_EOL . PHP_EOL;
                $data .= implode(';' . PHP_EOL, $schemaDiff->toSql($platform));
                file_put_contents($input->getOption('output-file'), $data, FILE_APPEND);
            } else {
                $io->section($group->getFromConnection()->getDescription() . ' => ' . $group->getToConnection()->getDescription());
                $this->outputSchemaDiff($io, $schemaDiff);
            }
        }
    }

    /**
     * @param SymfonyStyle $io
     * @param SchemaDiff $schemaDiff
     */
    private function outputSchemaDiff(SymfonyStyle $io, SchemaDiff $schemaDiff)
    {
        if (count($schemaDiff->newTables) > 0) {
            $io->title('New tables');
            $list = [];
            foreach ($schemaDiff->newTables as $newTable) {
                $columnList = [];
                foreach ($newTable->getColumns() as $column) {
                    $columnList[] = $column->getName();
                }
                $list[] = $newTable->getName() . ': ' . implode(', ', $columnList);
            }
            $io->listing($list);
        }

        if (count($schemaDiff->removedTables) > 0) {
            $io->title('Removed tables');
            $list = [];
            foreach ($schemaDiff->removedTables as $removedTable) {
                $list[] = $removedTable->getName();
            }
            $io->listing($list);
        }

        if (count($schemaDiff->changedTables) > 0) {
            $io->title('Changed tables');
            foreach ($schemaDiff->changedTables as $changedTable) {
                if ($changedTable->getNewName() !== false) {
                    $io->section($changedTable->name . ' => ' . $changedTable->getNewName());
                } else {
                    $io->section($changedTable->name);
                }

                if (count($changedTable->addedColumns) > 0) {
                    $io->write('Added columns');
                    $list = [];
                    foreach ($changedTable->addedColumns as $addedColumn) {
                        $list[] = $addedColumn->getName() . ': ' . $addedColumn->getType() . ' ' . $addedColumn->getLength();
                    }
                    $io->listing($list);
                }

                if (count($changedTable->changedColumns) > 0) {
                    $io->writeln('Changed columns');
                    foreach ($changedTable->changedColumns as $changedColumn) {
                        $io->writeln($changedColumn->column->getName());

                        $fromColumnArray = $changedColumn->fromColumn->toArray();
                        $toColumnArray = $changedColumn->column->toArray();

                        $list = [];
                        foreach ($changedColumn->changedProperties as $property) {
                            $list[] = $property . ': ' . $this->getPropertyValue($fromColumnArray[$property]) . ' => ' . $this->getPropertyValue($toColumnArray[$property]);
                        }
                        $io->listing($list);
                    }
                }

                if (count($changedTable->renamedColumns) > 0) {
                    $io->write('Renamed columns');
                    $list = [];
                    foreach ($changedTable->renamedColumns as $oldColumnName => $renamedColumn) {
                        $list[] = $oldColumnName . ' => ' . $renamedColumn->getName();
                    }
                    $io->listing($list);
                }

                if (count($changedTable->removedColumns) > 0) {
                    $io->write('Removed columns');
                    $list = [];
                    foreach ($changedTable->removedColumns as $removedColumn) {
                        $list[] = $removedColumn->getName();
                    }
                    $io->listing($list);
                }

                if (count($changedTable->addedIndexes) > 0) {
                    $io->write('Added indexes');
                    $list = [];
                    foreach ($changedTable->addedIndexes as $addedIndex) {
                        $list[] = $addedIndex->getName() . ': ' . implode(', ', $addedIndex->getColumns());
                    }
                    $io->listing($list);
                }

                if (count($changedTable->changedIndexes) > 0) {
                    $io->write('Changed indexes');
                    $list = [];
                    foreach ($changedTable->changedIndexes as $changedIndex) {
                        $list[] = $changedIndex->getName();
                    }
                    $io->listing($list);
                }

                if (count($changedTable->renamedIndexes) > 0) {
                    $io->write('Renamed indexes');
                    $list = [];
                    foreach ($changedTable->renamedIndexes as $oldIndexName => $renamedIndex) {
                        $list[] = $oldIndexName . ' => ' . $renamedIndex->getName();
                    }
                    $io->listing($list);
                }

                if (count($changedTable->removedIndexes) > 0) {
                    $io->write('Removed indexes');
                    $list = [];
                    foreach ($changedTable->removedIndexes as $removedIndex) {
                        $list[] = $removedIndex->getName();
                    }
                    $io->listing($list);
                }

                if (count($changedTable->addedForeignKeys) > 0) {
                    $io->write('Added foreign keys');
                    $list = [];
                    foreach ($changedTable->addedForeignKeys as $addedForeignKey) {
                        $list[] = $addedForeignKey->getName() . ': (' . implode(', ', $addedForeignKey->getColumns()) . ') => ' . $addedForeignKey->getForeignTableName() . ' (' . implode(', ', $addedForeignKey->getForeignColumns()) . ')';
                    }
                    $io->listing($list);
                }

                if (count($changedTable->changedForeignKeys) > 0) {
                    $io->write('Changed foreign keys');
                    $list = [];
                    foreach ($changedTable->changedForeignKeys as $changedForeignKey) {
                        $list[] = $changedForeignKey->getName() . ': ' . implode(', ', $changedForeignKey->getColumns());
                    }
                    $io->listing($list);
                }

                if (count($changedTable->removedForeignKeys) > 0) {
                    $io->write('Changed foreign keys');
                    $list = [];
                    foreach ($changedTable->removedForeignKeys as $removedForeignKey) {
                        $list[] = $removedForeignKey->getName();
                    }
                    $io->listing($list);
                }
            }
        }
    }

    /**
     * @param $value
     * @return string
     */
    private function getPropertyValue($value): string
    {
        if ($value === true) {
            return 'true';
        } else if ($value === false) {
            return 'false';
        } else if ($value === null) {
            return '';
        }
        return '"' . $value . '"';
    }

    /**
     * @param Group $group
     * @return AbstractPlatform
     * @throws DBALException
     */
    private function getDatabasePlatform(Group $group): AbstractPlatform
    {
        $connection = null;
        if (!$group->getFromConnection()->isFile()) {
            $connection = $group->getFromConnection();
        } else if (!$group->getToConnection()->isFile()) {
            $connection = $group->getToConnection();
        }
        $conn = DriverManager::getConnection($connection->getConfig(), new Configuration());
        $conn->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
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
        $sm = $conn->getSchemaManager();
        return $sm->createSchema();
    }

    /**
     * @param Schema $fromSchema
     * @param Schema $toSchema
     * @return SchemaDiff
     */
    private function diffSchema(Schema $fromSchema, Schema $toSchema): SchemaDiff
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($fromSchema, $toSchema);
        return $schemaDiff;
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