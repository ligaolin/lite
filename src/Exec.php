<?php

namespace Lin\Lite;

use DateTime;

trait Exec
{
    static protected $conn;
    protected $debug = false;
    static protected string $symbol = '`';
    protected float $start = 0;
    protected $debugLevel = 2; // 1: 打印sql语句+参数，2: 1+耗时+返回行数，3: 2+对象执行时间
    function debug()
    {
        $this->debug = true;
        return $this;
    }

    function debugLevel(int $level)
    {
        $this->debugLevel = max(min($level, 3), 1);
        return $this;
    }

    function show(string $sql, array $args, $time = 0, $count = 0)
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

    function runQuery($sql, array $args = [])
    {
        $start = microtime(true);
        $res = self::query($sql, $args);
        $this->show($sql, $args,  round((microtime(true) - $start) * 1000, 3), count($res));
        return $res;
    }

    function runExec($sql, array $args = [])
    {
        $start = microtime(true);
        self::exec($sql, $args);
        $this->show($sql, $args, round((microtime(true) - $start) * 1000, 3), 0);
    }

    static function query($sql, array $args = [])
    {
        return self::getConn()->query($sql, $args);
    }

    static function exec($sql, array $args = [])
    {
        self::getConn()->exec($sql, $args);
    }

    static function lastInsertId(): int|string
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
    function __destruct()
    {
        if ($this->debug && $this->debugLevel >= 3) {
            $end = microtime(true);
            echo sprintf(
                '[%s] [%s] [total: %.3fms]' . "\n",
                DateTime::createFromFormat('U.u', sprintf('%.6f', $this->start))->format('Y-m-d H:i:s.u'),
                DateTime::createFromFormat('U.u', sprintf('%.6f', $end))->format('Y-m-d H:i:s.u'),
                ($end - $this->start) * 1000
            );
        }
    }
}
