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
    use Table;
    use Exec;
    use Query;

    // 当$model包含主键时更新数据，否则添加数据
    function save(object|array|null $model)
    {
        if (!$this->model && is_object($model)) $this->model = $model;
        if (!$model) $model = $this->model;
        $update = true;
        $pkName = $this->getPkName();
        $where = [];
        $whereArgs = [];
        if (count($pkName)) {
            foreach ($pkName as $val) {
                if ((is_array($model) && (!isset($model[$val]) || $model[$val] === null || $model[$val] === '')) || (is_object($model) && (!isset($model->$val) || $model->$val === null || $model->$val === ''))) {
                    $update = false;
                } else {
                    $where[] = self::$symbol . $val . self::$symbol . ' = ?';
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

    function create(object|array|null $model)
    {
        if (!$this->model && is_object($model)) $this->model = $model;
        if (!$model) $model = $this->model;
        if ($this->model && method_exists($this->model, 'beforeCreate')) {
            $this->model->beforeCreate();
        }
        $arr = ['INSERT INTO ' . self::$symbol . $this->getTable() . self::$symbol];
        [$fields, $args] = $this->saveVal($model, true);
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

    function updates(object|array|null $model)
    {
        if (!$this->model && is_object($model)) $this->model = $model;
        if (!$model) $model = $this->model;
        if ($this->model && method_exists($this->model, 'beforeUpdate')) {
            $this->model->beforeUpdate();
        }
        $arr = ['UPDATE ' . self::$symbol . $this->getTable() . self::$symbol . ' SET'];

        [$fields, $args] = $this->saveVal($model, false);
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

    protected function saveVal(object|array $model, $isInsert = true)
    {
        $sqlFields = [];
        $args = [];
        $pkName = $this->getPkName();
        $ref = is_object($this->model) ? $this->model : (is_object($model) ? $model : null);
        foreach ($model as $key => $val) {
            $type = '';
            if ($ref) {
                if (!property_exists($ref, $key)) continue;
                if ($isInsert && DisableCreate::has($ref, $key)) continue;
                if (!$isInsert && DisableUpdate::has($ref, $key)) continue;
                if (BelongsTo::has($ref, $key) || HasOne::has($ref, $key) || HasMany::has($ref, $key)) continue;
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
            if ($isInsert) $sqlFields[] = self::$symbol . $key . self::$symbol;
            else $sqlFields[] = self::$symbol . $key . self::$symbol . ' = ?';
            $args[] = $this->castValue($val, $type);
        }
        return [$sqlFields, $args];
    }

    protected function castValue($val, $type)
    {
        if ($val === null || $val === '') {
            $intTypes = ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'bit'];
            $floatTypes = ['decimal', 'float', 'double', 'real', 'numeric'];
            $lowerType = strtolower($type);
            if (in_array($lowerType, $intTypes) || in_array($lowerType, $floatTypes)) {
                return null;
            }
        }
        return $val;
    }

    function update(string $field, $value)
    {
        $this->updates([$field => $value]);
    }

    // 检查数据唯一性
    // $msg 错误提示
    // $where 为检查条件
    // $whereArgs 为检查条件中的参数
    // 当使用模型实例化时，如果有主键，会自动通过主键排除自身数据
    function checkUnique($msg, $where, ...$whereArgs)
    {
        if ($where) {
            if ($pkName = $this->getPkName()) {
                foreach ($pkName as $val) {
                    if ((is_array($this->model) && isset($this->model[$val]) && $this->model[$val] !== null && $this->model[$val] !== '') || (is_object($this->model) && isset($this->model->$val) && $this->model->$val !== null && $this->model->$val !== '')) {
                        $where .= " AND " . self::$symbol . $val . self::$symbol . ' != ?';
                        $whereArgs[] = is_array($this->model) ? $this->model[$val] : $this->model->$val;
                    }
                }
            }
            $sql = "SELECT EXISTS (SELECT 1 FROM " . self::$symbol . $this->getTable() . self::$symbol . " WHERE {$where}) as count";
            $result = self::runQuery($sql, $whereArgs);
            if (!isset($result[0]['count']) || $result[0]['count']) {
                throw new \Exception($msg);
            }
        }
        return $this;
    }

    function copy(object|array|null $model)
    {
        if ($model) {
            if (is_object($model)) {
                foreach ($model as $key => $val) {
                    if (is_object($this->model) && property_exists($this->model, $key)) $this->model->$key = $val;
                    else if (is_array($this->model) && array_key_exists($key, $this->model)) $this->model[$key] = $val;
                }
            } else if (is_array($model)) {
                foreach ($model as $key => $val) {
                    if (is_array($this->model) && array_key_exists($key, $this->model)) $this->model[$key] = $val; 
                    else if (is_object($this->model) && property_exists($this->model, $key)) $this->model->$key = $val;
                }
            }
        }
        return $this;
    }
}