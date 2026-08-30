<?php

namespace Lin\Lite;

class Conn implements ConnInterface
{
    protected static $instance = null;
    protected static $conn = null;

    protected function __construct() {}
    protected function __clone() {}

    static function init($host, $user, $password, $database, $port = 3306)
    {
        self::$conn = mysqli_connect($host, $user, $password, $database, $port);
        if (mysqli_connect_errno()) {
            throw new \Exception('数据库连接失败');
        }
        self::$instance = new self();
        return self::$instance;
    }

    static function getInstance()
    {
        return self::$instance;
    }

    function query($sql, $args = array())
    {
        $stmt = self::stmt($sql, $args);
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
        $stmt = self::stmt($sql, $args);
        mysqli_stmt_close($stmt);
    }

    function lastInsertId()
    {
        return mysqli_insert_id(self::$conn);
    }

    function getSymbol()
    {
        return '`';
    }

    protected function stmt($sql, $args)
    {
        $stmt = mysqli_prepare(self::$conn, $sql);
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

        mysqli_stmt_execute($stmt);
        return $stmt;
    }

    function begin()
    {
        mysqli_query(self::$conn, "START TRANSACTION");
    }

    function commit()
    {
        mysqli_commit(self::$conn);
    }

    function rollback()
    {
        mysqli_rollback(self::$conn);
    }
}