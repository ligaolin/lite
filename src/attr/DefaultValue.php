<?php

namespace Lin\Lite\attr;

use Lin\Lite\AnnotationReader;

class DefaultValue
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    static function get($model, $key)
    {
        if (!is_object($model)) {
            return null;
        }
        try {
            $ref = new \ReflectionClass($model);
            if ($ref->hasProperty($key)) {
                $attrs = AnnotationReader::getPropertyAnnotations($ref->getName(), $key, __CLASS__);
                if (!empty($attrs)) {
                    return $attrs[0]->value;
                }
            }
        } catch (\ReflectionException $e) {
        }
        return null;
    }

    static function toSql($value)
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