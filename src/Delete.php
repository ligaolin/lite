<?php

namespace Lin\Lite;

trait Delete
{
    // 依赖 DB 组合 Save（Save -> Query -> Table -> Exec）间接提供
    // getTable()/runExec()/toArgs()/$symbol 等成员。
    // 切勿在此 use Table/Query/Exec：PHP 5.4 会把“同一 trait 经多条路径引入”
    // 判定为方法冲突（如 getTable），导致 Fatal error。
    function delete()
    {
        if ($this->model && method_exists($this->model, 'beforeDelete')) {
            $this->model->beforeDelete();
        }
        $arr = array('DELETE FROM');
        $arr[] = $this->getQuoteSymbol() . $this->getTable() . $this->getQuoteSymbol();
        $arr = array_merge($arr, $this->join);

        $where = join(' AND ', $this->where);
        if ($where) $where = 'WHERE ' . $where;
        $arr[] = $where;

        $sql = join(' ', $arr);
        $args = $this->toArgs();
        self::runExec($sql, $args);

        if ($this->model && method_exists($this->model, 'afterDelete')) {
            $this->model->afterDelete();
        }
        return $this;
    }
}