<?php

namespace Lin\Lite\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DisableCreate
{
    static function has(object $model, string $key): bool
    {
        try {
            $ref = new \ReflectionClass($model);
            if ($ref->hasProperty($key)) {
                return !empty($ref->getProperty($key)->getAttributes(self::class));
            }
        } catch (\ReflectionException) {
        }
        return false;
    }
}