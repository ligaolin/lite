<?php

namespace Lin\Lite;

// 方法分类：创建实例方法、过程方法、终结方法
// 创建实例方法: table()、model()，请通过创建实例方法开始操作
// 终结方法：count、first、find、exists、delete、save、create、update、updates、checkUnique,当有调用debug()方法时，执行终结方法会打印sql语句
// 其他属于过程方法，其中debug方法可以在终结方法执行时打印sql语句，方便调试

class DB
{
    use Query;
    use Save;
    use Delete;
    use Migrate;

    // 基于完整表名创建实例
    static function table(string $table)
    {
        $db = new static();
        $db->table = $table;
        return $db;
    }

    // 基于模型创建实例，推荐
    static function model(object $model)
    {
        $db = new static();
        $db->model = $model;
        return $db;
    }

    // 复用通用逻辑
    function scope(callable $scope)
    {
        $scope($this);
        return $this;
    }

    // 事务操作
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