<?php

namespace Suovawp\Database;

use Suovawp\Container;

/**
 * @template-covariant  M of object
 * @template-covariant  I of object
 * @template-covariant  Q of Query
 *
 * @phpstan-import-type QueryOptions from Query
 * @phpstan-import-type Wheres from Query as QueryWhere
 *
 * @phpstan-type SchemaField array{type:string,default?:string,comment?:string,optional?:bool,
 *      db?:string,map?:string} 可选的带NOT NULL，db用于覆盖预设，db[sub]可在optional和default之间加自定义子句
 *                              - db字符串语法例子：'PK|type:VARCHAR(125)|default:now()|sub:ON UPDATE CURRENT_TIMESTAMP'
 */
class Schema
{
    public const TABLE = '';

    /** @var array<string,SchemaField> */
    public const FIELDS = [];

    /** 若有多个主键可定义为数组 */
    public const PK = [];

    /** 用于查询的主键 */
    public const ID = 'id';

    public const TIMESTAMP = true;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    /** @var class-string<M> */
    public const MODEL = '';

    /** 自动解析转换JSON数据的字段 */
    public const JSON_FIELDS = [];

    /** 可搜索的字段，query方法解析search参数时使用 */
    public const SEARCH_FIELDS = [];

    /** @var Container */
    protected static $container;

    public static function fullTableName()
    {
        return DB::fullTableName(static::class);
    }

    public static function getDefault()
    {
        $data = [];
        foreach (static::FIELDS as $key => $field) {
            $data[$key] = $field['default'] ?? null;
        }
        return $data;
    }

    public static function getColumns()
    {
        return array_keys(static::FIELDS);
    }

    public static function exists()
    {
        return DB::tableExists(static::fullTableName());
    }

    /**
     * 创建Join数组结构.
     *
     * @template A
     * @template O
     * @param A|string|QueryWhere|null       $as   可以是别名或`on`条件
     * @param O|QueryWhere|null              $on
     * @param 'INNER'|'LEFT'|'RIGHT'|'CROSS' $type
     * @return array{type:string,table:string,
     *  as:($as is array?null:A),on:($as is array?A:(O is null?null:O))}
     */
    public static function createJoin($as = null, $on = null, $type = 'INNER')
    {
        if (isset($as) && !isset($on)) {
            $on = $as;
            $as = null;
        }
        return ['type' => $type, 'table' => DB::fullTableName(static::class), 'as' => $as, 'on' => $on];
    }

    /**
     * @template A
     * @template O
     * @param A|string|QueryWhere|null $as 可以是别名或`on`条件
     * @param O|QueryWhere|null        $on
     * @return array{type:'LEFT',table:string,
     *  as:($as is array?null:A),on:($as is array?A:(O is null?null:O))}
     */
    public static function createLeftJoin($as = null, $on = null)
    {
        return static::createJoin(static::class, $as, $on, 'LEFT');
    }

    /**
     * @template A
     * @template O
     * @param A|string|QueryWhere|null $as 可以是别名或`on`条件
     * @param O|QueryWhere|null        $on
     * @return array{type:'RIGHT',table:string,
     *  as:($as is array?null:A),on:($as is array?A:(O is null?null:O))}
     */
    public static function createRightJoin($as = null, $on = null)
    {
        return static::createJoin(static::class, $as, $on, 'RIGHT');
    }

    /**
     * @template A
     * @template O
     * @param A|string|QueryWhere|null $as 可以是别名或`on`条件
     * @param O|QueryWhere|null        $on
     * @return array{type:'CROSS',table:string,
     *  as:($as is array?null:A),on:($as is array?A:(O is null?null:O))}
     */
    public static function createCrossJoin($as = null, $on = null)
    {
        return static::createJoin(static::class, $as, $on, 'CROSS');
    }

    public static function setContainer(Container $container)
    {
        static::$container = $container;
    }

    /**
     * 获取模型单例，每个主键一个实例.
     *
     * @param  I|int $id
     * @return M
     */
    public static function singleton($id)
    {
        $object = null;
        if (!is_numeric($id)) {
            $object = $id;
            $id = $object->{static::ID};
        }
        $key = static::MODEL.'::'.$id;
        if (static::$container->hasInstance($key)) {
            return static::$container->get($key);
        }
        $model = static::buildSingleton($id, $object);
        $model = $model ?: static::buildModel([], false);
        return static::$container->instance($key, $model);
    }

    /**
     * @param int    $id
     * @param I|null $object
     */
    protected static function buildSingleton($id, $object = null)
    {
        return static::findById($id);
    }

    /**
     * 子类需要覆盖此方法进行具体实例检查.
     *
     * @param I $object
     */
    public static function entityOf($object)
    {
        return is_object($object);
    }

    /**
     * @return M|null 提供的对象为假或非实例时返回null
     */
    public static function entityProxyIf($object, $exists = true)
    {
        if (!$object || !static::entityOf($object)) {
            return null;
        }
        return static::entityProxy($object, $exists);
    }

    /**
     * 此方法纯粹创建实例，不进行传值检查，因此确保传入的对象正确.
     *
     * @param  I    $object
     * @param  bool $exists
     * @return M
     */
    public static function entityProxy($object, $exists = null)
    {
        $className = static::getModel($object);
        $model = new $className([], ['schema' => static::class, 'exists' => $exists ?? (bool) $object]);
        $model->setEntity($object);
        return $model;
    }

    /**
     * @param  I[]  $objects
     * @param  bool $exists
     * @return M[]
     */
    public static function entityProxyMany($objects, $exists = true)
    {
        $result = [];
        foreach ($objects as $object) {
            $result[] = static::entityProxy($object, $exists);
        }
        return $result;
    }

    /**
     * 单纯实例化模型类，不做额外数据转换，可提供初始数据.
     */
    public static function newModel(array $data = [], $exists = false)
    {
        if (static::MODEL) {
            $className = static::MODEL;
            return new $className($data, ['schema' => static::class, 'exists' => $exists]);
        }
        return (object) $data;
    }

    public static function getModel($object)
    {
        return static::MODEL;
    }

    /**
     * 从数据库中的数据创建模型.
     *
     * @param  array|object $row
     * @return M
     */
    public static function buildModel($row, $exists = false)
    {
        $className = static::getModel($row);
        if ($className) {
            return new $className(static::afterGet((array) $row), ['schema' => static::class, 'exists' => $exists]);
        }
        return (object) $row;
    }

    /**
     * @param  array[]|object[] $rows
     * @return M[]
     */
    public static function buildModelMany(array $rows, $exists = false)
    {
        $result = [];
        foreach ($rows as $row) {
            $result[] = static::buildModel($row, $exists);
        }
        return $result;
    }

    public static function getPrimaryKeys()
    {
        if (empty(static::PK)) {
            $keys = [];
            foreach (static::FIELDS as $key => $info) {
                if (isset($info['db']) && false !== strpos($info['db'], 'PK')) {
                    $keys[] = $key;
                }
            }
            return $keys;
        }
        return static::PK;
    }

    public static function createQuery($as = null)
    {
        return DB::createQuery(static::class, $as);
    }

    /**
     * @param string|string[] $columns
     */
    public static function columnExtsts($columns)
    {
        global $wpdb;
        $table = static::fullTableName();
        $column = is_array($columns) ? 'IN ('.implode("','", $columns).')' : "= '{$columns}'";
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$table}' AND COLUMN_NAME {$column}";
        return $wpdb->get_var($sql) > 0;
    }

    /**
     * 指定条件数组.
     *
     * @see Query::where()
     *
     * @param QueryWhere $where 键值对条件数组
     *                          - 数组键是列名或特定操作，值是条件的值
     *                          - 默认是 AND 逻辑，`$or => array` 用于指定 OR 逻辑
     *                          - 支持多层嵌套
     *                          - 操作符：$eq,$ne,$lt,$lte,$gt,$gte,$in,$nin,$between,$regex,$like,$startsWith,$endsWith,$contains
     *                          - 逻辑：$or,$and
     *                          - 其他：$exists,$switch,$date
     *                          - $date使用WP_Date_Query。用$date做字段时，若能从模式获得CREATED_AT，则自动使用CREATED_AT字段，否则无效
     */
    public static function where(array $where)
    {
        return static::createQuery()->where($where);
    }

    /** 通过主键或指定键判断是否存在这行数据 */
    public static function has($id, $field = null)
    {
        return static::createQuery()->has($id, $field ?? static::ID);
    }

    /**
     * @param  QueryOptions $options
     * @return M[]
     */
    public static function findMany(array $options)
    {
        $data = static::createQuery()->setOptions($options)->findMany();
        return static::buildModelMany($data, true);
    }

    /**
     * @param  QueryOptions $options
     * @return M|null
     */
    public static function findFirst(array $options)
    {
        $data = static::createQuery()->setOptions($options)->findFirst();
        return $data ? static::buildModel($data, true) : null;
    }

    /**
     * 查询最新的一条
     *
     * @param  QueryOptions $options
     * @return M|null
     */
    public static function latestFirst(array $options)
    {
        $options['orderby'][static::CREATED_AT] = 'DESC';
        return static::findFirst($options);
    }

    /**
     * @template T
     * @param  QueryOptions      $options
     * @param  \Closure(Query):T $fallback
     * @return M|T
     */
    public static function findFirstOr(array $options, \Closure $fallback)
    {
        $query = static::createQuery();
        $data = $query->setOptions($options)->findFirst();
        if ($data) {
            return static::buildModel($data, true);
        }
        return $fallback($query);
    }

    /**
     * @param  string|int      $id
     * @param  string|string[] $columns
     * @return M|null
     */
    public static function findById($id, $columns = '*')
    {
        $data = static::createQuery()->select($columns)->where([static::ID => $id])->findFirst();
        return $data ? static::buildModel($data, true) : null;
    }

    /**
     * @template T
     * @param  string|int                        $id
     * @param  string|string[]|\Closure(Query):T $columns  不需筛选列时可提供回退回调
     * @param  \Closure(Query):T                 $fallback
     * @return M|T
     */
    public static function findByIdOr($id, $columns, ?\Closure $fallback = null)
    {
        if ($columns instanceof \Closure) {
            $fallback = $columns;
            $columns = '*';
        }
        $query = static::createQuery();
        $data = $query->select($columns)->where([static::ID => $id])->findFirst();
        if ($data) {
            return static::buildModel($data, true);
        }
        return $fallback($query);
    }

    /**
     * 未找到时根据条件插入一行数据.
     *
     * @param  QueryOptions $options  创建时只能提取键值对相等格式的条件，不能提取复杂条件
     *                                - wpdb的prepare有时可解析数组，但还是通过defaults参数覆盖较好
     * @param  array        $defaults 单独提供或写在options中，提供插入的数据，覆盖从条件提取的值
     * @return M|null
     */
    public static function findOrCreate($options, $defaults = [])
    {
        return static::findFirstOr($options, function ($query) use ($options, $defaults) {
            if ($query->hasError()) {
                return null;
            }
            $data = $options['defaults'] ?? $defaults;
            $whereData = $options['where'] ?? [];
            foreach ($whereData as $key => $value) {
                if (!isset($data[$key]) && !is_array($value)) {
                    $data[$key] = $value;
                }
            }
            return static::createAndReturn($data);
        });
    }

    /**
     * 创建数据并返回模型，自动按需添加CREATED_AT.
     * - 如果创建失败返回null，这根据wpdb的insert_id判断.
     *
     * @return M|null
     */
    public static function createAndReturn(array $data)
    {
        $id = static::createQuery()->create(static::beforeSave($data));
        return $id ? static::findById($id) : null;
    }

    /** 创建数据并返回自增ID，自动按需添加CREATED_AT. */
    public static function create(array $data)
    {
        return static::createQuery()->create(static::beforeSave($data));
    }

    /** 创建多条数据并返回影响的行数，自动按需添加CREATED_AT. */
    public static function createMany(array $data)
    {
        return static::createQuery()->createMany(array_map(fn ($d) => static::beforeSave($d), $data));
    }

    /**
     * 更新数据，自动按需添加UPDATED_AT.
     *
     * @param  array{where:QueryWhere,
     *      data:array}|QueryWhere $options 简单条件或完整选项
     * @param  array    $data 前面参数只提供where时，这里提供要更新的数据
     * @return int|bool 返回影响的行数，出现错误时返回false
     */
    public static function update(array $options, ?array $data = null)
    {
        $query = static::createQuery();
        if ($data) {
            $query->where($options);
        } else {
            $data = $options['data'] ?? [];
            unset($options['data']);
            $query->setOptions($options);
        }
        return $query->update(static::beforeSave($data, false));
    }

    /**
     * @param array{update:array,create:array,where:QueryWhere} $options
     */
    public static function upsert(array $options)
    {
        return static::updateOr(
            ['where' => $options['where'], 'data' => $options['update']],
            fn () => static::create($options['create'])
        );
    }

    /**
     * @template T
     * @param  array{data:array,where:QueryWhere}   $options
     * @param  \Closure(array $data,Query $query):T $fallback 回调接收更新失败的数据和查询对象
     * @return int|bool|T
     */
    public static function updateOr($options, \Closure $fallback)
    {
        $data = $options['data'];
        $query = static::createQuery();
        $result = $query->where($options['where'])->update(static::beforeSave($data, false));
        if (false !== $result) {
            return $result;
        }
        return $fallback($data, $query);
    }

    protected static function afterGet($data)
    {
        foreach (static::JSON_FIELDS as $field) {
            if (isset($data[$field]) && !is_array($data[$field])) {
                $data[$field] = $data[$field] ? json_decode($data[$field], true) : [];
            }
        }
        return $data;
    }

    protected static function beforeSave($data, $isCreate = true)
    {
        if (static::TIMESTAMP) {
            $data = $isCreate ? static::withCreatedTimestamp($data) : static::withUpdatedTimestamp($data);
        }
        foreach (static::JSON_FIELDS as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }
        return $data;
    }

    protected static function withCreatedTimestamp($data)
    {
        if (static::CREATED_AT && isset(static::FIELDS[static::CREATED_AT])) {
            $data[static::CREATED_AT] = current_time('mysql');
        }
        return $data;
    }

    protected static function withUpdatedTimestamp($data)
    {
        if (static::UPDATED_AT && isset(static::FIELDS[static::UPDATED_AT])) {
            $data[static::UPDATED_AT] = current_time('mysql');
        }
        return $data;
    }

    /**
     * 返回影响的行数，出现错误时返回false.
     */
    public static function delete($id)
    {
        return static::createQuery()->delete($id, static::ID);
    }

    /**
     * 子类覆盖此方法返回默认查询参数.
     */
    public static function getDefaultQueryParams()
    {
        return [
            'page'     => 1,
            'per_page' => 12,
        ];
    }

    protected static function paramMapFilter(array $map)
    {
        return $map;
    }

    /**
     * @return queryResult<M,I,Q,P>
     */
    public static function wrapQuery($query, $params = [], $args = [])
    {
    }

    /**
     * 自定义参数查询，适合匹配前端的查询参数.
     *
     * @template P
     * @param  P|array              $params 查询参数，一般设计和api接口一致
     *                                      - 不直接使用 QueryOptions
     *                                      - Schema 默认识别与字段同名的参数，构建相等where条件
     *                                      - 其他参数需要通过覆盖 `prepareQuery` 方法解析并构建查询
     * @return queryResult<M,I,Q,P> 返回标准化查询结果对象
     */
    final public static function query(array $params)
    {
        $result = static::execQuery($params + static::getDefaultQueryParams());
        return new QueryResult($result, static::class);
    }

    /** 支持通过query参数查询总量 */
    public static function queryTotal(array $params)
    {
        $params = static::prepareQueryParams($params + static::getDefaultQueryParams());
        $query = static::createQuery();
        static::prepareQuery($query, $params);
        return $query->total();
    }

    /**
     * 子类覆盖此方法实现完全自定义的查询，需返回 `QueryResult` 构造函数接收的数组结构.
     */
    protected static function execQuery(array $params)
    {
        $params = static::prepareQueryParams($params);
        $perPage = $params['per_page'];
        $page = $params['page'];
        $query = static::createQuery()->page($page, $perPage);
        static::prepareQuery($query, $params);
        $items = $query->findMany();
        return [
            'query'          => $query,
            'items'          => $items,
            'per_page'       => $perPage,
            'page'           => $page,
            'total_callback' => [$query, 'total'],
            'params'         => $params,
        ];
    }

    protected static function prepareQueryParams(array $params)
    {
        foreach (static::paramMapFilter(['s' => 'search']) as $key => $newKey) {
            if (isset($params[$key])) {
                $params[$newKey] = $params[$key];
                unset($params[$key]);
            }
        }
        return $params;
    }

    /**
     * 子类覆盖此方法，根据查询参数修改Query实例，不要进行实际查询.
     *
     * @param Q     $query  查询实例
     * @param array $params 经过`map`转换后的查询参数
     */
    protected static function prepareQuery($query, array $params)
    {
        return $query->setOptions(static::paramsToBaseQueryOptions($params));
    }

    /**
     * 把查询参数转化为 `QueryOptions` 数组。
     * 目前存在某些条件或排序时会使用join，因此需要指定表名前缀；
     * 查询仅允许筛选主表列，因此直接加前缀；.
     *
     * @param array        $params       实现了通用解析：
     *                                   - 所有列自动加指定的表别名前缀
     *                                   - 条件：与列同名参数变成条件数组，数组`created_at和updated_at`解析为`date_query`条件
     *                                   - 分页：`per_page`和`page`参数
     *                                   - 排序: `orderby`和`order`参数
     *                                   - - 有CREATED_AT时默认按新到旧排序
     *                                   - - `date`解析为CREATED_AT字段；`old`时间相反
     *                                   - 搜索：`search`参数为模糊搜索，需指定搜索的列名
     *                                   - 选择：解析`fields`参数，若已提供`select`选项则不解析
     * @param string       $pre          表名前缀带点号
     * @param QueryOptions $options      指定已有的选项，将在基础上追加
     * @param array        $searchFields 扩展要搜索的列，此组不自动加前缀
     * @return array{where?:array,
     * orderby?:array,select:array}
     */
    protected static function paramsToBaseQueryOptions(array $params, $pre = '', $options = [], $searchFields = [])
    {
        $select = ['*'];
        foreach (static::FIELDS as $key => $value) {
            if (isset($params[$key])) {
                $whereValue = $params[$key];
                if (is_array($whereValue) && ($key === static::CREATED_AT || $key === static::UPDATED_AT)) {
                    $whereValue = ['$date' => $whereValue];
                }
                $options['where'][$pre.$key] = $whereValue;
                continue;
            }
            if ('fields' == $key) {
                $select = is_array($value) ? $value : [$value];
            }
        }
        $options['select'] ??= $pre ? array_map(fn ($field) => $pre.$field, $select) : $select;
        if (!empty($params['search'])) {
            $search = $params['search'];
            $conditions = [];
            foreach (static::SEARCH_FIELDS as $field) {
                $conditions[$pre.$field] = ['$contains' => $search];
            }
            foreach ($searchFields as $field) {
                $conditions[$field] = ['$contains' => $search];
            }
            if ($conditions) {
                $conditions['$relation'] = 'OR';
                if (isset($options['where']['$and'])) {
                    $options['where']['$and'][] = $conditions;
                } else {
                    $options['where']['$and'] = [$conditions];
                }
            }
        }
        if (!empty($params['orderby'])) {
            $orderby = is_array($params['orderby']) ? $params['orderby'] : [$params['orderby'] => $params['order'] ?? 'desc'];
            foreach ($orderby as $by => $sort) {
                if ('old' == $by) {
                    $by = 'date';
                    $sort = 'asc' == $sort ? 'desc' : 'asc';
                }
                $by = 'date' == $by ? static::CREATED_AT : $by;
                $options['orderby'][$pre.$by] = $sort;
            }
        } elseif (static::CREATED_AT) {
            $options['orderby'] = [$pre.static::CREATED_AT => 'desc'];
        }
        return $options;
    }

    protected static function appendSearchWhere(array &$options, array $columns, $search, $pre = '')
    {
        $conditions = [];
        foreach ($columns as $column) {
            $conditions[$pre.$column] = ['$contains' => $search];
        }
        $conditions['$relation'] = 'OR';
        if (isset($options['where']['$and'])) {
            $options['where']['$and'][] = $conditions;
        } else {
            $options['where']['$and'] = [$conditions];
        }
    }

    /** 打印输出即将执行的SQL语句，用于调试 */
    public static function dumpSQL($callback = null)
    {
        $func = fn ($query) => function_exists('dump') ? dump($query) : var_dump($query);
        add_filter('query', $func);
        $cancel = fn () => remove_filter('query', $func);
        if ($callback) {
            $callback();
            $cancel();
            return;
        }
        return $cancel;
    }

    /**
     * @return int|string|false 返回主键值，失败返回false
     */
    public static function save(array $data, $pk = null)
    {
        $pk ??= static::ID;
        $pkValue = $data[$pk] ?? null;
        unset($data[$pk]);
        if (!$pkValue || !static::has($pkValue, $pk)) {
            return static::create($data);
        }
        $result = static::update([
            'where' => [$pk => $pkValue],
            'data'  => $data,
        ]);
        if ($result > 0) {
            return $pkValue;
        }
        return static::has($pkValue, $pk) ? $pkValue : static::create($data);
    }
}
