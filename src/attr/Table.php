<?php

namespace Lin\Lite\attr;

class Table
{
    public $name = null;
    public $engine = 'InnoDB';
    public $charset = 'utf8mb4';
    public $collation = 'utf8mb4_general_ci';
    public $comment = '';

    public function __construct($name = null, $engine = 'InnoDB', $charset = 'utf8mb4', $collation = 'utf8mb4_general_ci', $comment = '')
    {
        $this->name = $name;
        $this->engine = $engine;
        $this->charset = $charset;
        $this->collation = $collation;
        $this->comment = $comment;
    }
}