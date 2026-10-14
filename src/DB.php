<?php

namespace Lin\Lite;

class DB
{
    // trait 依赖链保持“线性”：
    //   Save -> Query -> Table -> Exec
    // Delete/Migrate 自身不再 use Table/Exec，而是依赖上面这条链补齐方法。
    // 若让 Table/Exec 经多条路径（Save 链 + Delete/Migrate）重复引入，
    // PHP 5.4 会报 trait 方法冲突（例如 getTable），产生 Fatal error。
    use Save;
    use Delete;
    use Migrate;

    static function table($table, $conn = null)
    {
        $db = new static();
        $db->table = $table;
        if ($conn !== null) {
            $db->instanceConn = is_string($conn) ? Conn::getInstance($conn) : $conn;
        }
        return $db;
    }

    static function model($model, $conn = null)
    {
        $db = new static();
        $db->model = $model;
        if ($conn !== null) {
            $db->instanceConn = is_string($conn) ? Conn::getInstance($conn) : $conn;
        }
        return $db;
    }

    function scope($scope)
    {
        $scope($this);
        return $this;
    }

    static function transaction($fun)
    {
        self::begin();
        try {
            $fun();
        } catch (\Exception $e) {
            self::rollback();
            throw $e;
        }
        self::commit();
    }
}