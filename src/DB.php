<?php

namespace Lin\Lite;

class DB
{
    use Query;
    use Save;
    use Delete;
    use Migrate;

    static function table($table)
    {
        $db = new static();
        $db->table = $table;
        return $db;
    }

    static function model($model)
    {
        $db = new static();
        $db->model = $model;
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