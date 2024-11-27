<?php

namespace Suovawp\Database;

use Suovawp\Utils\Str;

/**
 * @phpstan-import-type Wheres from Query as QueryWhere
 */
class DB
{
    public const MYSQL_TYPE_MAP = [
        'string'   => 'VARCHAR(200)',
        'boolean'  => 'TINYINT(1)',
        'int'      => 'INT',
        'bigint'   => 'BIGINT',
        'float'    => 'DOUBLE',
        'decimal'  => 'DECIMAL(65,30)',
        'datetime' => 'DATETIME(3)',
        'json'     => 'JSON',
        'bytes'    => 'LONGBLOB',
    ];

    public const FORMAT_MAP = [
        'string'   => '%s',
        'int'      => '%d',
        'bigint'   => '%d',
        'float'    => '%f',
        'decimal'  => '%s',
        'datetime' => '%s',
        'json'     => '%s',
        'bytes'    => '%s',
    ];

    /**
     * @template T of Schema
     * @param class-string<T> $schema
     */
    public static function createQuery($schema, $as = null)
    {
        global $wpdb;
        $table = $schema::TABLE ?: static::classNameToTableName($schema);
        return (new Query($wpdb, $wpdb->prefix.$table, $as))->setSchema($schema);
    }

    /**
     * @template T of Schema
     * @param class-string<T> $schema
     */
    public static function fullTableName($schema)
    {
        global $wpdb;
        return $wpdb->prefix.($schema::TABLE ?: static::classNameToTableName($schema));
    }

    public static function classNameToTableName($schema)
    {
        $pos = strrpos($schema, '\\');
        $className = false !== $pos ? substr($schema, $pos + 1) : $schema;
        return Str::snake($className);
    }

    public static function rename($oldTable, $newTable, $usePrefix = true)
    {
        global $wpdb;
        if ($usePrefix) {
            $oldTable = $wpdb->prefix.$oldTable;
            $newTable = $wpdb->prefix.$newTable;
        }
        return (bool) $wpdb->query("RENAME TABLE `$oldTable` TO `$newTable`");
    }

    /**
     * 删除数据表.
     *
     * @param string|array $tables    表名
     * @param bool         $usePrefix 是否自动添加表前缀
     */
    public static function drop($tables, $usePrefix = true)
    {
        global $wpdb;
        if (!is_array($tables)) {
            $tables = [$tables];
        }
        if ($usePrefix) {
            $tables = array_map(fn ($table) => "`{$wpdb->prefix}{$table}`", $tables);
        } else {
            $tables = array_map(fn ($table) => "`{$table}`", $tables);
        }
        return (bool) $wpdb->query('DROP TABLE IF EXISTS '.implode(',', $tables));
    }

    /**
     * 返回占位符替代值的新数据和一组待插值，已剔除模式未定义的字段.
     */
    public static function parseDataFormatsValues($schema, $data)
    {
        $formatData = [];
        $values = [];
        foreach ($data as $field => $value) {
            if (!empty($f = static::getSchemaFieldFormat($schema, $field))) {
                $formatData[$field] = $f;
                $values[] = $value;
            }
        }
        return [$formatData, $values];
    }

    /**
     * 返回一组字段，多组占位符和一组待插值，已剔除模式未定义的字段.
     */
    public static function parseDataManyFormatsValues($schema, $data)
    {
        if (empty($data)) {
            return [[], [], []];
        }
        // 收集所有可能出现的字段
        $allFields = [];
        foreach ($data as $row) {
            $allFields = array_merge($allFields, array_keys($row));
        }
        $allFields = array_unique($allFields);
        // 筛选有效字段和对应格式
        $fields = [];
        $formats = [];
        foreach ($allFields as $field) {
            if ($f = static::getSchemaFieldFormat($schema, $field)) {
                $fields[] = $field;
                $formats[] = $f;
            }
        }
        $rows = [];
        $values = [];
        foreach ($data as $item) {
            $row = [];
            foreach ($fields as $i => $field) {
                if (isset($item[$field])) {
                    $row[] = $formats[$i];
                    $values[] = $item[$field];
                } else {
                    $row[] = 'DEFAULT';
                }
            }
            $rows[] = $row;
        }
        return [$fields, $rows, $values];
    }

    public static function getSchemaFieldFormat($schema, $field)
    {
        if (isset($schema::FIELDS[$field])) {
            $type = $schema::FIELDS[$field]['type'];
            return self::FORMAT_MAP[$type];
        }
    }

    /**
     * @template T of Schema
     * @param T[]                                   $schemas
     * @param array{veropt?:string,version?:string} $options
     */
    public static function migrate($schemas, $options = [])
    {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $migrate = new Migrate();
        $result = $migrate->migrate($schemas, $wpdb->prefix, $wpdb->get_charset_collate());
        if (false === $result) {
            return false;
        }
        if (!empty($wpdb->last_error)) {
            return ['error' => $wpdb->last_error];
        }
        if (isset($options['version'])) {
            update_option($options['veropt'], $options['version']);
        }
        return true;
    }
}
