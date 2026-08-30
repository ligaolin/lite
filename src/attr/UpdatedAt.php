<?php

namespace Lin\Lite\attr;

use Lin\Lite\AnnotationReader;

class UpdatedAt
{
    static function has($model, $key)
    {
        if (!is_object($model)) {
            return false;
        }
        try {
            $ref = new \ReflectionClass($model);
            if ($ref->hasProperty($key)) {
                return AnnotationReader::hasPropertyAnnotation($ref->getName(), $key, __CLASS__);
            }
        } catch (\ReflectionException $e) {
        }
        return false;
    }

    static function now()
    {
        return date('Y-m-d H:i:s');
    }
}