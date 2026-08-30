<?php

namespace Lin\Lite;

use Lin\Lite\attr\Json;
use Lin\Lite\attr\DisableRead;
use Lin\Lite\attr\BelongsTo;
use Lin\Lite\attr\HasOne;
use Lin\Lite\attr\HasMany;

trait Query
{
    use Table;

    public $field = array();
    public $join = array();
    public $joinArgs = array();
    public $where = array();
    public $whereArgs = array();
    public $limit = '';
    public $offset = '';
    public $order = array();
    public $group = '';
    public $having = '';
    public $distinct = '';
    public $preloads = array();

    function select()
    {
        $this->field = func_get_args();
        return $this;
    }

    function where($sql)
    {
        $val = func_get_args();
        array_shift($val);
        list($sql, $bound) = $this->expandIn($sql, $val);
        $this->whereArgs = array_merge($this->whereArgs, $bound);
        $this->where[] = $sql;
        return $this;
    }

    function whereNotEmpty($sql, $val)
    {
        if ($val) {
            list($sql, $bound) = $this->expandIn($sql, array($val));
            $this->whereArgs = array_merge($this->whereArgs, $bound);
            $this->where[] = $sql;
        }
        return $this;
    }

    // 按占位符顺序展开数组参数为多个 ?（IN 查询）
    protected function expandIn($sql, $args)
    {
        $out = '';
        $bound = array();
        $argIndex = 0;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            if ($sql[$i] === '?') {
                if ($argIndex < count($args) && is_array($args[$argIndex])) {
                    $out .= join(',', array_fill(0, count($args[$argIndex]), '?'));
                    foreach ($args[$argIndex] as $item) {
                        $bound[] = $item;
                    }
                } else {
                    $out .= '?';
                    if ($argIndex < count($args)) {
                        $bound[] = $args[$argIndex];
                    }
                }
                $argIndex++;
            } else {
                $out .= $sql[$i];
            }
        }
        for ($i = $argIndex; $i < count($args); $i++) {
            if (is_array($args[$i])) {
                foreach ($args[$i] as $item) {
                    $bound[] = $item;
                }
            } else {
                $bound[] = $args[$i];
            }
        }
        return array($out, $bound);
    }

    function like($sql, $val, $like = '')
    {
        if ($val) {
            if (!Utils::strContains(strtoupper($sql), strtoupper('like'))) $sql .= ' LIKE ?';
            $this->where[] = $sql;
            if (!$like) $like = '%' . $val . '%';
            $this->whereArgs[] = $like;
        }
        return $this;
    }

    function order($order)
    {
        $this->order[] = $order;
        return $this;
    }

    function join($sql)
    {
        $val = func_get_args();
        array_shift($val);
        $this->join[] = $sql;
        $this->joinArgs = array_merge($this->joinArgs, $val);
        return $this;
    }

    function group($group)
    {
        $this->group = $group;
        return $this;
    }

    function having($having)
    {
        $this->having = $having;
        return $this;
    }

    function distinct($distinct)
    {
        $this->distinct = $distinct;
        return $this;
    }

    function limit($limit, $max_limit = 1000)
    {
        $this->limit = min($limit, $max_limit);
        return $this;
    }

    function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    function page($page = 1, $page_size = 10, $max_page_size = 100)
    {
        $page_size = max(1, min($max_page_size, $page_size));
        $this->limit = $page_size;
        if ($page > 0) {
            $this->offset = ($page - 1) * $page_size;
        }
        return $this;
    }

    protected function toArgs()
    {
        return array_merge($this->joinArgs, $this->whereArgs);
    }

    function value($field, &$value)
    {
        $arr = array('SELECT');
        $arr[] = $field;
        $arr[] = 'FROM';
        $arr[] = self::$symbol . $this->getTable() . self::$symbol;
        $arr = array_merge($arr, $this->join);

        $where = join(' AND ', $this->where);
        if ($where) $where = 'WHERE ' . $where;
        $arr[] = $where;

        if ($this->group) $arr[] = 'GROUP BY ' . $this->group;
        if ($this->having) $arr[] = 'HAVING ' . $this->having;

        $order = join(', ', $this->order);
        if ($order) $arr[] = 'ORDER BY ' . $order;

        $arr[] = 'LIMIT 1';

        $sql = join(' ', $arr);
        $args = $this->toArgs();
        $result = self::runQuery($sql, $args);
        $value = null;
        if (isset($result[0][$field])) {
            $value = $result[0][$field];
        }
        return $this;
    }

    function values($fields, &$data)
    {
        $arr = array('SELECT');
        $arr[] = join(',', $fields);
        $arr[] = 'FROM';
        $arr[] = self::$symbol . $this->getTable() . self::$symbol;
        $arr = array_merge($arr, $this->join);

        $where = join(' AND ', $this->where);
        if ($where) $where = 'WHERE ' . $where;
        $arr[] = $where;

        if ($this->group) $arr[] = 'GROUP BY ' . $this->group;
        if ($this->having) $arr[] = 'HAVING ' . $this->having;

        $order = join(', ', $this->order);
        if ($order) $arr[] = 'ORDER BY ' . $order;

        if ($this->limit) $arr[] = 'LIMIT ' . $this->limit;
        if ($this->offset) $arr[] = 'OFFSET ' . $this->offset;

        $sql = join(' ', $arr);
        $args = $this->toArgs();
        $result = self::runQuery($sql, $args);
        $data = array();
        foreach ($result as $row) {
            $item = array();
            foreach ($fields as $f) {
                $item[$f] = isset($row[$f]) ? $row[$f] : null;
            }
            $data[] = $item;
        }
        return $this;
    }

    function count(&$total)
    {
        $arr = array('SELECT');
        if ($this->distinct) $arr[] = 'DISTINCT';
        $arr[] = 'COUNT(*)';
        $arr[] = 'FROM';
        $arr[] = self::$symbol . $this->getTable() . self::$symbol;
        $arr = array_merge($arr, $this->join);

        $where = join(' AND ', $this->where);
        if ($where) $where = 'WHERE ' . $where;
        $arr[] = $where;

        if ($this->group) $arr[] = 'GROUP BY ' . $this->group;
        if ($this->having) $arr[] = 'HAVING ' . $this->having;

        $sql = join(' ', $arr);
        $args = $this->toArgs();
        $result = self::runQuery($sql, $args);
        $total = 0;
        if (isset($result[0]['COUNT(*)'])) {
            $total = $result[0]['COUNT(*)'];
        }
        return $this;
    }

    function first(&$data)
    {
        if (!$this->model && is_object($data)) $this->model = $data;
        if ($this->model && method_exists($this->model, 'beforeFind')) {
            $this->model->beforeFind();
        }
        $arr = array('SELECT');
        if ($this->distinct) $arr[] = 'DISTINCT';
        if (!count($this->field)) $this->field = array('*');
        $arr = array_merge($arr, $this->field);
        $arr[] = 'FROM';
        $arr[] = self::$symbol . $this->getTable() . self::$symbol;
        $arr = array_merge($arr, $this->join);

        $where = join(' AND ', $this->where);
        if ($where) $arr[] = 'WHERE ' . $where;

        if ($this->group) $arr[] = 'GROUP BY ' . $this->group;
        if ($this->having) $arr[] = 'HAVING ' . $this->having;

        $order = join(', ', $this->order);
        if ($order) $arr[] = 'ORDER BY ' . $order;

        $arr[] = 'LIMIT 1';

        $sql = join(' ', $arr);
        $args = $this->toArgs();

        $result = self::runQuery($sql, $args);

        if (isset($result[0])) {
            if ($this->model) {
                $modelClass = get_class($this->model);
                $data = new $modelClass();
                $this->setVals($data, $result[0]);
            } else {
                $data = $result[0];
            }
        } else {
            $data = null;
        }
        $this->preloadMatch($data);
        return $this;
    }

    function find(&$data)
    {
        if (!$this->model && isset($data[0]) && is_object($data[0])) $this->model = $data[0];
        if ($this->model && method_exists($this->model, 'beforeFind')) {
            $this->model->beforeFind();
        }
        $arr = array('SELECT');
        if ($this->distinct) $arr[] = 'DISTINCT';
        if (!count($this->field)) $this->field = array('*');
        $arr = array_merge($arr, $this->field);
        $arr[] = 'FROM';
        $arr[] = self::$symbol . $this->getTable() . self::$symbol;
        $arr = array_merge($arr, $this->join);

        $where = join(' AND ', $this->where);
        if ($where) $arr[] = 'WHERE ' . $where;

        if ($this->group) $arr[] = 'GROUP BY ' . $this->group;
        if ($this->having) $arr[] = 'HAVING ' . $this->having;

        $order = join(', ', $this->order);
        if ($order) $arr[] = 'ORDER BY ' . $order;

        if ($this->limit) $arr[] = 'LIMIT ' . $this->limit;
        if ($this->offset) $arr[] = 'OFFSET ' . $this->offset;

        $sql = join(' ', $arr);
        $args = $this->toArgs();
        $result = self::runQuery($sql, $args);
        if ($this->model) {
            $data = array();
            $modelClass = get_class($this->model);
            foreach ($result as $item) {
                $m = new $modelClass();
                $this->setVals($m, $item);
                $data[] = $m;
            }
            $this->preloadMatch($data);
        } else {
            $data = $result;
        }
        return $this;
    }

    protected function setVals(&$model, $data)
    {
        foreach ($model as $k => &$item) {
            if (DisableRead::has($model, $k)) continue;
            if (BelongsTo::has($model, $k) || HasOne::has($model, $k) || HasMany::has($model, $k)) continue;
            if (array_key_exists($k, $data)) {
                $item = $data[$k];
                if ($item !== null && Json::has($model, $k)) {
                    $item = json_decode($item, true);
                }
            }
        }
        if (method_exists($model, 'afterFind')) {
            $model->afterFind();
        }
    }

    function exists(&$exist)
    {
        $arr = array('SELECT 1 FROM');
        $arr[] = self::$symbol . $this->getTable() . self::$symbol;
        $arr = array_merge($arr, $this->join);

        $where = join(' AND ', $this->where);
        if ($where) $where = 'WHERE ' . $where;
        $arr[] = $where;

        if ($this->group) $arr[] = 'GROUP BY ' . $this->group;
        if ($this->having) $arr[] = 'HAVING ' . $this->having;

        $sql = join(' ', $arr);
        $sql = "SELECT EXISTS ({$sql}) as count";
        $args = $this->toArgs();
        $result = self::runQuery($sql, $args);
        if (isset($result[0]['count']) && $result[0]['count']) {
            $exist = true;
        } else {
            $exist = false;
        }
        return $this;
    }

    function preload($model, $fun = null)
    {
        $ref = new \ReflectionClass($this->model);
        $relatedClass = get_class($model);
        $pk = $this->getPkName();
        $referencesKey = isset($pk[0]) ? $pk[0] : '';
        $foreignKey = Utils::underlineCase(get_class($this->model)) . '_id';

        $first = false;
        $propName = '';
        foreach ($ref->getProperties() as $prop) {
            $belongsTo = $this->getPropAttr($prop, __NAMESPACE__ . '\attr\BelongsTo');
            if ($belongsTo && $belongsTo->model === $relatedClass) {
                $first = true;
                $propName = $prop->getName();
                $relatedTable = Utils::underlineCase($relatedClass);
                $referencesKey = $this->findForeignKey(get_class($this->model), $relatedTable)
                    ?: (Utils::strEndsWith($propName, '_id') ? $propName : $propName . '_id');
                $foreignKey = 'id';
                foreach ($ref->getProperties() as $p) {
                    if ($p->getName() === $referencesKey) {
                        $fkAttr = $this->getPropAttr($p, __NAMESPACE__ . '\attr\ForeignKey');
                        if ($fkAttr) {
                            $foreignKey = $fkAttr->column;
                        }
                        break;
                    }
                }
                break;
            }
            $hasOne = $this->getPropAttr($prop, __NAMESPACE__ . '\attr\HasOne');
            if ($hasOne && $hasOne->model === $relatedClass) {
                $first = true;
                $propName = $prop->getName();
                $foreignKey = $this->findForeignKey($relatedClass, $this->getTable()) ?: $foreignKey;
                break;
            }
            $hasMany = $this->getPropAttr($prop, __NAMESPACE__ . '\attr\HasMany');
            if ($hasMany && $hasMany->model === $relatedClass) {
                $first = false;
                $propName = $prop->getName();
                $foreignKey = $this->findForeignKey($relatedClass, $this->getTable()) ?: $foreignKey;
                break;
            }
        }

        $m = DB::Model($model);
        if ($fun) $fun($m);
        if ($this->debug) $m = $m->debug();
        $this->preloads[] = array(
            'db' => $m,
            'first' => $first,
            'foreignKey' => $foreignKey,
            'referencesKey' => $referencesKey,
            'propName' => $propName,
        );
        return $this;
    }

    private function findForeignKey($relatedClass, $currentTable)
    {
        $ref = new \ReflectionClass($relatedClass);
        foreach ($ref->getProperties() as $prop) {
            $fkAttr = $this->getPropAttr($prop, __NAMESPACE__ . '\attr\ForeignKey');
            if ($fkAttr && $fkAttr->table === $currentTable) {
                return $prop->getName();
            }
        }
        return null;
    }

    protected function preloadMatch(&$data)
    {
        if (empty($this->preloads)) return;
        if (empty($data)) return;
        foreach ($this->preloads as $entry) {
            $m = $entry['db'];
            $first = $entry['first'];
            $foreignKey = $entry['foreignKey'];
            $referencesKey = $entry['referencesKey'];

            if (is_array($data)) {
                if (empty($data)) {
                    continue;
                }
                $references = array();
                foreach ($data as $item) {
                    $references[] = $item->$referencesKey;
                }
                $m->where($foreignKey . ' IN (?)', $references);
            } else {
                $m->where($foreignKey . ' = ?', $data->$referencesKey);
            }
            if (is_array($data)) {
                $preloadData = array();
                $m->Find($preloadData);
            } elseif ($first) {
                $preloadData = null;
                $m->First($preloadData);
                $preloadData = $preloadData ? array($preloadData) : array();
            } else {
                $preloadData = array();
                $m->Find($preloadData);
            }
            $name = $entry['propName'] ?: (!empty($preloadData) ? Utils::underlineCase(get_class($preloadData[0])) : '');
            if (is_array($data)) {
                foreach ($data as &$item) {
                    if ($first) {
                        foreach ($preloadData as $pd) {
                            if ($pd->$foreignKey == $item->$referencesKey) {
                                $item->$name = $pd;
                                break;
                            }
                        }
                    } else {
                        $arr = array();
                        foreach ($preloadData as $pd) {
                            if ($pd->$foreignKey == $item->$referencesKey) {
                                $arr[] = $pd;
                            }
                        }
                        if (count($arr)) $item->$name = $arr;
                    }
                }
            } else {
                if ($first) {
                    $data->$name = isset($preloadData[0]) ? $preloadData[0] : null;
                } else {
                    $data->$name = $preloadData;
                }
            }
        }
    }
}