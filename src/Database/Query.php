<?php

namespace Suovawp\Database;

use Suovawp\Utils\Arr;

/**
 * @template S of Schema
 *
 * @phpstan-type Switch array{branches:array{case:mixed,then:mixed}[],default:mixed}
 * @phpstan-type Condition array{
 *      $eq?:mixed,$ne?:mixed,$lt?:mixed,$lte?:mixed,$gt?:mixed,$gte?:mixed,$in?:array,
 *      $nin?:array,$between?:array,$regex?:string,$like?:string,
 *      $startsWith:string,$endsWith:string,$contains:string,$date?:array,$switch?:Switch
 * }
 * @phpstan-type Wheres array<string,Condition|array{$or?:Wheres[],$and?:Wheres[]}|mixed>
 * @phpstan-type Join array{type:string,table:string,as?:string,on?:array|string}
 * @phpstan-type QueryOptions array{
 *      table?:string,as?:string,
 *      select?:string|string[],where?:Wheres,join?:Join[],
 *      groupby?:string|string[],having?:array,
 *      orderby?:array,limit?:int,offset?:int,
 *      omit?:string[],
 * }
 *
 * @property int|null        $limit
 * @property int|null        $offset
 * @property class-string<S> $schema
 * @property \wpdb           $db
 */
class Query
{
    /** @var string */
    protected $table;

    /** @var string|null */
    protected $as;

    /** @var string|string[]|null */
    protected $select;

    /** @var Wheres|null */
    protected $where;

    /** @var Join[] */
    protected $join = [];

    /** @var string|string[]|null */
    protected $groupby;

    /** @var Wheres|null */
    protected $having;

    /** @var array|null */
    protected $orderby;

    /** @var int|null */
    protected $limit;

    /** @var int|null */
    protected $offset;

    /** @var string[]|null */
    protected $omit;

    protected $formatValues = [];

    /** @var class-string<S> */
    protected $schema;

    protected $defaultDateColumn;

    /** @var \wpdb */
    protected $db;

    /**
     * @param string $table
     * @param string $as
     */
    public function __construct($db = null, $table = '', $as = null)
    {
        $this->table = $table;
        $this->as = $as;
        $this->db = $db;
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function toArray()
    {
        return [
            'table'   => $this->table,
            'as'      => $this->as,
            'select'  => $this->select,
            'join'    => $this->join,
            'where'   => $this->where,
            'groupby' => $this->groupby,
            'having'  => $this->having,
            'orderby' => $this->orderby,
            'limit'   => $this->limit,
            'offset'  => $this->offset,
        ];
    }

    /**
     * 设置模式同时自动设置时间列为CREATED_AT.
     *
     * @template T of S
     * @param class-string<T> $schema
     */
    public function setSchema($schema)
    {
        $this->schema = $schema;
        $this->setDateCol($schema::CREATED_AT);
        return $this;
    }

    /**
     * 设置时间列名后，查询参数中可以省略列名，直接用$date代替.
     */
    public function setDateCol(string $column)
    {
        $this->defaultDateColumn = $column ?: null;
        return $this;
    }

    /**
     * 替代链式调用传递参数.
     *
     * @param QueryOptions $options
     */
    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }

    /**
     * 指定条件数组.
     *
     * @param Wheres $wheres 键值对条件数组
     *                       - 数组键是列名或特定操作，值是条件的值
     *                       - 支持多层嵌套
     *                       - 逻辑关系：$or,$and 用索引数组指定一组 `OR`|`AND` 逻辑。（关联数组隐式 `AND` 逻辑，$relation键可修改)
     *                       - 操作符：$eq,$ne,$lt,$lte,$gt,$gte,$in,$nin,$between,$regex,$like,$startsWith,$endsWith,$contains
     *                       - 其他：$exists,$switch,$date
     *                       - $date 使用 `WP_Date_Query` 解析构建条件。
     *                       - $date 用做条件列时，尝试从Schema::CREATED_AT获取真实列名，获取失败则无效
     *                       - $exists 只能做条件列，且值不转义
     *                       - 条件值不需转义时，可加 $raw 操作符
     */
    public function where(array $wheres)
    {
        $this->where = $wheres;
        return $this;
    }

    /** @param string|string[] $columns */
    public function select($columns)
    {
        $this->select = $columns;
        return $this;
    }

    /** 排除一些列 */
    public function omit($keys)
    {
        $this->omit = $keys;
        return $this;
    }

    public function form($table, $as = null)
    {
        $this->table = $table;
        $this->as = $as;
        return $this;
    }

    public function as($as)
    {
        $this->as = $as;
        return $this;
    }

    /**
     * @param string|string[] $columns
     */
    public function groupBy($columns)
    {
        $this->groupby = $columns;
        return $this;
    }

    /**
     * 类似where，主要用于分组后筛选.
     *
     * @param Wheres $conditions
     */
    public function having($conditions)
    {
        $this->having = $conditions;
        return $this;
    }

    /**
     * @param string|array<string,string> $orderby
     * @param string|null                 $sort
     */
    public function orderBy($orderby, $sort = 'ASC')
    {
        $this->orderby = is_array($orderby) ? $orderby : [$orderby => $sort];
        return $this;
    }

    public function limit($number, $offset = null)
    {
        $this->limit = (int) $number;
        $this->offset = isset($offset) ? (int) ($offset) : null;
        return $this;
    }

    public function page($page, $size = 10)
    {
        $this->limit = (int) $size;
        $this->offset = ((int) $page - 1) * $this->limit;
        return $this;
    }

    /**
     * @param string                         $table
     * @param string|Wheres|null             $as    可以是别名或`on`条件
     * @param string|Wheres|null             $on
     * @param 'INNER'|'LEFT'|'RIGHT'|'CROSS' $type  大写
     */
    public function join($table, $as = null, $on = null, $type = 'INNER')
    {
        if (isset($as) && !isset($on)) {
            $on = $as;
            $as = null;
        }
        $this->join[] = ['type' => $type, 'table' => $table, 'as' => $as, 'on' => $on];
        return $this;
    }

    public function leftJoin($table, $as = null, $on = null)
    {
        return $this->join($table, $as, $on, 'LEFT');
    }

    public function rightJoin($table, $as = null, $on = null)
    {
        return $this->join($table, $as, $on, 'RIGHT');
    }

    public function crossJoin($table, $as = null, $on = null)
    {
        return $this->join($table, $as, $on, 'CROSS');
    }

    public function toSelectSQL()
    {
        $select = '*';
        if ($this->omit && $this->schema) {
            $select = Arr::except(array_keys($this->schema::FIELDS), $this->omit);
            $select = implode(', ', $select);
        } elseif (isset($this->select)) {
            $select = $this->select;
            $select = is_array($select) ? implode(', ', $select) : $select;
        }
        $sql = "SELECT {$select} FROM `{$this->table}`".(isset($this->as) ? ' AS '.$this->as : '');
        if (!empty($this->join)) {
            foreach ($this->join as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']}".(isset($join['as']) ? ' AS '.$join['as'] : '');
                if (isset($join['on'])) {
                    $sql .= ' ON '.$this->buildWhereClauseIf($join['on']);
                }
            }
        }
        if ($this->where) {
            $sql .= ' WHERE '.$this->buildWhereClause($this->where);
        }
        if (isset($this->groupby)) {
            $func = [$this, 'parseDateBy'];
            $sql .= ' GROUP BY '.(is_array($this->groupby) ? implode(', ', array_map($func, $this->groupby)) : $func($this->groupby));
        }
        if (isset($this->having)) {
            $sql .= ' HAVING '.$this->buildWhereClause($this->having);
        }
        if (isset($this->orderby)) {
            $orderby = '';
            foreach ($this->orderby as $by => $sort) {
                $by = $this->parseDateBy($by);
                $orderby .= ", $by ".strtoupper($sort);
            }
            $sql .= ' ORDER BY '.ltrim($orderby, ', ');
        }
        if (isset($this->limit)) {
            $sql .= ' LIMIT '.$this->limit;
        }
        if (isset($this->offset)) {
            $sql .= ' OFFSET '.$this->offset;
        }
        return $this->prepareIf($sql);
    }

    protected function parseDateBy($by)
    {
        return isset($this->defaultDateColumn) && ('$date' == $by || 'date' == $by) ? $this->defaultDateColumn : $by;
    }

    public function toCreateSQL($data, $multiple = false)
    {
        if ($multiple) {
            [$fields, $rows, $values] = DB::parseDataManyFormatsValues($this->schema, $data);
            $this->formatValues = $values;
        } else {
            [$formats, $values] = DB::parseDataFormatsValues($this->schema, $data);
            $this->formatValues = $values;
            $fields = array_keys($formats);
            $rows = [array_values($formats)];
        }
        $fields = '`'.implode('`, `', $fields).'`';
        $rows = implode(', ', array_map(fn ($v) => '('.implode(', ', $v).')', $rows));
        $sql = "INSERT INTO `{$this->table}` ($fields) VALUES $rows";
        return $this->prepareIf($sql);
    }

    public function toUpdateSQL($data, $raw = false)
    {
        if (!$raw) {
            [$formats, $values] = DB::parseDataFormatsValues($this->schema, $data, $raw);
            $this->formatValues = $values;
        } else {
            $formats = $data;
        }
        $fields = [];
        foreach ($formats as $field => $value) {
            $fields[] = "`$field` = ".$value;
        }
        $fields = implode(', ', $fields);
        $sql = "UPDATE `{$this->table}` SET $fields WHERE {$this->buildWhereClause($this->where)}";
        return $this->prepareIf($sql);
    }

    public function toDeleteSQL()
    {
        $sql = "DELETE FROM `{$this->table}` WHERE {$this->buildWhereClause($this->where)}";
        return $this->prepareIf($sql);
    }

    protected function buildWhereClauseIf($query)
    {
        return is_string($query) ? $query : $this->buildWhereClause($query);
    }

    protected function buildWhereClause(array $query)
    {
        if (isset($query['$or']) && 1 == count($query)) {
            return $this->buildSQLLogicCondition($query['$or'], 'OR', false);
        }
        if (isset($query['$and']) && 1 == count($query)) {
            return $this->buildSQLLogicCondition($query['$and'], 'AND', false);
        }
        return $this->buildSQLCondition($query);
    }

    protected function buildSQLLogicCondition(array $wheres, string $logic = 'AND', $wrap = true)
    {
        $conditions = [];
        foreach ($wheres as $where) {
            $condition = $this->buildSQLCondition($where);
            $conditions[] = $wrap && count($where) > 1 ? '('.$condition.')' : $condition;
        }
        return implode(' '.$logic.' ', $conditions);
    }

    protected function buildSQLCondition(array $query, string $logic = 'AND')
    {
        $conditions = [];
        foreach ($query as $key => $value) {
            if ('$relation' === $key) {
                $logic = strtoupper($value);
                continue;
            }
            if ('$or' === $key) {
                $conditions[] = '('.$this->buildSQLLogicCondition($value, 'OR').')';
                continue;
            }
            if ('$and' === $key) {
                $conditions[] = '('.$this->buildSQLLogicCondition($value, 'AND').')';
                continue;
            }
            if ('$exists' === $key) {
                $conditions[] = "EXISTS ({$value})";
                continue;
            }
            if ('$date' === $key) {
                if (isset($this->defaultDateColumn) && $value) {
                    $conditions[] = $this->parseCondition($this->defaultDateColumn, ['$date' => $value]);
                }
                continue;
            }
            $conditions[] = is_array($value) ? $this->parseCondition($key, $value) : "$key = {$this->valueFormat($value, $key)}";
        }
        return implode(" $logic ", $conditions);
    }

    protected function parseCondition($field, array $value)
    {
        $format = true;
        if (isset($value['$raw'])) {
            $format = !$value['$raw'];
            unset($value['$raw']);
        }
        if (($count = count($value)) > 1) {
            $conditions = [];
            for ($i = 0; $i < $count; ++$i) {
                if (false === ($condition = $this->parseConditionStr($field, $value, $format))) {
                    break;
                }
                $conditions[] = $condition;
            }
            $condition = empty($conditions) ? false : '('.implode(' AND ', $conditions).')';
        } else {
            $condition = $this->parseConditionStr($field, $value, $format);
        }
        if (false === $condition) {
            $condition = $this->parseConditionStr($field, ['$in' => $value], $format);
        }
        return $condition;
    }

    protected function parseConditionStr($field, $value, $format = true)
    {
        if (array_key_exists('$eq', $value)) {
            $value = $value['$eq'];
            if (is_null($value)) {
                $operator = 'IS';
                $value = 'NULL';
                $format = false;
            } else {
                $operator = '=';
            }
        } elseif (array_key_exists('$ne', $value)) {
            $value = $value['$ne'];
            if (is_null($value)) {
                $operator = 'IS NOT';
                $value = 'NULL';
                $format = false;
            } else {
                $operator = '!=';
            }
        } elseif (isset($value['$lt'])) {
            $operator = '<';
            $value = $value['$lt'];
        } elseif (isset($value['$lte'])) {
            $operator = '<=';
            $value = $value['$lte'];
        } elseif (isset($value['$gt'])) {
            $operator = '>';
            $value = $value['$gt'];
        } elseif (isset($value['$gte'])) {
            $operator = '>=';
            $value = $value['$gte'];
        } elseif (isset($value['$regex'])) {
            $operator = 'REGEXP';
            $value = $value['$regex'];
            $formatType = '%s';
        } elseif (isset($value['$between'])) {
            $operator = 'BETWEEN';
            $value = $this->arrayValueJoin(' AND ', $value['$between'], $format);
            $format = false;
        } elseif (isset($value['$nin'])) {
            $operator = 'NOT IN';
            $value = '('.$this->arrayValueJoin(', ', $value['$nin'], $format).')';
            $format = false;
        } elseif (isset($value['$in'])) {
            $operator = 'IN';
            $value = '('.$this->arrayValueJoin(', ', $value['$in'], $format).')';
            $format = false;
        } elseif (isset($value['$switch'])) {
            return $this->buildSwitch($value['$switch'], $field);
        } elseif (isset($value['$date'])) {
            return $this->parseDateQuery($field, $value['$date']);
        } elseif (isset($value['$like'])) {
            $operator = 'LIKE';
            $value = $value['$like'];
            $formatType = '%s';
        } elseif (isset($value['$contains'])) {
            $operator = 'LIKE';
            $value = '%'.$value['$contains'].'%';
            $formatType = '%s';
        } elseif (isset($value['$startsWith'])) {
            $operator = 'LIKE';
            $value = $value['$startsWith'].'%';
            $formatType = '%s';
        } elseif (isset($value['$endsWith'])) {
            $operator = 'LIKE';
            $value = '%'.$value['$endsWith'];
            $formatType = '%s';
        } else {
            return false;
        }
        $value = $format ? $this->valueFormat($value, $field, $formatType ?? null) : $value;
        return "$field $operator $value";
    }

    /**
     * @param Switch $args
     */
    protected function buildSwitch($args, $expression = null)
    {
        $switch = 'CASE';
        if (empty($expression)) {
            foreach ($args['branches'] as $branch) {
                $case = $branch['case'];
                $case = $this->buildWhereClause($case);
                $switch .= " WHEN $case THEN ".$this->getValueForType($branch['then']);
            }
        } else {
            $switch .= ' '.$expression;
            foreach ($args['branches'] as $branch) {
                $switch .= " WHEN {$branch['case']} THEN ".$this->getValueForType($branch['then']);
            }
        }
        if (isset($args['default'])) {
            $switch .= ' ELSE '.$this->getValueForType($args['default']);
        }
        return $switch.' END';
    }

    /**
     * @param '%s'|'%d'|'%f'|null $format 某些情况下，条件值类型不一定和列类型一致，因此提供已知格式化类型以跳过解析
     */
    protected function valueFormat($value, $field, $format = null)
    {
        if ($format) {
            $this->formatValues[] = $value;
            return $format;
        }
        if (isset($this->schema)) {
            if (!empty($this->as)) {
                $field = 0 === strpos($field, $this->as.'.') ? substr($field, strlen($this->as) + 1) : $field;
            }
            if (isset($this->schema::FIELDS[$field]['type'])) {
                $format = DB::FORMAT_MAP[$this->schema::FIELDS[$field]['type']];
                $this->formatValues[] = $value;
                return $format;
            }
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_numeric($value)) {
            return $value;
        }
        $this->formatValues[] = $value;
        return '%s';
    }

    protected function getValueForType($value)
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_numeric($value)) {
            return $value;
        }
        return "'{$value}'";
    }

    protected function arrayValueJoin($separator, $value, $format)
    {
        return implode($separator, $format ? array_map([$this, 'getValueForType'], $value) : $value);
    }

    protected function parseDateQuery($column, $args)
    {
        $hasFormat = false !== strpos($column, '%');
        $args['column'] = $hasFormat ? 'zmo_date' : $column;
        $dateQuery = new \WP_Date_Query($args);
        add_filter('date_query_valid_columns', $callback = fn ($columns) => [...$columns, $args['column']]);
        $condition = $dateQuery->get_sql();
        remove_filter('date_query_valid_columns', $callback);
        $condition = $hasFormat ? str_replace('zmo_date', $column, $condition) : $condition;
        $condition = $this->removeLeadingAndOr($condition);
        return $condition;
    }

    protected function removeLeadingAndOr($str)
    {
        $str = trim($str);
        if (0 === stripos($str, 'AND ')) {
            return substr($str, 4);
        } elseif (0 === stripos($str, 'OR ')) {
            return substr($str, 3);
        }
        return $str;
    }

    public function prepareIf($sql)
    {
        return $this->formatValues ? $this->db->prepare($sql, ...$this->formatValues) : $sql;
    }

    /**
     * 复用当前查询条件去获取总记录数.
     */
    public function total()
    {
        $limit = $this->limit;
        $offset = $this->offset;
        $select = $this->select;
        $this->limit = $this->offset = null;
        $this->formatValues = [];
        $total = $this->count();
        $this->limit = $limit;
        $this->offset = $offset;
        $this->select = $select;
        return $total;
    }

    public function count(string $column = '*')
    {
        $this->select = 'COUNT('.$column.')';
        return (int) $this->db->get_var($this->toSelectSQL());
    }

    /**
     * @param string|string[] $columns
     */
    public function get($columns = null)
    {
        isset($columns) && $this->select($columns);
        return $this->db->get_results($this->toSelectSQL());
    }

    /**
     * 总会返回数组.
     *
     * @param  string|string[] $columns
     * @return object[]
     */
    public function findMany($columns = null)
    {
        isset($columns) && $this->select($columns);
        $items = $this->db->get_results($this->toSelectSQL(), \OBJECT);
        return empty($items) ? [] : (array) $items;
    }

    /**
     * @param  string|string[]  $columns
     * @return object|void|null
     */
    public function findFirst($columns = null)
    {
        isset($columns) && $this->select($columns);
        $this->limit = 1;
        return $this->db->get_row($this->toSelectSQL());
    }

    /**
     *  插入一条数据并返回自增ID.
     */
    public function create($data)
    {
        $result = $this->db->query($this->toCreateSQL($data, false));
        return $result > 0 ? $this->db->insert_id : false;
    }

    /**
     * 返回受影响的行数.
     */
    public function createMany($data)
    {
        $this->db->query('START TRANSACTION');

        try {
            $result = $this->db->query($this->toCreateSQL($data, true));
            $this->db->query('COMMIT');
            return $result;
        } catch (\Exception $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * 返回影响的行数，出现错误时返回false.
     */
    public function update($data, $raw = false)
    {
        if (empty($data)) {
            return false;
        }
        return $this->db->query($this->toUpdateSQL($data, $raw));
    }

    /** 通过主键判断是否存在这行数据 */
    public function has($id, $field = 'id')
    {
        $sql = "SELECT 1 FROM `{$this->table}`".(isset($this->as) ? ' AS '.$this->as : '');
        $sql .= ' WHERE '.$field.' = '.$this->valueFormat($id, $field);
        return $this->db->get_var($this->prepareIf($sql)) ? true : false;
    }

    /**
     * 返回影响的行数，出现错误时返回false.
     */
    public function delete($id = null, $field = 'id')
    {
        if (isset($id)) {
            $this->where = [$field => $id];
        }
        return $this->db->query($this->toDeleteSQL());
    }

    /**
     * 获取第一行指定列的值
     *
     * @param string|null $column
     */
    public function firstCol($column = null, $x = 0)
    {
        $this->limit = 1;
        $arr = $this->getCol($column, $x);
        return reset($arr);
    }

    /**
     * 获取多行指定列的值数组.
     *
     * @param string|null $column
     */
    public function getCol($column = null, $x = 0)
    {
        isset($column) && $this->select($column);
        return $this->db->get_col($this->toSelectSQL(), $x);
    }

    /**
     * 返回第一行指定列的值
     *
     * @param string|null $column
     */
    public function getVar($column = null, $x = 0, $y = 0)
    {
        isset($column) && $this->select($column);
        return $this->db->get_var($this->toSelectSQL(), $x, $y);
    }

    public function getError()
    {
        return $this->db->last_error;
    }

    public function hasError()
    {
        return !empty($this->db->last_error);
    }
}
