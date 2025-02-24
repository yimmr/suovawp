<?php

namespace Suovawp\Utils;

/**
 * @phpstan-type Result array{name:string,type:string,class:string|null,default:mixed,parameter:\ReflectionParameter}[]
 */
class ArgumentResolver
{
    /** @var array<string,Result> */
    private array $cache = [];

    /** @var self */
    private static $instance;

    private const MAX_CACHE_SIZE = 1000;

    /**
     * 单例模式解析，此方式可全局缓存.
     */
    public static function parse($callable)
    {
        return self::i()->getParametersWithCache($callable);
    }

    public static function parseNotCache($callable)
    {
        return self::i()->getParameters($callable);
    }

    /**
     * 构建一个类的实例.
     *
     * @template T
     * @param  class-string<T>                             $className 类名
     * @param  (callable(class-string,static):object)|null $make      可选回调用于构建类依赖，若未提供直接new
     * @param  array                                       $params    参数列表
     * @return T
     */
    public function build($className, $make = null, $params = [])
    {
        $reflector = new \ReflectionClass($className);
        if (!$reflector->isInstantiable()) {
            throw new \Exception("{$className} is not instantiable");
        }
        $constructor = $reflector->getConstructor();
        if (!$constructor) {
            return new $className();
        }
        return $reflector->newInstanceArgs($this->buildArgs(
            $constructor->getParameters(), $make, $params, "{$className}::__construct()"));
    }

    /**
     * @template T
     * @param  callable():T                                $callback 执行的回调
     * @param  (callable(class-string,static):object)|null $make     可选回调用于构建类依赖，若未提供直接new
     * @param  array                                       $params   参数列表
     * @return T
     */
    public function call($callback, $make = null, $params = [])
    {
        $reflection = $this->getReflection($callback);
        $parameters = $reflection->getParameters();
        return call_user_func_array(
            $callback,
            $this->buildArgs($parameters, $make, $params, $reflection->getName())
        );
    }

    /**
     * @param \ReflectionParameter[]                      $parameters
     * @param (callable(class-string,static):object)|null $make       可选回调用于构建类依赖，若未提供直接new
     */
    public function buildArgs(array $parameters, $make, array $params = [], string $funcname = '')
    {
        $args = [];
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            } elseif ($class = $this->getParameterClassName($parameter, $parameter->getType())) {
                $args[] = isset($make) ? $make($class, $this) : new $class();
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } elseif ($parameter->isOptional()) {
                $args[] = null;
            } else {
                $length = count($args);
                throw new \InvalidArgumentException("ArgumentResolver build error: Too few arguments to function $funcname, {$length} passed");
            }
        }
        return $args;
    }

    /**
     * @param  callable|string|object $callable
     * @return Result
     */
    public function getParameters($callable)
    {
        $result = [];
        $reflection = $this->getReflection($callable);
        if ($reflection instanceof \ReflectionClass) {
            $reflection = $reflection->getConstructor();
        }
        if (!$reflection) {
            return $result;
        }
        $parameters = $reflection->getParameters();
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            $result[] = [
                'name'      => $parameter->getName(),
                'type'      => $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed',
                'class'     => $this->getParameterClassName($parameter, $type),
                'parameter' => $parameter,
            ];
        }
        return $result;
    }

    protected function getParameterClassName(\ReflectionParameter $parameter, $type)
    {
        if (!($type instanceof \ReflectionNamedType) || $type->isBuiltin()) {
            return null;
        }
        $name = $type->getName();
        if ('self' === $name) {
            return ($class = $parameter->getDeclaringClass()) ? $class->getName() : null;
        }
        if ('parent' === $name) {
            $class ??= $parameter->getDeclaringClass();
            return ($class && ($parent = $class->getParentClass())) ? $parent->getName() : null;
        }
        return $name;
    }

    /**
     * @param callable|string|object $callable
     */
    public function getParametersWithCache($callable)
    {
        $key = $this->getCacheKey($callable);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $result = $this->getParameters($callable);
        if (count($this->cache) >= self::MAX_CACHE_SIZE) {
            array_shift($this->cache);
        }
        $this->cache[$key] = $result;
        return $result;
    }

    /**
     * @param callable|string|object $callable
     */
    private function getCacheKey($callable)
    {
        if (is_string($callable)) {
            return $callable;
        }
        if (is_array($callable)) {
            return is_object($callable[0])
                ? get_class($callable[0]).'::'.$callable[1]
                : $callable[0].'::'.$callable[1];
        }
        if (is_object($callable)) {
            return get_class($callable);
        }
        if ($callable instanceof \Closure) {
            return spl_object_hash($callable);
        }
        return serialize($callable);
    }

    /**
     * @param callable|string|object $callable
     */
    private function getReflection($callable)
    {
        if (is_string($callable) && class_exists($callable)) {
            return new \ReflectionClass($callable);
        }
        if (is_array($callable)) {
            return new \ReflectionMethod($callable[0], $callable[1]);
        }
        if ($callable instanceof \Closure) {
            return new \ReflectionFunction($callable);
        }
        if (is_object($callable)) {
            return new \ReflectionClass($callable);
        }
        return new \ReflectionFunction($callable);
    }

    public function removeCache($callable)
    {
        unset($this->cache[$this->getCacheKey($callable)]);
    }

    public function flush()
    {
        $this->cache = [];
    }

    public static function i()
    {
        return self::$instance ??= new self();
    }
}
