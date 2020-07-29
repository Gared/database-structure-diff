<?php

namespace DatabaseDiffer\Tests\Model;

use DatabaseDiffer\Model\FileParser;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use PHPUnit\Framework\TestCase;

class FileParserTest extends TestCase
{
    public function testParsing(): void
    {
        $parser = new FileParser(__DIR__ . '/../data/simple-structure.sql', 'testdb', new MySQL57Platform());
        $this->assertSame('testdb', $parser->getSchema()->getName());

        $this->assertCount(2, $parser->getSchema()->getTables());

        $firstTable = $parser->getSchema()->getTable('testdb.user');
        $this->assertSame('user', $firstTable->getName());
        $this->assertCount(16, $firstTable->getColumns());

        $userColumn = $firstTable->getColumn('user_id');
        $this->assertSame(true, $userColumn->getAutoincrement());
        $this->assertSame(null, $userColumn->getComment());
        $this->assertInstanceOf(IntegerType::class, $userColumn->getType());

        $loginNameColumn = $firstTable->getColumn('login_name');
        $this->assertSame(false, $loginNameColumn->getAutoincrement());
        $this->assertSame('name for login', $loginNameColumn->getComment());
        $this->assertSame(true, $loginNameColumn->getNotnull());
        $this->assertSame('utf8mb4', $loginNameColumn->getCustomSchemaOption('CHARACTER SET'));
        $this->assertSame(50, $loginNameColumn->getLength());
        $this->assertSame(null, $loginNameColumn->getDefault());
        $this->assertInstanceOf(StringType::class, $loginNameColumn->getType());
    }
}