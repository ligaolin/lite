<?php

namespace Lin\Lite;

class Conn implements ConnInterface
{
    // 静态缓存实例
    protected static ?self $instance = null;
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

    static function getInstance(): self
    {
        return self::$instance;
    }

    function query(string $sql, array $args = [])
    {
        $stmt = self::stmt($sql, $args);
        $res = mysqli_stmt_get_result($stmt);
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
        mysqli_stmt_close($stmt);
        return $data;
    }

    function exec(string $sql, array $args = [])
    {
        $stmt = self::stmt($sql, $args);
        mysqli_stmt_close($stmt);
    }

    function lastInsertId(): int|string
    {
        return mysqli_insert_id(self::$conn);
    }

    // 设置字段/表名的包裹符 
    function getSymbol(): string
    {
        return '`';
    }

    protected function stmt(string $sql, array $args)
    {
        $stmt = mysqli_prepare(self::$conn, $sql);
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
        mysqli_begin_transaction(self::$conn);
    }

    // 提交事务
    function commit()
    {
        mysqli_commit(self::$conn);
    }

    // 回滚事务
    function rollback()
    {
        mysqli_rollback(self::$conn);
    }
}