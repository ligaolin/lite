<?php

namespace Lin\Lite;

use Lin\Lite\attr\PrimaryKey;
use Lin\Lite\attr\Table as TableAttr;

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
                $ref = new \ReflectionClass($this->model);
                $attrs = $ref->getAttributes(TableAttr::class);
                if (!empty($attrs)) {
                    $tableAttr = $attrs[0]->newInstance();
                    if ($tableAttr->name) {
                        $this->table = $tableAttr->name;
                    }
                }
            
                if (!$this->table) {
                    $this->table = Utils::underlineCase($this->model::class);
                }
            }
        }
        return $this->table;
    }

    function getPkName()
    {
        if (!$this->pkName) {
            if (is_object($this->model)) {
                $ref = new \ReflectionClass($this->model);
                foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                    if (!empty($prop->getAttributes(PrimaryKey::class))) {
                        $this->pkName[] = $prop->getName();
                    }
                }
            }
        
            if (!$this->pkName && ($table = $this->getTable())) {
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