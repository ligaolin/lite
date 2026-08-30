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
        $needle = '@' . $shortName;
        $len = strlen($doc);
        $i = 0;
        while (($pos = strpos($doc, $needle, $i)) !== false) {
            $after = $pos + strlen($needle);
            // 必须是独立注解名，避免 @Table 匹配到 @TableExtra 之类的词
            $ch = $after < $len ? $doc[$after] : '';
            if (($ch >= 'a' && $ch <= 'z') || ($ch >= 'A' && $ch <= 'Z') || ($ch >= '0' && $ch <= '9') || $ch === '_') {
                $i = $after;
                continue;
            }

            $j = $after;
            while ($j < $len && ($doc[$j] === ' ' || $doc[$j] === "\t" || $doc[$j] === "\r" || $doc[$j] === "\n")) {
                $j++;
            }

            $args = array();
            if ($j < $len && $doc[$j] === '(') {
                $end = self::findMatchingParen($doc, $j);
                if ($end === false) {
                    $i = $after;
                    continue;
                }
                $inner = substr($doc, $j + 1, $end - $j - 1);
                $args = self::parseArgs($inner);
                $i = $end + 1;
            } else {
                $i = $after;
            }
            $results[] = self::newInstance($attrClass, $args);
        }
        return $results;
    }

    private static function findMatchingParen($str, $openPos)
    {
        $len = strlen($str);
        $depth = 0;
        $quote = null;
        for ($i = $openPos; $i < $len; $i++) {
            $c = $str[$i];
            if ($quote !== null) {
                if ($c === '\\' && $i + 1 < $len) {
                    $i++;
                    continue;
                }
                if ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
                continue;
            }
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return false;
    }

    private static function parseArgs($str)
    {
        $args = array();
        $pairs = self::splitTopLevel($str, ',');
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            $pos = self::findTopLevelColon($pair);
            if ($pos !== false) {
                $key = trim(substr($pair, 0, $pos));
                $value = trim(substr($pair, $pos + 1));
                $args[$key] = self::parseValue($value);
            }
        }
        return $args;
    }

    private static function splitTopLevel($str, $delim)
    {
        $parts = array();
        $len = strlen($str);
        $quote = null;
        $depth = 0;
        $start = 0;
        for ($i = 0; $i < $len; $i++) {
            $c = $str[$i];
            if ($quote !== null) {
                if ($c === '\\' && $i + 1 < $len) {
                    $i++;
                    continue;
                }
                if ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
                continue;
            }
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === $delim && $depth === 0) {
                $parts[] = substr($str, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($str, $start);
        return $parts;
    }

    private static function findTopLevelColon($str)
    {
        $len = strlen($str);
        $quote = null;
        $depth = 0;
        for ($i = 0; $i < $len; $i++) {
            $c = $str[$i];
            if ($quote !== null) {
                if ($c === '\\' && $i + 1 < $len) {
                    $i++;
                    continue;
                }
                if ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
                continue;
            }
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === ':' && $depth === 0) {
                return $i;
            }
        }
        return false;
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