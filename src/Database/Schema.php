<?php

namespace Suovawp\Database;

/**
 * @template M of object
 * @template I of object
 * @template Q of Query
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

    public static function createQuery()
    {
        return DB::createQuery(static::class);
    }

    /**
     * @param  array|object $row
     * @return M
     */
    public static function buildModel($row)
    {
        if (static::MODEL) {
            $className = static::MODEL;
            return new $className(static::afterGet((array) $row), ['schema' => static::class]);
        }
        return (object) $row;
    }

    /**
     * @param array[]|object[] $rows
     */
    public static function buildModelMany(array $rows)
    {
        $result = [];
        foreach ($rows as $row) {
            $result[] = static::buildModel($row);
        }
        return $result;
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

    public static function has($id)
    {
        return static::createQuery()->has($id, static::ID);
    }

    /**
     * @param QueryOptions $options
     */
    public static function findMany(array $options)
    {
        $data = static::createQuery()->setOptions($options)->findMany();
        return static::buildModelMany($data);
    }

    /**
     * @param QueryOptions $options
     */
    public static function findFirst(array $options)
    {
        $data = static::createQuery()->setOptions($options)->findFirst();
        return $data ? static::buildModel($data) : null;
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
            return static::buildModel($data);
        }
        return $fallback($query);
    }

    /**
     * @param string|int      $id
     * @param string|string[] $columns
     */
    public static function findById($id, $columns = '*')
    {
        $data = static::createQuery()->select($columns)->where([static::ID => $id])->findFirst();
        return $data ? static::buildModel($data) : null;
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
            return static::buildModel($data);
        }
        return $fallback($query);
    }

    /**
     * 未找到时根据条件插入一行数据.
     *
     * @param QueryOptions $options  创建时只能提取键值对相等格式的条件，不能提取复杂条件
     *                               - wpdb的prepare有时可解析数组，但还是通过defaults参数覆盖较好
     * @param array        $defaults 单独提供或写在options中，提供插入的数据，覆盖从条件提取的值
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

    /**
     * 子类覆盖此方法返回默认查询参数.
     */
    public static function getDefaultQueryParams()
    {
        return [];
    }

    /**
     * 子类覆盖此方法实现完全自定义的查询，需返回 `QueryResult` 构造函数接收的数组结构.
     */
    protected static function execQuery(array $params)
    {
        $query = static::createQuery();
        $perPage = $params['per_page'] ?? 12;
        $page = $params['page'] ?? 1;
        $query->page($page, $perPage);
        $where = [];
        foreach (static::FIELDS as $key => $value) {
            if (isset($params[$key])) {
                $where[$key] = $params[$key];
            }
        }
        static::prepareQuery($query, $params, $where);
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

    /**
     * 子类覆盖此方法，根据查询参数修改Query实例，不要进行实际查询.
     *
     * @param Q     $query  查询实例
     * @param array $params 查询参数
     * @param array $where  默认构建的与字段同名参数的相等where条件
     */
    protected static function prepareQuery($query, $params, $where)
    {
        $query->where($where);
    }
}
