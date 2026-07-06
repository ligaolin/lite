<?php

namespace Lin\Lite;

use Lin\Lite\attr\AutoIncrement;
use Lin\Lite\attr\Comment;
use Lin\Lite\attr\DefaultValue;
use Lin\Lite\attr\ForeignKey;
use Lin\Lite\attr\Index;
use Lin\Lite\attr\DisableMigrate;
use Lin\Lite\attr\PrimaryKey;
use Lin\Lite\attr\Table as TableAttr;
use Lin\Lite\attr\Type;

trait Migrate
{
    use Exec;

    function syncTable()
    {
        $model = $this->model;
        $ref = new \ReflectionClass($model);
        $tableName = $this->getTable();

        $tableAttr = $this->getTableAttr($ref);
        $columns = $this->getColumnDefs($ref);
        $indexes = $this->getIndexDefs($ref);
        $foreignKeys = $this->getForeignKeyDefs($ref);

        $exists = $this->tableExists($tableName);
        if (!$exists) {
            $sql = $this->buildCreateTable($tableName, $columns, $indexes, $foreignKeys, $tableAttr);
            $this->runExec($sql);
            return $this;
        }

        $this->alterColumns($tableName, $columns, $ref);
        $this->alterIndexes($tableName, $indexes, $ref);
        $this->alterForeignKeys($tableName, $foreignKeys, $ref);
        return $this;
    }

    private function tableExists(string $tableName): bool
    {
        $rows = self::runQuery("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$tableName]);
        return !empty($rows);
    }

    private function getTableAttr(\ReflectionClass $ref): ?TableAttr
    {
        $attrs = $ref->getAttributes(TableAttr::class);
        if (!empty($attrs)) {
            return $attrs[0]->newInstance();
        }
        return null;
    }

    private function getColumnDefs(\ReflectionClass $ref): array
    {
        $columns = [];
        $props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            $name = $prop->getName();
            if (!empty($prop->getAttributes(DisableMigrate::class))) continue;
            $def = $this->buildColumnDef($prop, $name);
            if ($def) {
                $columns[$name] = $def;
            }
        }
        return $columns;
    }

    private function buildColumnDef(\ReflectionProperty $prop, string $name): string
    {
        $type = $this->getAttr($prop, Type::class);
        if (!$type) {
            return '';
        }
        $col = strtoupper($type->type);
        if ($type->length !== null) {
            $col .= '(' . $type->length;
            if ($type->scale !== null) {
                $col .= ',' . $type->scale;
            }
            $col .= ')';
        }

        $isPk = !empty($prop->getAttributes(PrimaryKey::class));
        $isAi = !empty($prop->getAttributes(AutoIncrement::class));
        $col .= $isPk ? ' NOT NULL' : ' NULL';

        $dv = $this->getAttr($prop, DefaultValue::class);
        if ($dv) {
            $col .= ' DEFAULT ' . DefaultValue::toSql($dv->value);
        } elseif (!$isPk) {
            $col .= ' DEFAULT NULL';
        }

        if ($isAi) {
            $col .= ' AUTO_INCREMENT';
        }

        $comment = $this->getAttr($prop, Comment::class);
        if ($comment) {
            $col .= " COMMENT '" . addslashes($comment->comment) . "'";
        }

        return $col;
    }

    private function getIndexDefs(\ReflectionClass $ref): array
    {
        $indexes = [];
        $props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            $name = $prop->getName();
            $attrs = $prop->getAttributes(Index::class);
            foreach ($attrs as $attr) {
                $idx = $attr->newInstance();
                $idxName = $idx->name ?: 'idx_' . $name;
                $indexes[$idxName] = $name;
            }
        }
        return $indexes;
    }

    private function getForeignKeyDefs(\ReflectionClass $ref): array
    {
        $fks = [];
        $props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            $name = $prop->getName();
            $fkAttrs = $prop->getAttributes(ForeignKey::class);
            foreach ($fkAttrs as $fkAttr) {
                $fk = $fkAttr->newInstance();
                if (!$fk->migrate) continue;
                $fks[$name] = [
                    'table' => $fk->table,
                    'column' => $fk->column,
                    'onDelete' => $fk->onDelete,
                    'onUpdate' => $fk->onUpdate,
                ];
            }
        }
        return $fks;
    }

    private function getAttr(\ReflectionProperty $prop, string $attrClass): ?object
    {
        $attrs = $prop->getAttributes($attrClass);
        if (!empty($attrs)) {
            return $attrs[0]->newInstance();
        }
        return null;
    }

    private function buildCreateTable(string $tableName, array $columns, array $indexes, array $foreignKeys, ?TableAttr $tableAttr): string
    {
        $symbol = self::$symbol;
        $lines = [];

        $pks = [];
        foreach ($columns as $name => $def) {
            $lines[] = "{$symbol}{$name}{$symbol} $def";
        }

        $ref = new \ReflectionClass($this->model);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!empty($prop->getAttributes(PrimaryKey::class))) {
                $pks[] = $prop->getName();
            }
        }
        if (!empty($pks)) {
            $pkList = join("{$symbol},{$symbol}", $pks);
            $lines[] = "PRIMARY KEY ({$symbol}{$pkList}{$symbol})";
        }

        foreach ($indexes as $idxName => $colName) {
            $lines[] = "INDEX {$symbol}{$idxName}{$symbol} ({$symbol}{$colName}{$symbol})";
        }

        foreach ($foreignKeys as $colName => $fk) {
            $fkName = "fk_{$colName}";
            $line = "CONSTRAINT {$symbol}{$fkName}{$symbol} FOREIGN KEY ({$symbol}{$colName}{$symbol}) REFERENCES {$symbol}{$fk['table']}{$symbol} ({$symbol}{$fk['column']}{$symbol})";
            if ($fk['onDelete']) {
                $line .= " ON DELETE {$fk['onDelete']}";
            }
            if ($fk['onUpdate']) {
                $line .= " ON UPDATE {$fk['onUpdate']}";
            }
            $lines[] = $line;
        }

        $engine = $tableAttr ? $tableAttr->engine : 'InnoDB';
        $charset = $tableAttr ? $tableAttr->charset : 'utf8mb4';
        $collation = $tableAttr ? $tableAttr->collation : 'utf8mb4_general_ci';
        $comment = $tableAttr ? $tableAttr->comment : '';

        $sql = "CREATE TABLE IF NOT EXISTS {$symbol}{$tableName}{$symbol} (\n  "
            . join(",\n  ", $lines)
            . "\n) ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collation}";
        if ($comment) {
            $sql .= " COMMENT='" . addslashes($comment) . "'";
        }
        return $sql;
    }

    private function alterColumns(string $tableName, array $columns, \ReflectionClass $ref)
    {
        $existing = $this->getExistingColumns($tableName);
        foreach ($columns as $name => $def) {
            if (!isset($existing[$name])) {
                $symbol = self::$symbol;
                $sql = "ALTER TABLE {$symbol}{$tableName}{$symbol} ADD COLUMN {$symbol}{$name}{$symbol} $def";
                $this->runExec($sql);
            }
        }
    }

    private function alterIndexes(string $tableName, array $indexes, \ReflectionClass $ref)
    {
        $existing = $this->getExistingIndexes($tableName);
        foreach ($indexes as $idxName => $colName) {
            if (!isset($existing[$idxName])) {
                $symbol = self::$symbol;
                $sql = "ALTER TABLE {$symbol}{$tableName}{$symbol} ADD INDEX {$symbol}{$idxName}{$symbol} ({$symbol}{$colName}{$symbol})";
                $this->runExec($sql);
            }
        }
    }

    private function alterForeignKeys(string $tableName, array $foreignKeys, \ReflectionClass $ref)
    {
        $existing = $this->getExistingForeignKeys($tableName);
        foreach ($foreignKeys as $colName => $fk) {
            $fkName = "fk_{$colName}";
            if (!isset($existing[$fkName])) {
                $symbol = self::$symbol;
                $sql = "ALTER TABLE {$symbol}{$tableName}{$symbol} ADD CONSTRAINT {$symbol}{$fkName}{$symbol} FOREIGN KEY ({$symbol}{$colName}{$symbol}) REFERENCES {$symbol}{$fk['table']}{$symbol} ({$symbol}{$fk['column']}{$symbol})";
                if ($fk['onDelete']) {
                    $sql .= " ON DELETE {$fk['onDelete']}";
                }
                if ($fk['onUpdate']) {
                    $sql .= " ON UPDATE {$fk['onUpdate']}";
                }
                $this->runExec($sql);
            }
        }
    }

    private function getExistingColumns(string $tableName): array
    {
        $rows = self::runQuery("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$tableName]);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['COLUMN_NAME']] = true;
        }
        return $map;
    }

    private function getExistingIndexes(string $tableName): array
    {
        $rows = self::runQuery('SHOW INDEX FROM ' . self::$symbol . $tableName . self::$symbol . " WHERE Key_name != 'PRIMARY'");
        $map = [];
        foreach ($rows as $row) {
            $map[$row['Key_name']] = true;
        }
        return $map;
    }

    private function getExistingForeignKeys(string $tableName): array
    {
        $rows = self::runQuery("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL", [$tableName]);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['CONSTRAINT_NAME']] = true;
        }
        return $map;
    }
}