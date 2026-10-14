<?php

namespace Lin\Lite;

use DateTime;

trait Exec
{
    static protected $conn;
    protected $instanceConn;
    protected $debug = false;
    static protected $symbol = '`';
    protected $start = 0;
    protected $debugLevel = 2;

    function getQuoteSymbol()
    {
        if ($this->instanceConn) {
            return $this->instanceConn->getSymbol();
        }
        return self::$symbol;
    }

    function debug()
    {
        $this->debug = true;
        return $this;
    }

    function debugLevel($level)
    {
        $this->debugLevel = max(min($level, 3), 1);
        return $this;
    }

    function show($sql, $args, $time = 0, $count = 0)
    {
        if ($this->debug) {
            if ($this->debugLevel > 1) {
                echo '[row: ' . $count . '] [' . $time . 'ms] [sql: ' . $sql . '] [args: ' . json_encode($args) . ']' . "\n";
            } else {
                echo '[sql: ' . $sql . '] [args: ' . json_encode($args) . ']' . "\n";
            }
        }
    }

    static function setConn(ConnInterface $conn)
    {
        self::$conn = $conn;
        self::$symbol = $conn->getSymbol();
    }

    static function getConn()
    {
        if (!self::$conn) {
            self::setConn(Conn::getInstance());
        }
        return self::$conn;
    }

    function runQuery($sql, $args = array())
    {
        $start = microtime(true);
        if ($this->instanceConn) {
            $res = $this->instanceConn->query($sql, $args);
        } else {
            $res = self::query($sql, $args);
        }
        $this->show($sql, $args, round((microtime(true) - $start) * 1000, 3), count($res));
        return $res;
    }

    function runExec($sql, $args = array())
    {
        $start = microtime(true);
        if ($this->instanceConn) {
            $this->instanceConn->exec($sql, $args);
        } else {
            self::exec($sql, $args);
        }
        $this->show($sql, $args, round((microtime(true) - $start) * 1000, 3), 0);
    }

    static function query($sql, $args = array())
    {
        return self::getConn()->query($sql, $args);
    }

    static function exec($sql, $args = array())
    {
        self::getConn()->exec($sql, $args);
    }

    static function lastInsertId()
    {
        return self::getConn()->lastInsertId();
    }

    static function begin()
    {
        self::getConn()->begin();
    }

    static function commit()
    {
        self::getConn()->commit();
    }

    static function rollback()
    {
        self::getConn()->rollback();
    }

    function __construct()
    {
        $this->start = microtime(true);
    }

    protected function getPropAttr(\ReflectionProperty $prop, $attrClass)
    {
        $attrs = AnnotationReader::getPropertyAnnotations($prop->getDeclaringClass()->getName(), $prop->getName(), $attrClass);
        if (!empty($attrs)) {
            return $attrs[0];
        }
        return null;
    }

    private function formatMicrotime($mt)
    {
        $dt = new DateTime();
        $dt->setTimestamp((int)$mt);
        $us = sprintf('%06d', ($mt - (int)$mt) * 1000000);
        return $dt->format('Y-m-d H:i:s') . '.' . $us;
    }

    function __destruct()
    {
        if ($this->debug && $this->debugLevel >= 3) {
            $end = microtime(true);
            echo sprintf(
                '[%s] [%s] [total: %.3fms]' . "\n",
                $this->formatMicrotime($this->start),
                $this->formatMicrotime($end),
                ($end - $this->start) * 1000
            );
        }
    }
}