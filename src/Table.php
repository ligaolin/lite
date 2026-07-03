<?php

namespace Lin\Lite;

trait Table
{
    use Exec;
    protected $model;
    protected $table;
    protected $pkName = [];
    protected $fields = [];
    protected $fieldsAttr = [];

    function getTable()
    {
        if (!$this->table) {
            if (is_object($this->model)) {
                if (method_exists($this->model, 'getTable')) {
                    $this->table = $this->model->getTable();
                } else {
                    $this->table = Utils::underlineCase($this->model::class);
                }
            }
        }
        return $this->table;
    }

    function getPkName()
    {
        if (!$this->pkName) {
            if (method_exists($this->model, 'getPkName')) {
                $this->pkName = $this->model->getPkName();
            } else if ($table = $this->getTable(null)) {
                $sql = "SHOW KEYS FROM {$table} WHERE Key_name = 'PRIMARY'";
                $result = self::runQuery($sql);
                foreach ($result as $value) {
                    $this->pkName[] = $value['Column_name'];
                }
            }
        }
        return $this->pkName;
    }

    function getFieldsAttr()
    {
        if (!$this->fieldsAttr && $table = $this->getTable(null)) {
            $this->fieldsAttr = self::runQuery("SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,COLUMN_KEY,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_MAXIMUM_LENGTH,NUMERIC_PRECISION,NUMERIC_SCALE FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? 
                ORDER BY ORDINAL_POSITION", [$table]);
        }
        return $this->fieldsAttr;
    }

    function getFields()
    {
        if (!$this->fields) {
            if (method_exists($this->model, 'getFields')) {
                $this->fields = $this->model->getFields();
            } else if ($attr = $this->getFieldsAttr()) {
                $this->fields = array_column($attr, 'COLUMN_NAME');
            }
        }
        return $this->fields;
    }
}
