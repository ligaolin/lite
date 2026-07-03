<?php

namespace Lin\Lite;

class Utils
{
    /**
     * 小驼峰
     */
    static function lowerCamelCase(string $input): string
    {
        $short = self::removeNamespace($input);
        $str = str_replace(' ', '', ucwords(preg_replace('/[_\-\s.]+/', ' ', $short)));
        return lcfirst($str);
    }

    /**
     * 大驼峰
     */
    static function upperCamelCase(string $input): string
    {
        $short = self::removeNamespace($input);
        return str_replace(' ', '', ucwords(preg_replace('/[_\-\s.]+/', ' ', $short)));
    }

    /**
     * 下划线命名 
     */
    static function underlineCase(string $input): string
    {
        $short = self::removeNamespace($input);
        $snake = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $short);
        $snake = preg_replace('/[_\-\s.]+/', '_', $snake);
        return strtolower(trim($snake, '_'));
    }

    /**
     * 移除命名空间，只保留短类名
     */
    static function removeNamespace(string $className): string
    {
        $arr = explode('\\', $className);
        return end($arr);
    }
}
