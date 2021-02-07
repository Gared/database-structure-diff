<?php
declare(strict_types=1);

namespace DatabaseDiffer\Tests\Model;

use DatabaseDiffer\Doctrine\EnumType;
use DatabaseDiffer\Model\FileParser;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;

class FileParserTest extends TestCase
{
    public function testParsing(): void
    {
        $platform = new MySQL57Platform();
        if (!Type::hasType('general_enum')) {
            Type::addType('general_enum', EnumType::class);
        }
        $platform->registerDoctrineTypeMapping('enum', 'general_enum');
        $platform->registerDoctrineTypeMapping('geometry', 'string');
        $parser = new FileParser(__DIR__ . '/../data/simple-structure.sql', 'testdb', $platform);
        $this->assertSame('testdb', $parser->getSchema()->getName());

        $this->assertCount(3, $parser->getSchema()->getTables());

        $firstTable = $parser->getSchema()->getTable('testdb.user');
        $this->assertSame('user', $firstTable->getName());
        $this->assertCount(16, $firstTable->getColumns());

        $birthPlaceColumn = $firstTable->getColumn('birth_place');
        self::assertSame(null, $birthPlaceColumn->getDefault());

        $isVerifiedColumn = $firstTable->getColumn('is_verified');
        self::assertSame('0', $isVerifiedColumn->getDefault());
        self::assertSame(false, $isVerifiedColumn->getNotnull());

        $foreignKeyClub = $firstTable->getForeignKey('fk_club');
        $this->assertSame(['user_id'], $foreignKeyClub->getColumns());
        $this->assertSame('club', $foreignKeyClub->getForeignTableName());
        $this->assertSame(['club_id'], $foreignKeyClub->getForeignColumns());
        $this->assertSame('CASCADE', $foreignKeyClub->getOption('onUpdate'));
        $this->assertSame('NO ACTION', $foreignKeyClub->getOption('onDelete'));
        $this->assertFalse($firstTable->hasIndex('fk_club'));
        $this->assertTrue($firstTable->hasIndex('fk_club_idx'), print_r($firstTable->getIndexes(), true));

        $this->assertTrue($firstTable->hasIndex('fulltext_street'));
        $fullTextIndex = $firstTable->getIndex('fulltext_street');
        $this->assertSame(['fulltext'], $fullTextIndex->getFlags());

        $userColumn = $firstTable->getColumn('user_id');
        $this->assertSame(true, $userColumn->getAutoincrement());
        $this->assertSame(null, $userColumn->getComment());
        $this->assertInstanceOf(IntegerType::class, $userColumn->getType());

        $loginNameColumn = $firstTable->getColumn('login_name');
        $this->assertSame(false, $loginNameColumn->getAutoincrement());
        $this->assertSame('name for login', $loginNameColumn->getComment());
        $this->assertSame(true, $loginNameColumn->getNotnull());
//        $this->assertSame('utf8mb4', $loginNameColumn->getCustomSchemaOption('CHARACTER SET'));
        $this->assertSame(50, $loginNameColumn->getLength());
        $this->assertSame(null, $loginNameColumn->getDefault());
        $this->assertInstanceOf(StringType::class, $loginNameColumn->getType());


        $clubTable = $parser->getSchema()->getTable('testdb.club');
        $this->assertSame('club', $clubTable->getName());
        $this->assertCount(3, $clubTable->getColumns());

        $ratingColumn = $clubTable->getColumn('rating');
        $this->assertSame(false, $ratingColumn->getAutoincrement());
        $this->assertSame(null, $ratingColumn->getComment());
        $this->assertInstanceOf(DecimalType::class, $ratingColumn->getType());
        $this->assertSame(1, $ratingColumn->getScale());
        $this->assertSame(2, $ratingColumn->getPrecision());

        $categoryColumn = $clubTable->getColumn('category');
        $this->assertSame('test', $categoryColumn->getDefault());

        $userNewTable = $parser->getSchema()->getTable('testdb.user_new');
        $this->assertSame('user_new', $userNewTable->getName());
        $this->assertCount(4, $userNewTable->getColumns());
        $hasIndexOnClubColumn = false;
        foreach ($userNewTable->getIndexes() as $index) {
            if ($index->getColumns() === ['club_id']) {
                $hasIndexOnClubColumn = true;
            }
        }
        $this->assertTrue($hasIndexOnClubColumn);
        $this->assertSame("ENUM('red', 'blue', 'yellow')", $userNewTable->getColumn('color')->getColumnDefinition());
        $this->assertTrue($userNewTable->hasIndex('geometry'));
        $this->assertTrue($userNewTable->hasColumn('geometry'));
        $geometryColumn = $userNewTable->getColumn('geometry');
        self::assertFalse($geometryColumn->getFixed());

        $this->assertTrue($userNewTable->hasIndex('unique_color'));
        $uniqueColorIndex = $userNewTable->getIndex('unique_color');
        static::assertSame(['color'], $uniqueColorIndex->getColumns());
    }
}