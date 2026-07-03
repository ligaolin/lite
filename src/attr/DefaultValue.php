<?php

namespace Lin\Lite\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DefaultValue
{
    public function __construct(public mixed $value) {}

    static function get(object $model, string $key): mixed
    {
        try {
            $ref = new \ReflectionClass($model);
            if ($ref->hasProperty($key)) {
                $prop = $ref->getProperty($key);
                $attrs = $prop->getAttributes(self::class);
                if (!empty($attrs)) {
                    return $attrs[0]->newInstance()->value;
                }
            }
        } catch (\ReflectionException) {
        }
        return null;
    }

    static function toSql(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return "'" . addslashes((string)$value) . "'";
    }
}