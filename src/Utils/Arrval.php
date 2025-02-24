<?php

namespace Suovawp\Utils;

/**
 * @template V
 *
 * @property int $length
 */
class Arrval implements \ArrayAccess
{
    use ArrAccessTrait;

    protected $value;

    /**
     * @param V|array $value
     */
    public function __construct(array $value)
    {
        $this->value = $value;
    }

    public function __get($name)
    {
        if ('length' === $name) {
            return count($this->value);
        }
        return null;
    }

    public function copy()
    {
        return clone $this;
    }

    /**
     * 所有元素通过回调测试时返回 true.
     *
     * @param callable(V[key-of<V>],key-of<V>): bool $callback
     */
    public function every(callable $callback): bool
    {
        foreach ($this->value as $k => $v) {
            if (!$callback($v, $k)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 至少有一个元素通过回调测试时返回 true.
     *
     * @param callable(V[key-of<V>],key-of<V>): bool $callback
     */
    public function some(callable $callback): bool
    {
        foreach ($this->value as $k => $v) {
            if ($callback($v, $k)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @template T
     * @param callable(V[key-of<V>],key-of<V>): T $callback
     * @return static<key-of<V>,T>
     */
    public function map(callable $callback)
    {
        $newArray = [];
        foreach ($this->value as $k => $v) {
            $newArray[$k] = $callback($v, $k);
        }
        return new static($newArray);
    }

    /**
     * @param callable(V[key-of<V>],?key-of<V>): bool $callback
     * @return static
     */
    public function filter(callable $callback, int $mode = 0)
    {
        return new static(array_filter($this->value, $callback, $mode));
    }

    /**
     * @template T
     * @param  callable(T,V[key-of<V>]): T $callback
     * @param  T $initial
     * @return T
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->value, $callback, $initial);
    }

    /**
     * @param callable(V[key-of<V>],key-of<V>,static): bool $callback
     */
    public function forEach($callback)
    {
        foreach ($this->value as $name => $value) {
            $callback($value, $name, $this);
        }
    }

    public function includes($searchElement, bool $strict = false)
    {
        return in_array($searchElement, $this->value, $strict);
    }

    public function indexOf($searchElement, bool $strict = false)
    {
        return array_search($searchElement, $this->value, $strict);
    }

    public function join($separator = ',')
    {
        return implode($separator, $this->value);
    }

    public function toArray()
    {
        return $this->value;
    }
}
