# Lanke PHP ORM 文档

ORM，目前仅实现了MySQL部分，通过注解定义模型，支持查询、增删改、关联预加载、自动建表迁移。

## 目录

- [注解属性](#注解属性)
- [创建实例](#创建实例)
- [查询](#查询)
- [插入更新](#插入更新)
- [删除](#删除)
- [关联预加载](#关联预加载)
- [表迁移](#表迁移)
- [模型表信息](#模型表信息)
- [原生 SQL](#原生-sql)
- [自定义数据库连接](#自定义数据库连接)
- [事务](#事务)
- [Scope 复用](#scope-复用)
- [调试](#调试)
- [回调钩子](#回调钩子)

---

## 注解属性

所有注解位于 `Lin\Lite\attr` 命名空间。

### 表注解（类级别）

| 注解 | 作用 | 参数 |
|------|------|------|
| `#[Table]` | 定义表信息 | `name`(表名), `engine`, `charset`, `collation`, `comment` |

### 列注解（属性级别）

| 注解 | 作用 | 参数 |
|------|------|------|
| `#[Type]` | SQL 类型 | `type`, `length`, `scale` |
| `#[Comment]` | 列注释 | `comment` |
| `#[PrimaryKey]` | 主键 | 无参数 |
| `#[AutoIncrement]` | 自增 | 无参数 |
| `#[DefaultValue]` | 默认值 | `value` |
| `#[Index]` | 普通索引 | `name`（可选，默认自动生成） |
| `#[Json]` | JSON 自动转换 | 无参数，写入 encode，读出 decode |
| `#[ForeignKey]` | 外键 | `table`(引用表), `column='id'`, `onDelete`, `onUpdate` |

### 时间自动填充

| 注解 | 作用 |
|------|------|
| `#[CreatedAt]` | INSERT 时如果为 null，自动填充当前时间（`Y-m-d H:i:s`） |
| `#[UpdatedAt]` | INSERT/UPDATE 时始终填充当前时间 |

### 权限注解（默认允许）

加了即禁止对应操作：

| 注解 | 作用 |
|------|------|
| `#[DisableRead]` | 查询时跳过赋值 |
| `#[DisableCreate]` | INSERT 时跳过该字段 |
| `#[DisableUpdate]` | UPDATE 时跳过该字段 |
| `#[DisableMigrate]` | 迁移/建表时不生成该列 |

### 关联注解

| 注解 | 作用 | 参数 |
|------|------|------|
| `#[BelongsTo(Model::class)]` | BelongsTo（我方存外键） | 关联模型类 |
| `#[HasOne(Model::class)]` | HasOne（对方存外键，一对一） | 关联模型类 |
| `#[HasMany(Model::class)]` | HasMany（对方存外键，一对多） | 关联模型类 |

### 完整模型示例

```php
namespace app\model;

use Lin\Lite\attr\Table;
use Lin\Lite\attr\PrimaryKey;
use Lin\Lite\attr\AutoIncrement;
use Lin\Lite\attr\Type;
use Lin\Lite\attr\DefaultValue;
use Lin\Lite\attr\Comment;
use Lin\Lite\attr\CreatedAt;
use Lin\Lite\attr\UpdatedAt;
use Lin\Lite\attr\BelongsTo;
use Lin\Lite\attr\HasMany;

#[Table(comment: '配置')]
class Config
{
    #[PrimaryKey, AutoIncrement, Type('int', 11), Comment('ID')]
    public $id;

    #[Type('varchar', 100), DefaultValue(''), Comment('名称')]
    public $name;

    #[Type('int', 11), Comment('配置类型ID'), BelongsTo(ConfigType::class)]
    public $config_type_id;

    #[Type('text'), Json, Comment('JSON 值')]
    public $vals;

    #[CreatedAt, Type('datetime'), Comment('创建时间')]
    public $created_at;

    #[UpdatedAt, Type('datetime'), Comment('更新时间')]
    public $updated_at;

    #[HasMany(ConfigValue::class)]
    public $values;
}
```

---

## 创建实例

```php
use Lin\Lite\DB;

// 基于表名
$db = DB::table('config');

// 基于模型（推荐）
$model = new Config();
$db = DB::model($model);
```

---

## 查询

### 条件

```php
// 简单条件，参数绑定
->where('id = ?', 1)
->where('status = ?', 1)
->where('id > ?', 10)

// WHERE 多个自动 AND
->where('status = ?', 1)->where('sort > ?', 0)

// IN 条件自动展开
->where('id IN (?)', [1, 2, 3])

// 非空才添加条件
->whereNotEmpty('name = ?', $name)

// LIKE 模糊搜索，非空才添加，自动加 %
->like('name', $keyword)
->like('name LIKE ?', $keyword) // 手动写 LIKE 也支持
```

### 排序、分页、限制

```php
->order('id desc')
->order('sort asc') // 多个 ORDER BY 逗号拼接

->limit(10)
->limit(10, 20)

->offset(10)

// 分页，自动计算 offset
->page(1, 10)   // 第1页，每页10条，最大不超过100
->page(1, 10, 50) // 自定义最大条数
```

### 选择字段、JOIN、GROUP、HAVING

```php
->select('id', 'name')
->select('id', 'name', 'sort')

->join('INNER JOIN config_type ON config.type_id = config_type.id')
->join('LEFT JOIN other ON ...')

->group('type_id')
->having('count(*) > 1')
```

### 执行查询

```php
// 查询多条，结果赋值传入变量
$list = [];
DB::model(new Config())->find($list);

// 查询一条，结果赋值传入变量
$item = null;
DB::model(new Config())->where('id = ?', 1)->first($item);

// 查询单个字段值，结果赋值传入变量
$name = null;
DB::model(new Config())->where('id = ?', 1)->value('name', $name);

// 查询多个字段值，返回二维数组，结果赋值传入变量
$list = [];
DB::model(new Config())->values(['id', 'name'], $list);

// 查询计数，结果赋值传入变量
$total = 0;
DB::model(new Config())->count($total);

// 检查是否存在，结果赋值传入变量
$exists = false;
DB::model(new Config())->where('name = ?', 'test')->exists($exists);
```

**结果都是通过引用返回，链式调用最后一步是赋值。**

---

## 插入更新

### 保存（自动判断插入/更新）

```php
// 模型对象，有主键值 → 更新，无主键值 → 插入
$model = new Config();
$model->name = 'test';
DB::model($model)->save($model);
// 返回 DB 实例，影响行数需要自行判断

// 自动回写自增ID：插入后 $model->id 已赋值
```

### 插入

```php
// 单条插入
$model = new Config();
$model->name = 'test';
DB::model($model)->create($model);
```

### 更新

```php
// 条件更新多条字段
DB::model(new Config())
    ->where('status = ?', 0)
    ->updates(['sort' => 100]);

// 更新单个字段
DB::model(new Config())
    ->where('id = ?', 1)
    ->update('sort', 100);
```

### 唯一性检查

```php
// 检查 name 唯一，如果已存在抛出异常
// 检查唯一性不会关联链式调用条件，仅使用传入的条件+主键条件（主键值不为空时，主键是链式，一般从模型实例化开始获得）
DB::model(new Config())
    ->checkUnique('名称已存在', 'name = ?', $name);

// 更新时自动排除当前数据，即加上排除当前主键条件
$config = new Config();
$config->id = 1;
$config->name = 'new name';
DB::model($config)->checkUnique('名称已存在', 'name = ?', $name);
```

---

## 删除

```php
// 条件删除
// 尽量使用条件删除，避免删除所有数据
DB::model(new Config())->where('status = ?', 0)->delete();
```

---

## 关联预加载

### 关系说明

| 类型 | 外键位置 | 示例 |
|------|----------|------|
| BelongsTo | 我方表 | 配置 **属于** 一种类型 → `$config_type_id` 加 `#[BelongsTo(ConfigType::class)]` |
| HasOne | 对方表 | 类型 **有一个** 配置 → `$config` 加 `#[HasOne(Config::class)]` |
| HasMany | 对方表 | 类型 **有多个** 配置 → `$configs` 加 `#[HasMany(Config::class)]` |

### 使用方式

```php
// 模型中注解
class Config
{
    #[BelongsTo(ConfigType::class)]
    public $config_type_id;
}

class ConfigType
{
    #[HasMany(Config::class)]
    public $configs;
}

// 查询时 preload() 预加载
$list = [];
DB::model(new Config())
    ->preload(ConfigType::class) // 属性名，自动从注解读关系
    ->find($list);

// 访问结果
foreach ($list as $item) {
    echo $item->configType->name;
}

// HasMany 预加载
$list = [];
DB::model(new ConfigType())
    ->preload(Config::class)
    ->find($list);

foreach ($list as $type) {
    foreach ($type->configs as $config) {
        echo $config->name;
    }
}

// 自定义预加载条件
$list = [];
DB::model(new Config())
    ->preload(ConfigType::class, function ($db) {
        $db->order('name desc');
    })
    ->find($list);
```

---

## 表迁移

根据模型注解自动创建/增量同步表结构。

```php
// 同步表结构，只增加不修改不删除
DB::model(new ConfigType())->syncTable();
DB::model(new Config())->syncTable();
```

**注意**：外键依赖要求先同步被引用的表，再同步引用表。

### 迁移策略

- 表不存在 → `CREATE TABLE IF NOT EXISTS` 整表创建
- 表已存在 → 对比 `INFORMATION_SCHEMA`，只 `ADD COLUMN` 新增列，`ADD INDEX` 新增索引，`ADD FOREIGN KEY` 新增外键
- **已有列/索引** 不会修改/删除

---

## 模型表信息

模型上可以定义方法覆盖自动获取的表信息：

```php
class Config
{
    // 自定义表名，默认自动从类名转换（ConfigType → config_type）
    public function getTable(): string
    {
        return 'config';
    }

    // 自定义主键字段名，默认从数据库查 PRIMARY KEY
    public function getPkName(): array
    {
        return ['id'];
    }

    // 自定义字段列表，默认从 INFORMATION_SCHEMA 查
    public function getFields(): array
    {
        return ['id', 'name', 'sort'];
    }
}
```

---

## 原生 SQL

通过 `DB` 上的静态方法，直接执行原生 SQL：

```php
use Lin\Lite\DB;

// 查询（返回二维数组）
$rows = DB::query('SELECT * FROM config WHERE id = ?', [1]);

// 执行（INSERT/UPDATE/DELETE）
DB::exec('UPDATE config SET sort = 0 WHERE id = ?', [1]);

// 获取最后插入ID
$id = DB::lastInsertId();

// 开启调试
DB::model(new Config())->debug()->find($list);
```

---

## 自定义数据库连接

`ConnInterface` 定义了连接需要实现的接口：

```php
interface ConnInterface
{
    public function getSymbol(): string;           // 名称标识符包裹符，如 `、"
    public function query(string $sql, array $args = []);  // 查询，有返回结果
    public function exec(string $sql, array $args = []);   // 执行，无返回结果
    public function lastInsertId(): int|string;    // 最后插入ID
    public function begin();                       // 开始事务
    public function commit();                      // 提交事务
    public function rollback();                    // 回滚事务
}
```

默认使用 `Conn`（MySQLi 实现），也可切换为自定义连接：

```php
use Lin\Lite\DB;

// 实现 ConnInterface 后替换连接
DB::setConn(new MyCustomConn());
```

替换后所有 ORM 操作都会走自定义连接，不同数据库适配只需实现这个接口。

---

## 事务

```php
use Lin\Lite\DB;
// 方式1，通过函数调用事务，结束自动提交，抛异常自动回滚
DB::transaction(function() {
    DB::model(new Config())->create(...);
    DB::model(new Log())->create(...);
});

// 方式2，自由控制事务，手动提交或回滚
try {
    DB::begin();
    DB::model(new Config())->create(...);
    DB::model(new Log())->create(...);
    DB::commit();
} catch (\Exception $e) {
    DB::rollback();
}
```

---

## Scope 复用

复用通用查询逻辑：

```php
// 闭包方式
DB::model(new Config())
    ->scope(function($db) {
        $db->where('status = ?', 1);
    })
    ->find($list);

// 函数方式
function active(DB $db): void
{
    $db->where('status = ?', 1);
}

DB::model(new Config())
    ->scope('active')
    ->find($list);
```

---

## 调试

```php
// 链式中调用 debug() ，执行终结方法会输出 SQL语句 和参数
// 终结方法：find, first, count, exists, values, value, syncTable, delete, save, create, update, tableExists, getExistingColumns, getExistingForeignKeys, getPkName, getFieldsAttr
// 其实就是在真正执行sql的地方会打印sql语句和参数
DB::model(new Config())
    ->debug()
    ->where('id = ?', 1)
    ->first($item);

// 设置调试打印等级，1: 打印sql语句+参数，2: 1+耗时+返回行数，3: 2+对象执行时间
// 默认：2
DB::model(new Config())
    ->debug()
    ->debugLevel(2)
    ->where('id = ?', 1)
    ->first($item);
```

---

## 回调钩子

模型中可以定义这些方法，会在对应时机调用：

```php
class Config
{
    public function beforeFind() {} // 查询前
    public function afterFind() {} // 查询后
    public function beforeSave() {} // 保存前, save方法，也会执行插入和更新钩子，反之不会，即插入和更新不会执行save钩子方法，保存后相同
    public function afterSave() {} // 保存后
    public function beforeCreate() {} // 插入前
    public function afterCreate() {} // 插入后
    public function beforeUpdate() {} // 更新前
    public function afterUpdate() {} // 更新后
    public function beforeDelete() {} // 删除前
    public function afterDelete() {} // 删除后
}
```