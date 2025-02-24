<?php

namespace Suovawp\Utils;

/**
 * @template U of array
 */
class URLSearchParams implements \IteratorAggregate
{
    /** @var (U is string ? array : U)|array */
    private $params = [];

    /**
     * @param string|U $init 数组或字符串，约定：
     *                       - 字符串解析：`param[]` 格式的参数名解析为数组，多个追加；其他同名参数依次覆盖
     *                       - 数组结构应符合字符串约定
     */
    public function __construct($init = '')
    {
        if (is_string($init)) {
            parse_str(ltrim($init, '?'), $this->params);
        } elseif (is_array($init)) {
            $this->params = $init;
        }
    }

    public function append(string $name, string $value)
    {
        if (!isset($this->params[$name])) {
            $this->params[$name] = $value;
        } elseif (is_array($this->params[$name])) {
            $this->params[$name][] = $value;
        } else {
            $this->params[$name] = [$this->params[$name], $value];
        }
    }

    public function delete(string $name)
    {
        unset($this->params[$name]);
    }

    public function get(string $name)
    {
        $value = $this->params[$name] ?? null;
        if (is_array($value)) {
            return $value[0];
        }
        return $value;
    }

    public function getAll(string $name)
    {
        if (isset($this->params[$name])) {
            $value = $this->params[$name];
            return is_array($value) ? $value : [$value];
        }
        return [];
    }

    public function has(string $name)
    {
        return isset($this->params[$name]);
    }

    public function set(string $name, string $value)
    {
        $this->params[$name] = $value;
    }

    public function sort()
    {
        ksort($this->params);
    }

    public function keys()
    {
        return array_keys($this->params);
    }

    public function values()
    {
        return array_values($this->params);
    }

    public function toString()
    {
        return http_build_query($this->params);
    }

    public function __toString()
    {
        return $this->toString();
    }

    public function toArray()
    {
        return $this->params;
    }

    public function getIterator(): \Traversable
    {
        $entries = [];
        foreach ($this->params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $entries[] = [$key, $v];
                }
            } else {
                $entries[] = [$key, $value];
            }
        }
        return new \ArrayIterator($entries);
    }

    /**
     * @param callable(U[key-of<U>],key-of<U>,static): bool $callback
     */
    public function forEach($callback)
    {
        foreach ($this->params as $name => $value) {
            $callback($value, $name, $this);
        }
    }
}
