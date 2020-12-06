<?php
declare(strict_types=1);

namespace DatabaseDiffer\Tests\Command;

use DatabaseDiffer\Command\DatabaseDiffCommand;
use DatabaseDiffer\Exception\FileNotFoundException;
use DatabaseDiffer\Exception\NoDatabaseConnectionConfiguredException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class DatabaseDiffCommandTest extends TestCase
{
    public function testExecuteNoFile()
    {
        $this->expectException(FileNotFoundException::class);

        $command = new DatabaseDiffCommand();
        $input = new ArrayInput([
            'config' => 'not_found_file'
        ]);
        $output = new NullOutput();
        $command->run($input, $output);
    }

    public function testExecuteNotBothFile()
    {
        $this->expectException(NoDatabaseConnectionConfiguredException::class);
        $command = new DatabaseDiffCommand();
        $input = new ArrayInput([
            'config' => __DIR__ . '/../data/files_only_config.php'
        ]);
        $output = new NullOutput();
        $command->run($input, $output);
    }
}