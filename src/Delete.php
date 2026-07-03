<?php

namespace Lin\Lite;

trait Delete
{
    use Table;

    // 删除数据
    function Delete()
    {
        if (method_exists($this->model, 'beforeDelete')) {
            $this->model->beforeDelete();
        }
        $arr = ['DELETE FROM'];
        $arr[] = self::$symbol . $this->getTable() . self::$symbol;
        $arr = array_merge($arr, $this->join);

        $where = join(' AND ', $this->where);
        if ($where) $where = 'WHERE ' . $where;
        $arr[] = $where;

        $sql = join(' ', $arr);
        $args = $this->toArgs();
        self::runQuery($sql, $args);

        if (method_exists($this->model, 'afterDelete')) {
            $this->model->afterDelete();
        }
        return $this;
    }
}
