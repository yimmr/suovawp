<?php

namespace Suovawp\Database;

/**
 * @template Model
 * @template Item
 * @template Query
 * @template Params
 *
 * @property int    $page
 * @property int    $perPage
 * @property int    $total
 * @property int    $pages
 * @property Item[] $items
 * @property Query  $query
 */
class QueryResult
{
    /** @var Model[] */
    protected $models;

    /** @var Item[] */
    protected $items = [];

    /** @var int|null */
    protected $page;

    /** @var int|null */
    protected $perPage;

    /** @var int|null */
    protected $total;

    /** @var int|null */
    protected $pages;

    /** @var Params */
    protected $params;

    /** @var array|null */
    protected $wpQueryArgs;

    /** @var Query */
    protected $query;

    protected $totalCallback;

    protected $basePaginateArgs = [];

    protected $schema;

    /**
     * @param array{items:Item[],page:int,per_page:int,query:Query,params:array,pages?:int,
     * total?:int,total_callback?:callable():int,wp_query_args?:array} $props
     * @param class-string<Schema> $schema
     */
    public function __construct($props = [], $schema = null)
    {
        $this->query = $props['query'];
        $this->items = $props['items'];
        $this->page = $props['page'];
        $this->perPage = $props['per_page'];
        $this->pages = $props['pages'] ?? null;
        $this->total = $props['total'] ?? null;
        $this->totalCallback = $props['total_callback'] ?? null;
        $this->wpQueryArgs = $props['wp_query_args'] ?? null;
        $this->params = $props['params'];
        $this->schema = $schema;
    }

    public function __get($name)
    {
        if ('total' == $name) {
            return $this->total();
        }
        if ('pages' == $name) {
            return $this->getPages();
        }
        return $this->$name;
    }

    public function toArray()
    {
        return [
            'items'    => $this->items,
            'page'     => $this->page,
            'per_page' => $this->perPage,
            'total'    => $this->total(),
            'pages'    => $this->getPages(),
        ];
    }

    public function toArrayWithModel()
    {
        return [
            'items'    => $this->items,
            'page'     => $this->page,
            'per_page' => $this->perPage,
            'total'    => $this->total(),
            'pages'    => $this->getPages(),
            'models'   => $this->getModels(),
        ];
    }

    /**
     * @return Model[]
     */
    public function getModels()
    {
        if (isset($this->models)) {
            return $this->models;
        }
        if (!$this->schema) {
            return $this->models = [];
        }
        if ($this->wpQueryArgs) {
            return $this->models = $this->schema::entityProxyMany($this->items, true);
        }
        return $this->models = $this->schema::buildModelMany($this->items, true);
    }

    public function getItems()
    {
        return $this->items;
    }

    public function isEmpty()
    {
        return empty($this->items);
    }

    public function total()
    {
        return $this->total ??= (int) call_user_func($this->totalCallback);
    }

    public function getPages()
    {
        return $this->pages ??= (int) ceil($this->total() / max(1, $this->perPage));
    }

    public function getParams()
    {
        return $this->params;
    }

    public function param($key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function getParam($key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function getWPQueryArgs()
    {
        return $this->wpQueryArgs;
    }

    /**
     * 设置基本分页参数.
     *
     * @param array<string,mixed> $args
     */
    public function setBasePaginateArgs($args)
    {
        $this->basePaginateArgs = $args;
        return $this;
    }

    /**
     * 创建WP分页函数的所需参数.
     *
     * @param mixed $
     * @param array<string,mixed> $args 额外自定义参数
     *                                  - param 页码查询参数名
     */
    public function getPaginateArgs($args = [])
    {
        $args += $this->basePaginateArgs;
        $args['total'] = $this->getPages();
        $args['current'] = $this->page;
        return $args;
    }

    public function getPaginateArgsForAjax($args = [])
    {
        if (!array_key_exists('base', $args)) {
            $url = wp_get_referer() ?: home_url();
            $args['base'] = trailingslashit(explode('?', $url)[0]).'%_%';
            $args += [
                'param'       => 'paged',
                'remove_args' => ['action'],
            ];
        }
        return $this->getPaginateArgs($args);
    }
}
