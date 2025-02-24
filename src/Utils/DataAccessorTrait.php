<?php

namespace Suovawp\Utils;

trait DataAccessorTrait
{
    use DataFormatTrait;

    protected $value = [];

    protected $keyCache = [];

    protected function beforeHas($key, $keys = null)
    {
    }

    protected function beforeGet($key, $keys = null)
    {
    }

    protected function afterForget($key, $keys = null)
    {
    }

    public function fill(array $data)
    {
        $this->value = $data + $this->value;
        return $this;
    }

    /** 设置根数据 */
    public function setValue(array $value)
    {
        $this->value = $value;
        return $this;
    }

    protected function isFirstKeySet(string $key)
    {
        return isset($this->value[$key]);
    }

    protected function isFirstKeyExists(string $key)
    {
        return array_key_exists($key, $this->value);
    }

    protected function getKeys(string $key)
    {
        return $this->keyCache[$key] ??= explode('.', $key);
    }

    public function has(string $key)
    {
        if (false === strpos($key, '.')) {
            $this->beforeHas($key);
            return array_key_exists($key, $this->value);
        }
        $keys = $this->getKeys($key);
        $this->beforeHas($keys[0], $keys);
        $array = $this->value;
        foreach ($keys as $key) {
            if (!is_array($array) || !array_key_exists($key, $array)) {
                return false;
            }
            $array = $array[$key];
        }
        return true;
    }

    public function get(string $key, $default = null)
    {
        if (false === strpos($key, '.')) {
            $this->beforeGet($key);
            return array_key_exists($key, $this->value) ? $this->value[$key] : $default;
        }
        $keys = $this->getKeys($key);
        $this->beforeGet($keys[0], $keys);
        $array = $this->value;
        foreach ($keys as $key) {
            if (!is_array($array) || !array_key_exists($key, $array)) {
                return $default;
            }
            $array = $array[$key];
        }
        return $array;
    }

    public function set(string $key, $value = null)
    {
        if (false === strpos($key, '.')) {
            $this->value[$key] = $value;
            return;
        }
        $keys = $this->getKeys($key);
        $lastKey = array_pop($keys);
        $array = &$this->value;
        foreach ($keys as $key) {
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }
        $array[$lastKey] = $value;
    }

    public function forget(string $key)
    {
        if (!$this->value) {
            return;
        }
        if (false === strpos($key, '.')) {
            unset($this->value[$key]);
            $this->afterForget($key);
            return;
        }
        $keys = $this->getKeys($key);
        $lastKey = array_pop($keys);
        $array = &$this->value;
        foreach ($keys as $key) {
            if (!isset($array[$key]) || !is_array($array[$key])) {
                return;
            }
            $array = &$array[$key];
        }
        unset($array[$lastKey]);
        $this->afterForget($keys[0], $keys);
    }

    public function forgetMany(array $keys)
    {
        foreach ($keys as $key) {
            $this->forget($key);
        }
    }

    public function merge(array $data)
    {
        $this->value = array_merge($this->value, $data);
    }

    public function mergeRecursive(array $data)
    {
        $this->value = array_merge_recursive($this->value, $data);
    }

    public function toArray()
    {
        return $this->value;
    }

    public function toJson(int $flags = 0)
    {
        return json_encode($this->value, $flags);
    }

    public function __invoke(string $key, $value = null)
    {
        return $this->get($key, $value);
    }
}
