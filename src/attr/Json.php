<?php

namespace Lin\Lite\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Json
{
    static function has(object $model, string $key): bool
    {
        try {
            $ref = new \ReflectionClass($model);
            if ($ref->hasProperty($key)) {
                $prop = $ref->getProperty($key);
                return !empty($prop->getAttributes(self::class));
            }
        } catch (\ReflectionException) {
        }
        return false;
    }
}