<?php

namespace Lin\Lite;

use Lin\Lite\attr\DefaultValue;

trait Migrate
{
    // 依赖 DB 组合 Save（Save -> Query -> Table -> Exec）间接提供
    // getTable()/runQuery()/runExec()/getPropAttr()/$symbol 等成员。
    // 切勿在此 use Table/Exec：PHP 5.4 会把“同一 trait 经多条路径引入”
    // 判定为方法冲突，导致 Fatal error。
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

    private function tableExists($tableName)
    {
        $rows = self::runQuery("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", array($tableName));
        return !empty($rows);
    }

    private function getTableAttr(\ReflectionClass $ref)
    {
        $attrs = AnnotationReader::getClassAnnotations($ref->getName(), __NAMESPACE__ . '\attr\Table');
        if (!empty($attrs)) {
            return $attrs[0];
        }
        return null;
    }

    private function getColumnDefs(\ReflectionClass $ref)
    {
        $columns = array();
        $props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            $name = $prop->getName();
            if (AnnotationReader::hasPropertyAnnotation($ref->getName(), $name, __NAMESPACE__ . '\attr\DisableMigrate')) continue;
            $def = $this->buildColumnDef($prop, $name);
            if ($def) {
                $columns[$name] = $def;
            }
        }
        return $columns;
    }

    private function buildColumnDef(\ReflectionProperty $prop, $name)
    {
        $type = $this->getPropAttr($prop, __NAMESPACE__ . '\attr\Type');
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

        $isPk = AnnotationReader::hasPropertyAnnotation($prop->getDeclaringClass()->getName(), $name, __NAMESPACE__ . '\attr\PrimaryKey');
        $isAi = AnnotationReader::hasPropertyAnnotation($prop->getDeclaringClass()->getName(), $name, __NAMESPACE__ . '\attr\AutoIncrement');
        $col .= $isPk ? ' NOT NULL' : ' NULL';

        $dv = $this->getPropAttr($prop, __NAMESPACE__ . '\attr\DefaultValue');
        if ($dv) {
            $col .= ' DEFAULT ' . DefaultValue::toSql($dv->value);
        } elseif (!$isPk) {
            $col .= ' DEFAULT NULL';
        }

        if ($isAi) {
            $col .= ' AUTO_INCREMENT';
        }

        $comment = $this->getPropAttr($prop, __NAMESPACE__ . '\attr\Comment');
        if ($comment) {
            $col .= " COMMENT '" . addslashes($comment->comment) . "'";
        }

        return $col;
    }

    private function getIndexDefs(\ReflectionClass $ref)
    {
        $indexes = array();
        $props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            $name = $prop->getName();
            $attrs = AnnotationReader::getPropertyAnnotations($ref->getName(), $name, __NAMESPACE__ . '\attr\Index');
            foreach ($attrs as $idx) {
                $idxName = $idx->name ?: 'idx_' . $name;
                $indexes[$idxName] = $name;
            }
        }
        return $indexes;
    }

    private function getForeignKeyDefs(\ReflectionClass $ref)
    {
        $fks = array();
        $props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            $name = $prop->getName();
            $fkAttrs = AnnotationReader::getPropertyAnnotations($ref->getName(), $name, __NAMESPACE__ . '\attr\ForeignKey');
            foreach ($fkAttrs as $fk) {
                if (!$fk->migrate) continue;
                $fks[$name] = array(
                    'table' => $fk->table,
                    'column' => $fk->column,
                    'onDelete' => $fk->onDelete,
                    'onUpdate' => $fk->onUpdate,
                );
            }
        }
        return $fks;
    }

    private function buildCreateTable($tableName, $columns, $indexes, $foreignKeys, $tableAttr)
    {
        $symbol = $this->getQuoteSymbol();
        $lines = array();

        $pks = array();
        foreach ($columns as $name => $def) {
            $lines[] = "{$symbol}{$name}{$symbol} $def";
        }

        $ref = new \ReflectionClass($this->model);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if (AnnotationReader::hasPropertyAnnotation($ref->getName(), $prop->getName(), __NAMESPACE__ . '\attr\PrimaryKey')) {
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

    private function alterColumns($tableName, $columns, \ReflectionClass $ref)
    {
        $existing = $this->getExistingColumns($tableName);
        foreach ($columns as $name => $def) {
            if (!isset($existing[$name])) {
                $symbol = $this->getQuoteSymbol();
                $sql = "ALTER TABLE {$symbol}{$tableName}{$symbol} ADD COLUMN {$symbol}{$name}{$symbol} $def";
                $this->runExec($sql);
            }
        }
    }

    private function alterIndexes($tableName, $indexes, \ReflectionClass $ref)
    {
        $existing = $this->getExistingIndexes($tableName);
        foreach ($indexes as $idxName => $colName) {
            if (!isset($existing[$idxName])) {
                $symbol = $this->getQuoteSymbol();
                $sql = "ALTER TABLE {$symbol}{$tableName}{$symbol} ADD INDEX {$symbol}{$idxName}{$symbol} ({$symbol}{$colName}{$symbol})";
                $this->runExec($sql);
            }
        }
    }

    private function alterForeignKeys($tableName, $foreignKeys, \ReflectionClass $ref)
    {
        $existing = $this->getExistingForeignKeys($tableName);
        foreach ($foreignKeys as $colName => $fk) {
            $fkName = "fk_{$colName}";
            if (!isset($existing[$fkName])) {
                $symbol = $this->getQuoteSymbol();
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

    private function getExistingColumns($tableName)
    {
        $rows = self::runQuery("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", array($tableName));
        $map = array();
        foreach ($rows as $row) {
            $map[$row['COLUMN_NAME']] = true;
        }
        return $map;
    }

    private function getExistingIndexes($tableName)
    {
        $rows = self::runQuery('SHOW INDEX FROM ' . $this->getQuoteSymbol() . $tableName . $this->getQuoteSymbol() . " WHERE Key_name != 'PRIMARY'");
        $map = array();
        foreach ($rows as $row) {
            $map[$row['Key_name']] = true;
        }
        return $map;
    }

    private function getExistingForeignKeys($tableName)
    {
        $rows = self::runQuery("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL", array($tableName));
        $map = array();
        foreach ($rows as $row) {
            $map[$row['CONSTRAINT_NAME']] = true;
        }
        return $map;
    }
}