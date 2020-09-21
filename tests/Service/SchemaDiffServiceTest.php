<?php
declare(strict_types=1);

namespace DatabaseDiffer\Tests\Service;

use DatabaseDiffer\Model\Config\Connection;
use DatabaseDiffer\Model\Config\Group;
use DatabaseDiffer\Service\SchemaDiffService;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SchemaDiffServiceTest extends TestCase
{
    public function testSimple(): void
    {
        $fromConnection = new Connection([
            'dbname' => 'testdb',
            'path' => __DIR__ . '/../data/simple-structure.sql',
            'driver' => 'file',
        ]);
        $toConnection = new Connection([
            'dbname' => 'testdb',
            'driver' => 'pdo_mysql',
        ]);
        $group = new Group($fromConnection, $toConnection);
        $platform = new MySQL57Platform();
        $platform->registerDoctrineTypeMapping('enum', 'string');
        $platform->registerDoctrineTypeMapping('geometry', 'string');

        $schemaManager = $this->createPartialMock(MySqlSchemaManager::class, ['getDatabasePlatform', 'createSchema']);
        $schemaManager->method('getDatabasePlatform')->willReturn($platform);
        $schemaManager->method('createSchema')->willReturn(new Schema(
            [new Table('user')],
            [],
            null,
            ['testdb'])
        );

        /** @var MockObject|SchemaDiffService $schemaDiffService */
        $schemaDiffService = $this->getMockBuilder(SchemaDiffService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSchemaManager'])
            ->getMock();
        $schemaDiffService->method('getSchemaManager')->willReturn($schemaManager);
        $schemaDiffService->__construct($group);
        $this->assertTrue($schemaDiffService->hasDifference());
        $this->assertNotEmpty($schemaDiffService->getSqlAlterCommands());
    }
}