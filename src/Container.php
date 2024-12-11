<?php

namespace Suovawp;

use Suovawp\Utils\ArgumentResolver;

class Container
{
    /** @var array<string,string|\Closure> */
    private $bindings = [];

    /** @var array<string,object> */
    private $instances = [];

    /** @var array<string,string> */
    private $alias = [];

    /**
     * 返回容器中的实例。遇到未解析的id时先创建实例并缓存.
     *
     * @template T
     * @param  class-string<T>|string $id
     * @return T
     */
    public function get(string $id)
    {
        if (isset($this->instances[$id])
            || isset($this->instances[$id = $this->findBindedIdOrThrow($id)])
        ) {
            return $this->instances[$id];
        }
        $instance = $this->resolve($this->bindings[$id]);
        $this->instances[$id] = $instance;
        return $instance;
    }

    /**
     * 判断id是否已注册或存在关联的实例.
     *
     * @param class-string|string $id
     */
    public function has(string $id)
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || isset($this->alias[$id]);
    }

    /**
     * id注册绑定到类名或闭包，会在获取时解析.
     *
     * @template O of object
     * @param class-string|string                  $id
     * @param class-string|\Closure(static):O|null $concrete 可选提供闭包工厂或指向另一个类
     * @param string|null                          $alias    可选设置别名
     */
    public function bind(string $id, $concrete = null, $alias = null)
    {
        $this->bindings[$id] = $concrete ?? $id;
        if (isset($alias)) {
            $this->alias[$alias] = $id;
        }
        return $this;
    }

    /** 设置别名，希望接口或任意名称指向一个类时很有用 */
    public function alias(string $alias, string $id)
    {
        $this->alias[$alias] = $id;
        return $this;
    }

    /**
     * 根据已注册的id创建新实例，不会重新构建已缓存的依赖项.
     *
     * @template T
     * @param  class-string<T>|string $id
     * @param  array                  $params
     * @return T
     */
    public function make($id, $params = [])
    {
        $id = $this->findBindedIdOrThrow($id);
        return $this->resolve($this->bindings[$id], $params);
    }

    /**
     * @template T
     * @template O of object
     * @param  class-string<T>|\Closure(static):O $concrete
     * @return T|O
     */
    protected function resolve($concrete, $params = [], $method = 'get')
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }
        return ArgumentResolver::i()->build($concrete, fn ($id) => $this->{$method}($id), $params);
    }

    /**
     * id关联实例，后续可直接获取.
     *
     * @template O
     * @param  class-string|string $id
     * @param  O                   $instance
     * @return O
     */
    public function instance(string $id, object $instance)
    {
        $this->instances[$id] = $instance;
        return $instance;
    }

    /** 是否有对应单例 */
    public function hasInstance(string $id)
    {
        return isset($this->instances[$id]);
    }

    /**
     * 简单获取类的单例，可选提供一个依赖，没有反射开销
     *
     * @template T
     * @template O
     * @param  class-string<T>        $class
     * @param  O                      $ctx   仅首次实例化时传递的任意值
     * @return (O is null ? T : T<O>)
     */
    public function singleton(string $class, $ctx = null)
    {
        return $this->instances[$class] ??= (isset($ctx) ? new $class($ctx) : new $class());
    }

    /**
     * 实时绑定的单例模式，不存在单例时通过容器实例化类，实现依赖注入，可选动态注册别名.
     *
     * @template T
     * @param  class-string<T> $class
     * @return T
     */
    public function getSingleton(string $class, ?string $alias = null)
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }
        if ($alias) {
            $this->alias($alias, $class);
        }
        return $this->instances[$class] = $this->resolve($class, [], 'getSingleton');
    }

    /**
     * @template T
     * @param  (callable():T)|string $callback 支持类名和非静态方法名数组。所有类和依赖必须先在容器注册
     * @return T
     */
    public function call($callback, array $params = [])
    {
        if (is_array($callback) && is_string($callback[0])) {
            $callback[0] = $this->get($callback[0]);
        }
        return ArgumentResolver::i()->call($callback, fn ($class) => $this->get($class), $params);
    }

    public function getBindings()
    {
        return $this->bindings;
    }

    public function getInstances()
    {
        return $this->instances;
    }

    /** 移除已注册的id */
    public function unbind(string $id)
    {
        unset($this->bindings[$id]);
    }

    /** 移除id缓存的实例 */
    public function remove(string $id)
    {
        unset($this->instances[$id]);
    }

    /** 清空所有内容 */
    public function flush()
    {
        $this->bindings = [];
        $this->instances = [];
    }

    protected function getConcrete($id)
    {
        return $this->bindings[$id] ?? (isset($this->alias[$id]) ? $this->getConcrete($this->alias[$id]) : null);
    }

    protected function findBindedIdOrThrow($id)
    {
        if (isset($this->bindings[$id])) {
            return $id;
        }
        if (isset($this->alias[$id])) {
            return $this->findBindedIdOrThrow($this->alias[$id]);
        }
        throw new \InvalidArgumentException("Unable to resolve '{$id}': binding not found in container", \E_ERROR);
    }
}
