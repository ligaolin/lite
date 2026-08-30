<?php

namespace Lin\Lite\attr;

use Lin\Lite\AnnotationReader;

class Type
{
    public $type;
    public $length = null;
    public $scale = null;

    public function __construct($type, $length = null, $scale = null)
    {
        $this->type = $type;
        $this->length = $length;
        $this->scale = $scale;
    }

    static function get($model, $key)
    {
        if (!is_object($model)) {
            return '';
        }
        try {
            $ref = new \ReflectionClass($model);
            if ($ref->hasProperty($key)) {
                $attrs = AnnotationReader::getPropertyAnnotations($ref->getName(), $key, __CLASS__);
                if (!empty($attrs)) {
                    return $attrs[0]->type;
                }
            }
        } catch (\ReflectionException $e) {
        }
        return '';
    }
}