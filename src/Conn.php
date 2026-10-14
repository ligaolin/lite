<?php

namespace Lin\Lite;

class Conn implements ConnInterface
{
    protected static $instances = array();
    protected $conn = null;

    protected function __construct() {}
    protected function __clone() {}

    static function init($host, $user, $password, $database, $port = 3306)
    {
        return self::connect('default', $host, $user, $password, $database, $port);
    }

    static function connect($name, $host, $user, $password, $database, $port = 3306)
    {
        $conn = mysqli_connect($host, $user, $password, $database, $port);
        if (mysqli_connect_errno()) {
            throw new \Exception('数据库连接失败');
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

    function query($sql, $args = array())
    {
        $stmt = $this->stmt($sql, $args);
        $res = mysqli_stmt_get_result($stmt);
        $data = array();
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $data;
    }

    function exec($sql, $args = array())
    {
        $stmt = $this->stmt($sql, $args);
        mysqli_stmt_close($stmt);
    }

    function lastInsertId()
    {
        return mysqli_insert_id($this->conn);
    }

    function getSymbol()
    {
        return '`';
    }

    protected function stmt($sql, $args)
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if ($stmt === false) {
            throw new \Exception('SQL prepare failed: ' . mysqli_error($this->conn));
        }
        if (!empty($args)) {
            $type = '';
            $bind = array();
            foreach ($args as $v) {
                if (is_int($v)) {
                    $type .= 'i';
                } elseif (is_float($v)) {
                    $type .= 'd';
                } else {
                    $type .= 's';
                }
                $bind[] = $v;
            }
            $refs = array();
            $refs[] = $type;
            for ($i = 0; $i < count($bind); $i++) {
                $refs[] = &$bind[$i];
            }
            call_user_func_array(array($stmt, 'bind_param'), $refs);
        }

        if (mysqli_stmt_execute($stmt) === false) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new \Exception('SQL execute failed: ' . $err);
        }
        return $stmt;
    }

    function begin()
    {
        mysqli_query($this->conn, "START TRANSACTION");
    }

    function commit()
    {
        mysqli_commit($this->conn);
    }

    function rollback()
    {
        mysqli_rollback($this->conn);
    }
}