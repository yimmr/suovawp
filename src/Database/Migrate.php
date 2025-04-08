<?php

namespace Suovawp\Database;

use Suovawp\Utils\Str;
use Suovawp\Validation\Validator as v;

class Migrate
{
    protected static $isMigrating = false;

    /**
     * @template T of Schema
     * @param class-string<T>[] $schemas
     * @param bool              $cleanCols 是否清理旧的列
     */
    public function migrate($schemas, $prefix, $charsetCollate = '', $cleanCols = false)
    {
        if (static::$isMigrating) {
            return false;
        }
        static::$isMigrating = true;
        foreach ($schemas as $schema) {
            $sql = static::buildCreateTableSQL($schema, $prefix, $charsetCollate);
            // echo '<p style="white-space: pre;">'.var_export($sql, true).'</p>';
            dbDelta($sql);
            if ($cleanCols) {
                global $wpdb;
                $table = self::schemaToTable($schema, $prefix);
                $existingColumns = $wpdb->get_col("DESC $table", 0);
                $columnsToDrop = array_diff($existingColumns, $schema::getColumns());
                $dropcolsql = '';
                foreach ($columnsToDrop as $column) {
                    // 跳过主键和其他不能删除的列
                    if ($schema::ID === $column || in_array($column, $schema::PK)) {
                        continue;
                    }
                    $dropcolsql .= ', DROP COLUMN '.$column;
                }
                if ($dropcolsql) {
                    $wpdb->query("ALTER TABLE $table".ltrim($dropcolsql, ',').';');
                }
            }
        }
        static::$isMigrating = false;
    }

    public function migrateTable($oldTable, $newTable, $autoPrefix = true)
    {
        global $wpdb;
        if ($autoPrefix) {
            $oldTable = $wpdb->prefix.$oldTable;
            $newTable = $wpdb->prefix.$newTable;
        }
        if (!DB::tableExists($oldTable)) {
            return false;
        }
        if (!DB::tableExists($newTable)) {
            return $wpdb->query("RENAME TABLE `$oldTable` TO `$newTable`");
        } else {
            $rows = $wpdb->get_results("SELECT * FROM `{$newTable}`", ARRAY_A);
            if ($rows) {
                $query = new Query($wpdb, $oldTable);
                $query->createMany($rows);
                if ($query->hasError()) {
                    return false;
                }
            }
            $wpdb->query("DROP TABLE `{$newTable}`");
            return $wpdb->query("RENAME TABLE `$oldTable` TO `$newTable`");
        }
    }

    /**
     * @template T of Schema
     * @param class-string<T> $schema
     */
    public static function schemaToTable($schema, $prefix = '')
    {
        if (!$schema::TABLE) {
            $pos = strrpos($schema, '\\');
            $className = false !== $pos ? substr($schema, $pos + 1) : $schema;
            $table = Str::snake($className);
            return $prefix.$table;
        } else {
            return $prefix.$schema::TABLE;
        }
    }

    /**
     * @template T of Schema
     * @param class-string<T> $schema
     */
    public function buildCreateTableSQL($schema, $prefix = '', $charsetCollate = '')
    {
        $table = self::schemaToTable($schema, $prefix);
        $fields = [];
        $pk = [];
        foreach ($schema::FIELDS as $column => $info) {
            $info = $this->parseDBField($info);
            $column = ($info['map'] ?? null) ?: $column;
            $field = "  `{$column}` {$info['type']}";  // 添加缩进
            if ($info['is_pk'] ?? false) {
                $pk[] = $column;
            }
            if (!($info['optional'] ?? false)) {
                $field .= ' NOT NULL';
            }
            if (!empty($info['sub'])) {
                $field .= ' '.$info['sub'];
            }
            if (isset($info['default'])) {
                $field .= ' DEFAULT '.$info['default'];
            }
            if (isset($info['comment'])) {
                $field .= " COMMENT '{$info['comment']}'";
            }
            $fields[] = $field;
        }
        if ($schema::PK) {
            $pk = array_merge([$pk, is_array($schema::PK) ? $schema::PK : [$schema::PK]]);
        }
        if ($pk) {
            $pk = implode('`,`', array_unique($pk));
            $fields[] = "  PRIMARY KEY (`{$pk}`)";
        }
        // 使用换行符连接字段定义
        $sql = "CREATE TABLE `{$table}` (\n".implode(",\n", $fields)."\n)";
        if ($charsetCollate) {
            $sql .= ' '.$charsetCollate;
        }
        return $sql.';';
    }

    public function parseDBField($field)
    {
        $override = [];
        if (isset($field['db'])) {
            $segs = explode('|', $field['db']);
            $sub = '';
            foreach ($segs as $method) {
                $param = '';
                if (false !== ($pos = strpos($method, ':'))) {
                    $param = substr($method, $pos + 1);
                    $method = substr($method, 0, $pos);
                }
                switch ($method) {
                    case 'PK':
                        $sub .= ' AUTO_INCREMENT';
                        $override['is_pk'] = true;
                        break;
                    case 'sub':
                        $sub .= ' '.$param;
                        break;
                    case 'default':
                        switch ($param) {
                            case 'now()':
                                $override['default'] = 'CURRENT_TIMESTAMP';
                                break;
                            case 'now(3)':
                                $override['default'] = 'CURRENT_TIMESTAMP(3)';
                                break;
                            default:
                                $override['default'] = (is_string($param) ? "'{$param}'" : $param);
                                break;
                        }
                        break;
                    default:
                        $override[$method] = $param;
                        break;
                }
            }
            $override['sub'] = $sub ? trim($sub, ' ') : null;
        }
        if (!isset($override['type'])) {
            $type = $field['type'];
            if (!isset(DB::MYSQL_TYPE_MAP[$type])) {
                throw new \InvalidArgumentException("Unsupported schema type: {$type}");
            }
            $override['type'] = DB::MYSQL_TYPE_MAP[$type];
        }
        if (!isset($override['default']) && array_key_exists('default', $field)) {
            $default = $field['default'];
            $override['default'] = is_string($default) ? "'{$default}'" : (is_null($default) ? 'NULL' : $default);
        }
        return $override + $field;
    }

    /*
     * @template T of Schema
     *
     * @param class-string<T> $schema
     */
    // public function toValidatorSchema($schema)
    // {
    //     $vschema = [];
    //     foreach ($schema::FIELDS as $column => $info) {
    //         $info = $this->parseDBField($info);
    //         $type = $info['type'];
    //         $label = $info['comment'] ?? $column;
    //         switch ($type) {
    //             case 'decimal':
    //                 //  $type = v::number($label)->decimal();
    //                 break;
    //             case 'datetime':
    //                 $type = v::date($label);
    //                 break;
    //             case 'json':
    //                 $type = v::string($label)->json();
    //                 break;
    //             case 'bytes':
    //                 $type = v::string($label);
    //                 break;
    //             default:
    //                 $type = v::{$type}($label);
    //                 break;
    //         }
    //         if (isset($info['default'])) {
    //             $type->default($info['default']);
    //         }
    //         $vschema[$column] = $type;
    //     }
    //     return v::array($vschema);
    // }
}
