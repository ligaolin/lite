<?php

namespace Lin\Lite\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Type
{
    public function __construct(
        public string $type,
        public ?int $length = null,
        public ?int $scale = null,
    ) {}

    static function get(object $model, string $key): string
    {
        try {
            $ref = new \ReflectionClass($model);
            if ($ref->hasProperty($key)) {
                $prop = $ref->getProperty($key);
                $attrs = $prop->getAttributes(self::class);
                if (!empty($attrs)) {
                    return $attrs[0]->newInstance()->type;
                }
            }
        } catch (\ReflectionException) {
        }
        return '';
    }
}