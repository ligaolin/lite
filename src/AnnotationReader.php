<?php

namespace Lin\Lite;

class AnnotationReader
{
    public static function getClassAnnotations($className, $attrClass)
    {
        if (is_object($className)) {
            $className = get_class($className);
        }
        $ref = new \ReflectionClass($className);
        $doc = $ref->getDocComment();
        return self::parseAnnotations($doc, $attrClass);
    }

    public static function getPropertyAnnotations($className, $propName, $attrClass)
    {
        if (is_object($className)) {
            $className = get_class($className);
        }
        try {
            $ref = new \ReflectionClass($className);
            if (!$ref->hasProperty($propName)) {
                return array();
            }
            $prop = $ref->getProperty($propName);
            $doc = $prop->getDocComment();
            return self::parseAnnotations($doc, $attrClass);
        } catch (\ReflectionException $e) {
            return array();
        }
    }

    public static function hasPropertyAnnotation($className, $propName, $attrClass)
    {
        return !empty(self::getPropertyAnnotations($className, $propName, $attrClass));
    }

    public static function hasClassAnnotation($className, $attrClass)
    {
        return !empty(self::getClassAnnotations($className, $attrClass));
    }

    private static function parseAnnotations($doc, $attrClass)
    {
        $results = array();
        if (!$doc) {
            return $results;
        }

        $shortName = self::getShortName($attrClass);

        $pattern = '/@' . preg_quote($shortName, '/') . '(?:\s*\(([^)]*)\))?/';
        if (preg_match_all($pattern, $doc, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $args = array();
                if (isset($match[1]) && $match[1] !== '') {
                    $args = self::parseArgs($match[1]);
                }
                $results[] = self::newInstance($attrClass, $args);
            }
        }
        return $results;
    }

    private static function parseArgs($str)
    {
        $args = array();
        $pairs = preg_split('/\s*,\s*/', $str);
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            $pos = strpos($pair, ':');
            if ($pos !== false) {
                $key = trim(substr($pair, 0, $pos));
                $value = trim(substr($pair, $pos + 1));
                $args[$key] = self::parseValue($value);
            }
        }
        return $args;
    }

    private static function parseValue($value)
    {
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }
        if (strtolower($value) === 'null') {
            return null;
        }
        if (is_numeric($value)) {
            return $value + 0;
        }
        if ((strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) ||
            (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1)) {
            return substr($value, 1, -1);
        }
        return $value;
    }

    private static function getShortName($class)
    {
        $parts = explode('\\', $class);
        return end($parts);
    }

    private static function newInstance($class, $args)
    {
        $ref = new \ReflectionClass($class);
        $instance = $ref->newInstanceWithoutConstructor();
        foreach ($args as $key => $value) {
            if ($ref->hasProperty($key)) {
                $prop = $ref->getProperty($key);
                $prop->setAccessible(true);
                $prop->setValue($instance, $value);
            }
        }
        return $instance;
    }
}