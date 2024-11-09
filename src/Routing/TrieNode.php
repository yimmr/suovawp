<?php

namespace Suovawp\Routing;

class TrieNode
{
    /** @var array<string,static> */
    public $children = [];

    public $handler;

    public $id;

    public $param;

    public $callback;

    /**
     * 路由带斜杠末尾时为True，请求路径带斜杠时会取反，即：
     * - 路由和请求路径都带斜杠末尾时，不建议重定向
     * - 存在不一致后缀时建议重定向.
     */
    public $tsr = false;

    public $hasParamChild = false;

    /** @var string[] */
    public $paramKeys = [];

    /** @var string[] */
    public $optionalKeys = [];

    /** @var array<string,static> */
    public $wildcardKeys = [];

    public $optional = false;

    public $ref;
}
