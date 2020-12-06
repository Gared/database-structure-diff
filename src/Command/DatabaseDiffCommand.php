<?php
declare(strict_types=1);

namespace DatabaseDiffer\Command;

use DatabaseDiffer\Model\ConfigReader;
use DatabaseDiffer\Service\SchemaDiffService;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DatabaseDiffCommand extends Command
{
    private const FORMAT_SQL = 'sql';
    private const FORMAT_PRETTY = 'pretty';

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
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Define format of output type', self::FORMAT_PRETTY)
            ->addOption('ignore-table', null, InputOption::VALUE_OPTIONAL, 'Regex to ignore table names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);

        $config = new ConfigReader($input->getArgument('config'));
        $format = $input->getOption('format');

        $exitCode = 0;

        foreach ($config->getGroups() as $group) {
            $diffService = new SchemaDiffService($group, $input->getOption('ignore-table'));
            $schemaDiff = $diffService->getSchemaDiff();

            if ($diffService->hasDifference()) {
                $exitCode = 2;
            }

            if ($input->getOption('output-file')) {
                $data = $diffService->getSqlAlterCommands();
                file_put_contents($input->getOption('output-file'), $data, FILE_APPEND);
            } else {
                if ($format === self::FORMAT_SQL) {
                    $io->writeln($diffService->getSqlAlterCommands());
                } else {
                    $io->section($group->getFromConnection()->getDescription() . ' => ' . $group->getToConnection()->getDescription());
                    $this->outputSchemaDiff($io, $schemaDiff, $diffService);
                }
            }
        }

        return $exitCode;
    }

    private function outputSchemaDiff(SymfonyStyle $io, SchemaDiff $schemaDiff, SchemaDiffService $diffService)
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
                    $io->section($changedTable->name . ' => ' . $changedTable->getNewName()->getName());
                } else {
                    $io->section($changedTable->name);
                }

                if (count($changedTable->addedColumns) > 0) {
                    $io->write('Added columns');
                    $list = [];
                    foreach ($changedTable->addedColumns as $addedColumn) {
                        $list[] = $addedColumn->getName() . ': ' . $addedColumn->getType()->getSQLDeclaration($addedColumn->toArray(), $diffService->getDatabasePlatform()) . ' ' . $addedColumn->getLength();
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
                            if ($fromColumnArray[$property] instanceof Type) {
                                $list[] = $property . ': ' . $fromColumnArray[$property]->getSQLDeclaration($fromColumnArray, $diffService->getDatabasePlatform()) . ' => ' . $toColumnArray[$property]->getSQLDeclaration($toColumnArray, $diffService->getDatabasePlatform());    
                            } else {
                                $list[] = $property . ': ' . $this->getPropertyValue($fromColumnArray[$property]) . ' => ' . $this->getPropertyValue($toColumnArray[$property]);
                            }
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
                    $io->write('Removed foreign keys');
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
     * @param string|bool|null $value
     * @return string
     */
    private function getPropertyValue($value): string
    {
        if ($value === true) {
            return 'true';
        } else if ($value === false) {
            return 'false';
        } else if ($value === null) {
            return 'NULL';
        }
        return '"' . $value . '"';
    }
}
