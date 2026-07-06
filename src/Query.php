<?php

namespace Lin\Lite;

use Lin\Lite\attr\Json;
use Lin\Lite\attr\DisableRead;
use Lin\Lite\attr\BelongsTo;
use Lin\Lite\attr\ForeignKey;
use Lin\Lite\attr\HasOne;
use Lin\Lite\attr\HasMany;

trait Query
{
    use Exec; // 执行sql语句
    use Table; // 表基础信息

    public $field = [];
    public $join = [];
    public $joinArgs = [];
    public $where = [];
    public $whereArgs = [];
    public $limit = '';
    public $offset = '';
    public $order = [];
    public $group = '';
    public $having = '';
    public $distinct = '';
    public $preloads = [];

    function select(string ...$field)
    {
        $this->field = $field;
        return $this;
    }

    function where(string $sql, ...$val)
    {
        foreach ($val as &$v) {
            if (is_array($v)) {
                $pos = strpos($sql, '?');
                if ($pos !== false) {
                    $placeholders = join(',', array_fill(0, count($v), '?'));
                    $sql = substr($sql, 0, $pos) . $placeholders . substr($sql, $pos + 1);
                }
                $this->whereArgs = array_merge($this->whereArgs, $v);
            } else {
                $this->whereArgs[] = $v;
            }
        }
        $this->where[] = $sql;
        return $this;
    }

    // 当查询字段值不为空时，才添加where条件句
    function whereNotEmpty(string $sql, $val)
    {
        if ($val) {
            if (is_array($val)) {
                $pos = strpos($sql, '?');
                if ($pos !== false) {
                    $placeholders = join(',', array_fill(0, count($val), '?'));
                    $sql = substr($sql, 0, $pos) . $placeholders . substr($sql, $pos + 1);
                }
                $this->whereArgs = array_merge($this->whereArgs, $val);
            } else {
                $this->whereArgs[] = $val;
            }
            $this->where[] = $sql;
        }
        return $this;
    }

    // 当查询字段值不为空时，才添加like条件句
    function like(string $sql, $val, string $like = '')
    {
        if ($val) {
            if (!str_contains(strtoupper($sql), strtoupper('like'))) $sql .= ' LIKE ?';
            $this->where[] = $sql;
            if (!$like) $like = '%' . $val . '%';
            $this->whereArgs[] = $like;
        }
        return $this;
    }


    function order(string $order)
    {
        $this->order[] = $order;
        return $this;
    }

    function join(string $sql, ...$val)
    {
        $this->join[] = $sql;
        $this->joinArgs = array_merge($this->joinArgs, $val);
        return $this;
    }

    function group(string $group)
    {
        $this->group = $group;
        return $this;
    }

    function having(string $having)
    {
        $this->having = $having;
        return $this;
    }

    function distinct(string $distinct)
    {
        $this->distinct = $distinct;
        return $this;
    }

    function limit(int $limit, int $max_limit = 1000)
    {
        $this->limit = min($limit, $max_limit);
        return $this;
    }

    function offset(int $offset)
    {
        $this->offset = $offset;
        return $this;
    }

    // 分页查询
    // $page 当前页
    // $page_size 每页条数
    // $max_page_size 最大每页条数，查询数据时不能大于这个数量
    function page(int $page = 1, int $page_size = 10, int $max_page_size = 100)
    {
        $page_size = max(1, min($max_page_size, $page_size));
        $this->limit = $page_size;
        if ($page > 0) { // 当前页大于0，才分页查询
            $this->offset = ($page - 1) * $page_size;
        }
        return $this;
    }

    protected function toArgs(): array
    {
        return array_merge($this->joinArgs, $this->whereArgs);
    }

    // 查询单个字段值
    function value(string $field, &$value)
    {
        $arr = ['SELECT'];
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

    // 查询多个字段值，返回二维数组
    function values(array $fields, array &$data)
    {
        $arr = ['SELECT'];
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
        $data = [];
        foreach ($result as $row) {
            $item = [];
            foreach ($fields as $f) {
                $item[$f] = $row[$f] ?? null;
            }
            $data[] = $item;
        }
        return $this;
    }

    // 查询数据条数
    function count(&$total)
    {
        $arr = ['SELECT'];
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

    // 查询第一条数据
    function first(object|array|null &$data)
    {
        if (!$this->model && is_object($data)) $this->model = $data;
        if (method_exists($this->model, 'beforeFind')) {
            $this->model->beforeFind();
        }
        $arr = ['SELECT'];
        if ($this->distinct) $arr[] = 'DISTINCT';
        if (!count($this->field)) $this->field = ['*'];
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
            $data = new ($this->model::class);
            $this->setVals($data, $result[0]);
        } else {
            $data = $this->model;
        }
        $this->preloadMatch($data);
        return $this;
    }

    // 查询多个数据
    function find(array|null &$data)
    {
        if (!$this->model && isset($data[0]) && is_object($data[0])) $this->model = $data[0];
        if (method_exists($this->model, 'beforeFind')) {
            $this->model->beforeFind();
        }
        $arr = ['SELECT'];
        if ($this->distinct) $arr[] = 'DISTINCT';
        if (!count($this->field)) $this->field = ['*'];
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

        // 分页
        if ($this->limit) $arr[] = 'LIMIT ' . $this->limit;
        if ($this->offset) $arr[] = 'OFFSET ' . $this->offset;

        $sql = join(' ', $arr);
        $args = $this->toArgs();
        $result = self::runQuery($sql, $args);
        $data = [];
        if ($this->model) {
            foreach ($result as $item) {
                $m = new ($this->model::class);
                $this->setVals($m, $item);
                $data[] = $m;
            }
            $this->preloadMatch($data);
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

    // 检查数据是否存在
    function exists(&$exist)
    {
        $arr = ['SELECT 1 FROM'];
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
            $exist =  true;
        } else {
            $exist = false;
        }
        return $this;
    }

    function preload(object $model, $fun = null)
    {
        $ref = new \ReflectionClass($this->model);
        $relatedClass = $model::class;
        $pk = $this->getPkName();
        $referencesKey = $pk[0] ?? '';
        $foreignKey = Utils::underlineCase($this->model::class) . '_id';

        $first = false;
        foreach ($ref->getProperties() as $prop) {
            $belongsTo = $this->getPropAttr($prop, BelongsTo::class);
            if ($belongsTo && $belongsTo->model === $relatedClass) {
                $first = true;
                $propName = $prop->getName();
                $relatedTable = Utils::underlineCase($relatedClass);
                $referencesKey = $this->findForeignKey($this->model::class, $relatedTable)
                    ?: (str_ends_with($propName, '_id') ? $propName : $propName . '_id');
                $foreignKey = 'id';
                foreach ($ref->getProperties() as $p) {
                    if ($p->getName() === $referencesKey) {
                        $fkAttr = $this->getPropAttr($p, ForeignKey::class);
                        if ($fkAttr) {
                            $foreignKey = $fkAttr->column;
                        }
                        break;
                    }
                }
                break;
            }
            $hasOne = $this->getPropAttr($prop, HasOne::class);
            if ($hasOne && $hasOne->model === $relatedClass) {
                $first = true;
                $foreignKey = $this->findForeignKey($relatedClass, $this->getTable()) ?: $foreignKey;
                break;
            }
            $hasMany = $this->getPropAttr($prop, HasMany::class);
            if ($hasMany && $hasMany->model === $relatedClass) {
                $first = false;
                $foreignKey = $this->findForeignKey($relatedClass, $this->getTable()) ?: $foreignKey;
                break;
            }
        }

        $m = DB::Model($model);
        if ($fun) $fun($m);
        if ($this->debug) $m = $m->debug();
        $this->preloads[] = [
            'db' => $m,
            'first' => $first,
            'foreignKey' => $foreignKey,
            'referencesKey' => $referencesKey,
        ];
        return $this;
    }

    private function getPropAttr(\ReflectionProperty $prop, string $attrClass): ?object
    {
        $attrs = $prop->getAttributes($attrClass);
        if (!empty($attrs)) {
            return $attrs[0]->newInstance();
        }
        return null;
    }

    private function findForeignKey(string $relatedClass, string $currentTable): ?string
    {
        $ref = new \ReflectionClass($relatedClass);
        foreach ($ref->getProperties() as $prop) {
            $fkAttr = $this->getPropAttr($prop, ForeignKey::class);
            if ($fkAttr && $fkAttr->table === $currentTable) {
                return $prop->getName();
            }
        }
        return null;
    }

    protected function preloadMatch(&$data)
    {
        if (empty($this->preloads)) return;
        foreach ($this->preloads as $entry) {
            $m = $entry['db'];
            $first = $entry['first'];
            $foreignKey = $entry['foreignKey'];
            $referencesKey = $entry['referencesKey'];

            if (is_array($data)) {
                if (empty($data)) {
                    continue;
                }
                $references = [];
                foreach ($data as $item) {
                    $references[] = $item->$referencesKey;
                }
                $m->where($foreignKey . ' IN (?)', $references);
            } else {
                $m->where($foreignKey . ' = ?', $data->$referencesKey);
            }
            if (is_array($data)) {
                $preloadData = [];
                $m->Find($preloadData);
            } elseif ($first) {
                $preloadData = null;
                $m->First($preloadData);
                $preloadData = $preloadData ? [$preloadData] : [];
            } else {
                $preloadData = [];
                $m->Find($preloadData);
            }
            $name = '';
            if (is_array($data)) {
                foreach ($data as &$item) {
                    if ($first) {
                        foreach ($preloadData as $pd) {
                            if (!$name) $name = Utils::underlineCase($pd::class);
                            if ($pd->$foreignKey == $item->$referencesKey) {
                                $item->$name = $pd;
                                break;
                            }
                        }
                    } else {
                        $arr = [];
                        foreach ($preloadData as $pd) {
                            if (!$name) $name = Utils::underlineCase($pd::class);
                            if ($pd->$foreignKey == $item->$referencesKey) {
                                $arr[] = $pd;
                            }
                        }
                        if (count($arr)) $item->$name = $arr;
                    }
                }
            } else {
                if ($first) {
                    foreach ($preloadData as $pd) {
                        if (!$name) $name = Utils::underlineCase($pd::class);
                        if ($pd->$foreignKey == $data->$referencesKey) {
                            $data->$name = $pd;
                            break;
                        }
                    }
                } else {
                    $arr = [];
                    foreach ($preloadData as $pd) {
                        if (!$name) $name = Utils::underlineCase($pd::class);
                        if ($pd->$foreignKey == $data->$referencesKey) {
                            $arr[] = $pd;
                        }
                    }
                    if (count($arr)) $data->$name = $arr;
                }
            }
        }
    }
}