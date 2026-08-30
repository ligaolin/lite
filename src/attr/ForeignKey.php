<?php

namespace Lin\Lite\attr;

class ForeignKey
{
    public $table;
    public $column = 'id';
    public $onDelete = null;
    public $onUpdate = null;
    public $migrate = true;

    public function __construct($table, $column = 'id', $onDelete = null, $onUpdate = null, $migrate = true)
    {
        $this->table = $table;
        $this->column = $column;
        $this->onDelete = $onDelete;
        $this->onUpdate = $onUpdate;
        $this->migrate = $migrate;
    }
}