<?php

namespace Suovawp\Database;

use Suovawp\Utils\Arr;

/**
 * 内置过滤SQL钩子
 * - users pre_user_query(\WP_User_Query)
 * - posts posts_clauses($clauses,\WP_Query) posts_orderby/posts_where($str,\WP_Query).
 * - terms terms_clauses($clauses,\WP_Term_Query).
 * - comments comments_clauses($clauses,\WP_Comment_Query).
 */

/**
 * @template A of array
 * @template T of 'posts'|'users'|'terms'|'comments'
 *
 * @phpstan-type QueryMap array{posts:\WP_Query,users:\WP_User_Query,terms:A,comments:\WP_Comment_Query}
 * @phpstan-type SQLClauses array{where:string,groupby:string,join:string,orderby:string,distinct:string,fields:string,limits:string}
 */
class WPQueryRefiner
{
    /** @var A|array */
    protected $args;

    /** @var T */
    protected $type;

    /** @var array<string,callable(SQLClauses|QueryMap[T],QueryMap[T]|string):SQLClauses|string> */
    protected $hooks = [];

    protected $hookOnce = true;

    protected $currentClausesHook;

    /**
     * @param A $args
     * @param T $type
     */
    public function __construct($args = [], $type = 'posts')
    {
        $this->args = $args;
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * 根据键名映射转换参数的键名.
     *
     * @param array<string,string> $map
     */
    public function map($map)
    {
        foreach ($map as $key => $newKey) {
            if (isset($this->args[$key])) {
                $this->args[$newKey] = $this->args[$key];
                unset($this->args[$key]);
            }
        }
        return $this;
    }

    /**
     * 转换参数.
     *
     * @template R
     *
     * @param callable(A $args):R $transformer
     *
     * @return static<R,T>
     */
    public function transform($transformer)
    {
        $this->args = $transformer($this->args);
        return $this;
    }

    /** 浅层覆盖 */
    public function override(array $override)
    {
        $this->args = $override + $this->args;
        return $this;
    }

    /**
     * @param callable(string $by,string $sort,A &$args):array{string,string} $converter 回调必须返回[$by,$sort]数组，可按需修改$args
     * @param (callable(string $by,string $sort?):string)|null $builder 创建SQL排序column的回调，未匹配请返回空字符
     * @param string[] $keys 未匹配转换或者存在这些排序键时，使用回调构建SQL排序列
     */
    public function sort($converter, $builder = null, $keys = [])
    {
        if (isset($this->args['orderby'])) {
            $order = $this->args['order'] ?? 'ASC';
            $orderby = $this->args['orderby'];
            if (is_array($orderby)) {
                $newOrderby = [];
                foreach ($orderby as $oldBy => $oldSort) {
                    [$by,$sort] = $converter($oldBy, $oldSort, $this->args);
                    $newOrderby[$by] = $sort;
                }
                $this->args['orderby'] = $newOrderby;
                $needFallback = isset($builder) && Arr::some($keys, fn ($k) => isset($orderby[$k]));
            } else {
                [$by,$sort] = $converter($orderby, $order, $this->args);
                $this->args['orderby'] = $by;
                $this->args['order'] = $sort;
                $needFallback = isset($builder) && in_array($orderby, $keys);
            }
            if ($needFallback) {
                $this->hooks['orderby'] = $builder;
            }
        }
        return $this;
    }

    /**
     * @param callable(string $where,QueryMap[T] $query):string $filter 回调必须返回条件。$where已去除前部分WHERE关键字并自动还原
     * @param string[] $keys 存在这些参数键时，使用回调过滤SQL条件
     */
    public function where($filter, $keys = [])
    {
        foreach ($keys as $key) {
            if (isset($this->args[$key])) {
                $this->hooks['where'] = $filter;
                break;
            }
        }
        return $this;
    }

    /**
     * @param callable(SQLClauses $clauses,QueryMap[T] $query):$clauses|void $filter 回调返回过滤后的`$clauses`。特例：users查询是修改Query，没有`$clauses`
     * @param array $keys 存在这些参数键时，使用回调过滤SQL
     */
    public function clauses($filter, $keys = [])
    {
        foreach ($keys as $key) {
            if (isset($this->args[$key])) {
                $this->hooks['clauses'] = $filter;
                break;
            }
        }
        return $this;
    }

    public function getArgs()
    {
        if (!empty($this->hooks)) {
            if ('users' == $this->type) {
                $this->usersHooks();
            } else {
                $this->clausesHooks([$this, 'sqlClausesFilter'], 10, 'terms' == $this->type ? 3 : 2);
            }
        }
        return $this->args;
    }

    protected function clausesHooks($filter, $priority = 10, $acceptedArgs = 1)
    {
        add_filter($this->currentClausesHook = $this->type.'_clauses', $filter, $priority, $acceptedArgs);
    }

    protected function usersHooks()
    {
        add_filter('pre_user_query', [$this, 'usersSQLFilter']);
    }

    /**
     * @param SQLClauses $clauses
     * @param QueryMap[T]|string[] $query terms查询是分类法数组
     * @param QueryMap['terms'] $args terms查询特例参数
     */
    public function sqlClausesFilter($clauses, $query, $args = [])
    {
        $qry = 'terms' == $this->type ? $args : $query;
        if (isset($this->hooks['where'])) {
            $clauses['where'] = $this->autoKeepSQLKeywordPrefix('WHERE ', $clauses['where'],
                fn ($w) => $this->hooks['where']($w, $qry));
        }
        if (isset($this->hooks['orderby'])) {
            $clauses['orderby'] = $this->autoKeepSQLKeywordPrefix('ORDER BY ', $clauses['orderby'],
                fn ($o) => $this->sqlOrderbyFilter($o, $qry));
        }
        if (isset($this->hooks['clauses'])) {
            $clauses = $this->hooks['clauses']($clauses, $qry);
        }
        if ($this->hookOnce) {
            remove_filter($this->currentClausesHook, [$this, 'sqlClausesFilter']);
        }
        return $clauses;
    }

    /**
     * @param \WP_User_Query $query
     */
    public function usersSQLFilter($query)
    {
        if (isset($this->hooks['where'])) {
            $query->query_where = $this->autoKeepSQLKeywordPrefix('WHERE ', $query->query_where,
                fn ($w) => $this->hooks['where']($w, $query));
        }
        if (isset($this->hooks['orderby'])) {
            $query->query_orderby = $this->autoKeepSQLKeywordPrefix('ORDER BY ', $query->query_orderby,
                fn ($o) => $this->sqlOrderbyFilter($o, $query));
        }
        if (isset($this->hooks['clauses'])) {
            $this->hooks['clauses']([], $query);
        }
        if ($this->hookOnce) {
            remove_filter('pre_user_query', [$this, 'usersSQLFilter']);
        }
    }

    protected function autoKeepSQLKeywordPrefix($prefix, $sql, $callback)
    {
        $hasPrefix = 0 === strpos($sql, $prefix);
        $sql = $callback($hasPrefix ? substr($sql, strlen($prefix)) : $sql);
        return $hasPrefix ? $prefix.$sql : $sql;
    }

    /**
     * @param string $orderby
     * @param QueryMap[T]|mixed $query
     */
    protected function sqlOrderbyFilter($orderby, $query)
    {
        $query = is_array($query) ? $query : $query->query_vars;
        return $this->rebuildSQLOrderby($orderby, $query['orderby'] ?? '', $query['order'] ?? 'asc');
    }

    /**
     * @param string                      $sql     原始ORDER BY
     * @param string|array<string,string> $orderby
     * @param string                      $sort
     */
    public function rebuildSQLOrderby($sql, $orderby, $sort = 'asc')
    {
        $sort = strtoupper($sort);
        $builder = $this->hooks['orderby'];
        if (is_array($orderby)) {
            $sqlraw = $sql ? explode(', ', $sql) : [];
            $newsql = [];
            foreach ($orderby as $by => $sort) {
                if ($subsql = $builder($by, $sort)) {
                    $newsql[] = $subsql;
                } elseif (!empty($sqlraw)) {
                    $newsql[] = array_shift($sqlraw);
                }
            }
            $sql = implode(', ', empty($sqlraw) ? $newsql : array_merge($newsql, $sqlraw));
        } else {
            $subsql = $builder($orderby, $sort);
            $sql = $subsql ? $subsql.($sql ? ', '.$sql : '') : $sql;
        }
        return $sql;
    }
}
