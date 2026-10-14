<?php

namespace Lin\Lite;

trait Delete
{
    use Table;

    function delete()
    {
        if ($this->model && method_exists($this->model, 'beforeDelete')) {
            $this->model->beforeDelete();
        }
        $arr = ['DELETE FROM'];
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