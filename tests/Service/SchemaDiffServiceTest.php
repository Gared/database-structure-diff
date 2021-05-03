<?php
declare(strict_types=1);

namespace DatabaseDiffer\Tests\Service;

use DatabaseDiffer\Model\Config\Connection;
use DatabaseDiffer\Model\Config\Group;
use DatabaseDiffer\Doctrine\EnumType;
use DatabaseDiffer\Service\SchemaDiffService;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
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
        if (!Type::hasType('general_enum')) {
            Type::addType('general_enum', EnumType::class);
        }
        $platform->registerDoctrineTypeMapping('enum', 'general_enum');
        $platform->registerDoctrineTypeMapping('geometry', 'string');

        $userTable = new Table('user');
        $userNewTable = new Table('user_new');
        $userNewTable->addColumn('color', 'general_enum', ['customSchemaOptions' => ['enum' => ['a']]]);
        $userNewTable->addColumn('geometry', 'string', ['length' => 0]);
        $userNewTable->addIndex(['geometry'], 'geometry', ['spatial' => true], ['length' => [32]]);

        $schemaManager = $this->createPartialMock(MySQLSchemaManager::class, ['getDatabasePlatform', 'createSchema']);
        $schemaManager->method('getDatabasePlatform')->willReturn($platform);
        $schemaManager->method('createSchema')->willReturn(
            new Schema(
                [$userTable, $userNewTable],
                [],
                null,
                ['testdb']
            )
        );

        /** @var MockObject|SchemaDiffService $schemaDiffService */
        $schemaDiffService = $this->getMockBuilder(SchemaDiffService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSchemaManager'])
            ->getMock();
        $schemaDiffService->method('getSchemaManager')->willReturn($schemaManager);
        $schemaDiffService->__construct($group);
        $schemaDiff = $schemaDiffService->getSchemaDiff();
        self::assertTrue($schemaDiffService->hasDifference());
        self::assertArrayHasKey('user_new', $schemaDiff->changedTables);
        self::assertCount(1, $schemaDiff->changedTables['user_new']->changedColumns);
        self::assertCount(0, $schemaDiff->changedTables['user_new']->changedIndexes);
        self::assertSame(['enum'], $schemaDiff->changedTables['user_new']->changedColumns['color']->changedProperties);
        self::assertNotEmpty($schemaDiffService->getSqlAlterCommands());
    }

    public function testDatabasesEnum(): void
    {
        $fromConnection = new Connection([
            'dbname' => 'testdb',
            'driver' => 'pdo_mysql',
        ]);
        $toConnection = new Connection([
            'dbname' => 'testdb',
            'driver' => 'pdo_mysql',
        ]);
        $group = new Group($fromConnection, $toConnection);
        $platform = new MySQL57Platform();
        if (!Type::hasType('general_enum')) {
            Type::addType('general_enum', EnumType::class);
        }
        $platform->registerDoctrineTypeMapping('enum', 'general_enum');
        $platform->registerDoctrineTypeMapping('geometry', 'string');

        $schemaManagerFrom = $this->createPartialMock(MySQLSchemaManager::class, ['getDatabasePlatform', 'createSchema']);
        $schemaManagerFrom->method('getDatabasePlatform')->willReturn($platform);
        $schemaManagerFrom->method('createSchema')->willReturn(
            new Schema(
                [new Table('user')],
                [],
                null,
                ['testdb']
            )
        );

        $tableTo = new Table('user2');
        $tableTo->addColumn('test', 'general_enum', ['customSchemaOptions' => ['enum' => ['a']]]);

        $schemaManagerTo = $this->createPartialMock(MySQLSchemaManager::class, ['getDatabasePlatform', 'createSchema']);
        $schemaManagerTo->method('getDatabasePlatform')->willReturn($platform);
        $schemaManagerTo->method('createSchema')->willReturn(
            new Schema(
                [$tableTo],
                [],
                null,
                ['testdb']
            )
        );

        /** @var MockObject|SchemaDiffService $schemaDiffService */
        $schemaDiffService = $this->getMockBuilder(SchemaDiffService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSchemaManager'])
            ->getMock();
        $schemaDiffService->method('getSchemaManager')->willReturnCallback(function($connection) use ($toConnection, $fromConnection, $schemaManagerTo, $schemaManagerFrom) {
            if ($connection === $toConnection) {
                return $schemaManagerTo;
            } else if ($connection === $fromConnection) {
                return $schemaManagerFrom;
            }
        });
        $schemaDiffService->__construct($group);
        self::assertTrue($schemaDiffService->hasDifference());
        self::assertNotEmpty($schemaDiffService->getSqlAlterCommands());
    }
}