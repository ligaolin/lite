<?php

namespace Lin\Lite;

class Conn implements ConnInterface
{
    protected static array $instances = [];
    protected $conn = null;

    protected function __construct() {}
    protected function __clone() {}

    static function init($host, $user, $password, $database, $port = 3306)
    {
        return self::connect('default', $host, $user, $password, $database, $port);
    }

    static function connect($name, $host, $user, $password, $database, $port = 3306): self
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

    static function getInstance(string $name = 'default'): ?self
    {
        return self::$instances[$name] ?? null;
    }

    function query(string $sql, array $args = [])
    {
        $stmt = $this->stmt($sql, $args);
        $res = mysqli_stmt_get_result($stmt);
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
        mysqli_stmt_close($stmt);
        return $data;
    }

    function exec(string $sql, array $args = [])
    {
        $stmt = $this->stmt($sql, $args);
        mysqli_stmt_close($stmt);
    }

    function lastInsertId(): int|string
    {
        return mysqli_insert_id($this->conn);
    }

    // 设置字段/表名的包裹符 
    function getSymbol(): string
    {
        return '`';
    }

    protected function stmt(string $sql, array $args)
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($args)) {
            $type = '';
            $bind = [];
            foreach ($args as $v) {
                // 自动判断类型
                if (is_int($v)) $type .= 'i';
                elseif (is_float($v)) $type .= 'd';
                else $type .= 's';
                $bind[] = $v;
            }
            mysqli_stmt_bind_param($stmt, $type, ...$bind);
        }

        mysqli_stmt_execute($stmt);
        return $stmt;
    }

    // 开始事务
    function begin()
    {
        mysqli_begin_transaction($this->conn);
    }

    // 提交事务
    function commit()
    {
        mysqli_commit($this->conn);
    }

    // 回滚事务
    function rollback()
    {
        mysqli_rollback($this->conn);
    }
}