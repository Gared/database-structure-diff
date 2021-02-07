<?php
declare(strict_types=1);

namespace DatabaseDiffer\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class EnumType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return "ENUM(".implode(", ", $column['enum']).")";
    }

    public function getName()
    {
        return 'general_enum';
    }
}