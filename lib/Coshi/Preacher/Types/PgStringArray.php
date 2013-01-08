<?php
namespace Coshi\Preacher\Types;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * PgStringArray
 *
 * @author Krzysztof Ozog <krzysztof.ozog@codesushi.co>
 */
class PgStringArray extends Type
{
    const PG_STRING_ARRAY = '_text';

    public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'text[]';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value !== "{NULL}" and $value !== "{}") {
            $value = trim($value, '{}');
            return explode(',', $value);
        }
        return array();

    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (is_array($value)) {
            $value = join(', ', $value);
        }
        return sprintf('{%s}', $value);
    }

    public function getName()
    {
        return self::PG_STRING_ARRAY;
    }
}
