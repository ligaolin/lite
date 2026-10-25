<?php

namespace Lin\Lite;

use Lin\Lite\attr\BelongsTo;
use Lin\Lite\attr\DefaultValue;
use Lin\Lite\attr\HasMany;
use Lin\Lite\attr\HasOne;
use Lin\Lite\attr\Json;
use Lin\Lite\attr\CreatedAt;
use Lin\Lite\attr\UpdatedAt;
use Lin\Lite\attr\DisableCreate;
use Lin\Lite\attr\DisableUpdate;
use Lin\Lite\attr\Type;

trait Save
{
    use Query; // 隐式包含 Table、Exec

    function save($model = null)
    {
        if (!$this->model && is_object($model)) $this->model = $model;
        if (!$model) $model = $this->model;
        $update = true;
        $pkName = $this->getPkName();
        $where = array();
        $whereArgs = array();
        if (count($pkName)) {
            foreach ($pkName as $val) {
                if ((is_array($model) && (!isset($model[$val]) || !$model[$val])) || (is_object($model) && (!isset($model->$val) || !$model->$val))) {
                    $update = false;
                } else {
                    $where[] = $this->getQuoteSymbol() . $val . $this->getQuoteSymbol() . ' = ?';
                    $whereArgs[] = is_array($model) ? $model[$val] : $model->$val;
                }
            }
        } else {
            $update = false;
        }
        if ($this->model && method_exists($this->model, 'beforeSave')) {
            $this->model->beforeSave();
        }
        if ($update) {
            $this->where = array_merge($this->where, $where);
            $this->whereArgs = array_merge($this->whereArgs, $whereArgs);
            $this->updates($model);
        } else {
            $this->create($model);
        }
        if ($this->model && method_exists($this->model, 'afterSave')) {
            $this->model->afterSave();
        }
        return $this;
    }

    function create($model = null)
    {
        if (!$this->model && is_object($model)) $this->model = $model;
        if (!$model) $model = $this->model;
        if ($this->model && method_exists($this->model, 'beforeCreate')) {
            $this->model->beforeCreate();
        }
        $arr = array('INSERT INTO ' . $this->getQuoteSymbol() . $this->getTable() . $this->getQuoteSymbol());
        list($fields, $args) = $this->saveVal($model, true);
        if (count($fields)) {
            $arr[] = '(' . join(',', $fields) . ')';
            $arr[] = 'VALUES';
            $arr[] = '(' . implode(', ', array_fill(0, count($fields), '?')) . ')';
        }
        $sql = join(' ', $arr);
        self::runExec($sql, $args);
        $insertId = self::lastInsertId();
        $pkName = $this->getPkName();
        if ($insertId && count($pkName) === 1) {
            $pk = $pkName[0];
            if (is_array($model)) {
                $model[$pk] = $insertId;
            } else {
                $model->$pk = $insertId;
            }
        }
        if ($this->model && method_exists($this->model, 'afterCreate')) {
            $this->model->afterCreate();
        }
        return $this;
    }

    function updates($model = null)
    {
        if (!$this->model && is_object($model)) $this->model = $model;
        if (!$model) $model = $this->model;
        if ($this->model && method_exists($this->model, 'beforeUpdate')) {
            $this->model->beforeUpdate();
        }
        $arr = array('UPDATE ' . $this->getQuoteSymbol() . $this->getTable() . $this->getQuoteSymbol() . ' SET');

        list($fields, $args) = $this->saveVal($model, false);
        if (count($fields)) {
            $arr[] = join(', ', $fields);
        }

        $where = join(' AND ', $this->where);
        if ($where) $arr[] = 'WHERE ' . $where;
        $args = array_merge($args, $this->whereArgs);

        $sql = join(' ', $arr);
        self::runExec($sql, $args);
        if ($this->model && method_exists($this->model, 'afterUpdate')) {
            $this->model->afterUpdate();
        }
        return $this;
    }

    protected function saveVal($model, $isInsert = true)
    {
        $sqlFields = array();
        $args = array();
        $pkName = $this->getPkName();
        $ref = is_object($this->model) ? $this->model : (is_object($model) ? $model : null);
        $iterate = $isInsert ? ($ref ?: $model) : $model;
        foreach ($iterate as $key => $val) {
            $type = '';
            if ($ref) {
                if (!property_exists($ref, $key)) continue;
                if ($isInsert && DisableCreate::has($ref, $key)) continue;
                if (!$isInsert && DisableUpdate::has($ref, $key)) continue;
                if (!$isInsert && CreatedAt::has($ref, $key) && !UpdatedAt::has($ref, $key)) continue;
                if (BelongsTo::has($ref, $key) || HasOne::has($ref, $key) || HasMany::has($ref, $key)) continue;
                if (is_array($model)) {
                    $val = array_key_exists($key, $model) ? $model[$key] : $val;
                } elseif ($model !== $ref) {
                    $val = isset($model->$key) ? $model->$key : $val;
                }
                if ($val === null) {
                    $val = DefaultValue::get($ref, $key);
                }
                if ($isInsert && CreatedAt::has($ref, $key) && !$val) {
                    $val = CreatedAt::now();
                }
                if (UpdatedAt::has($ref, $key)) {
                    $val = UpdatedAt::now();
                }
                if ($val !== null && Json::has($ref, $key)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                }
                $type = Type::get($ref, $key);
            }
            if (in_array($key, $pkName) && !$val) continue;
            if ($isInsert) $sqlFields[] = $this->getQuoteSymbol() . $key . $this->getQuoteSymbol();
            else $sqlFields[] = $this->getQuoteSymbol() . $key . $this->getQuoteSymbol() . ' = ?';
            $args[] = $this->castValue($val, $type);
        }
        return array($sqlFields, $args);
    }

    protected function castValue($val, $type)
    {
        if ($val === null || $val === '') {
            $intTypes = array('int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'bit');
            $floatTypes = array('decimal', 'float', 'double', 'real', 'numeric');
            $lowerType = strtolower($type);
            if (in_array($lowerType, $intTypes) || in_array($lowerType, $floatTypes)) {
                return null;
            }
        }
        return $val;
    }

    function update($field, $value)
    {
        $this->updates(array($field => $value));
    }

    function checkUnique($msg, $where)
    {
        $whereArgs = func_get_args();
        array_shift($whereArgs);
        array_shift($whereArgs);
        if ($where) {
            if ($pkName = $this->getPkName()) {
                foreach ($pkName as $val) {
                    if ((is_array($this->model) && isset($this->model[$val]) && $this->model[$val]) || (is_object($this->model) && isset($this->model->$val) && $this->model->$val)) {
                        $where .= " AND " . $this->getQuoteSymbol() . $val . $this->getQuoteSymbol() . ' != ?';
                        $whereArgs[] = is_array($this->model) ? $this->model[$val] : $this->model->$val;
                    }
                }
            }
            $sql = "SELECT EXISTS (SELECT 1 FROM " . $this->getQuoteSymbol() . $this->getTable() . $this->getQuoteSymbol() . " WHERE {$where}) as count";
            $result = self::runQuery($sql, $whereArgs);
            if (!isset($result[0]['count']) || $result[0]['count']) {
                throw new \Exception($msg);
            }
        }
        return $this;
    }

    function copy($model)
    {
        if ($model) {
            if (is_object($model)) {
                foreach ($model as $key => $val) {
                    if (is_object($this->model) && property_exists($this->model, $key)) {
                        $this->model->$key = $val;
                    } else if (is_array($this->model) && array_key_exists($key, $this->model)) {
                        $this->model[$key] = $val;
                    }
                }
            } else if (is_array($model)) {
                foreach ($model as $key => $val) {
                    if (is_array($this->model) && array_key_exists($key, $this->model)) {
                        $this->model[$key] = $val;
                    } else if (is_object($this->model) && property_exists($this->model, $key)) {
                        $this->model->$key = $val;
                    }
                }
            }
        }
        return $this;
    }
}