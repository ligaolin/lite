<?php

namespace Lin\Lite;

class PgsqlConn implements ConnInterface
{
    protected static $instances = array();
    protected $conn = null;

    protected function __construct() {}
    protected function __clone() {}

    static function connect($name, $host, $user, $password, $database, $port = 5432)
    {
        $conn = pg_connect("host=$host port=$port dbname=$database user=$user password=$password");
        if (!$conn) {
            throw new \Exception('PostgreSQL连接失败');
        }
        $instance = new self();
        $instance->conn = $conn;
        self::$instances[$name] = $instance;
        return $instance;
    }

    static function getInstance($name = 'default')
    {
        return isset(self::$instances[$name]) ? self::$instances[$name] : null;
    }

    protected function toPgsqlPlaceholders($sql)
    {
        $out = '';
        $n = 1;
        $inStr = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($ch === "'") {
                $inStr = !$inStr;
                $out .= $ch;
            } elseif (!$inStr && $ch === '?') {
                $out .= '$' . ($n++);
            } else {
                $out .= $ch;
            }
        }
        return $out;
    }

    function query($sql, $args = array())
    {
        $sql = $this->toPgsqlPlaceholders($sql);
        $result = pg_query_params($this->conn, $sql, $args);
        if ($result === false) {
            throw new \Exception('PostgreSQL query failed: ' . pg_last_error($this->conn));
        }
        $data = array();
        while ($row = pg_fetch_assoc($result)) {
            $data[] = $row;
        }
        pg_free_result($result);
        return $data;
    }

    function exec($sql, $args = array())
    {
        $sql = $this->toPgsqlPlaceholders($sql);
        $result = pg_query_params($this->conn, $sql, $args);
        if ($result === false) {
            throw new \Exception('PostgreSQL exec failed: ' . pg_last_error($this->conn));
        }
        pg_free_result($result);
    }

    function lastInsertId()
    {
        $result = pg_query($this->conn, "SELECT LASTVAL()");
        if ($result === false) {
            throw new \Exception('PostgreSQL lastInsertId failed: ' . pg_last_error($this->conn));
        }
        $row = pg_fetch_row($result);
        pg_free_result($result);
        return $row[0];
    }

    function getSymbol()
    {
        return '"';
    }

    function begin()
    {
        pg_query($this->conn, "BEGIN");
    }

    function commit()
    {
        pg_query($this->conn, "COMMIT");
    }

    function rollback()
    {
        pg_query($this->conn, "ROLLBACK");
    }
}