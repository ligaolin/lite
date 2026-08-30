<?php

namespace Lin\Lite;

class Utils
{
    /**
     * 小驼峰
     */
    static function lowerCamelCase($input)
    {
        $short = self::removeNamespace($input);
        $str = str_replace(' ', '', ucwords(preg_replace('/[_\-\s.]+/', ' ', $short)));
        return lcfirst($str);
    }

    /**
     * 大驼峰
     */
    static function upperCamelCase($input)
    {
        $short = self::removeNamespace($input);
        return str_replace(' ', '', ucwords(preg_replace('/[_\-\s.]+/', ' ', $short)));
    }

    /**
     * 下划线命名 
     */
    static function underlineCase($input)
    {
        $short = self::removeNamespace($input);
        $snake = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $short);
        $snake = preg_replace('/[_\-\s.]+/', '_', $snake);
        return strtolower(trim($snake, '_'));
    }

    /**
     * 移除命名空间，只保留短类名
     */
    static function removeNamespace($className)
    {
        $arr = explode('\\', $className);
        return end($arr);
    }

    /**
     * str_contains polyfill for PHP < 8.0
     */
    static function strContains($haystack, $needle)
    {
        return strpos($haystack, $needle) !== false;
    }

    /**
     * str_ends_with polyfill for PHP < 8.0
     */
    static function strEndsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }
}