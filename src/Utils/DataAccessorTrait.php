<?php

namespace Suovawp\Utils;

trait DataAccessorTrait
{
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

    /** 获取数组值，非数组或不存在时返回默认值 */
    public function array(string $key, array $default = [])
    {
        $value = $this->get($key, null);
        return is_array($value) ? $value : $default;
    }

    /** 获取数组值，若是字符串则拆分为数组，其他类型或不存在时返回默认值 */
    public function splitIf(string $key, string $separator = ',', array $default = [])
    {
        $value = $this->get($key, null);
        return is_array($value) ? $value : (is_string($value) ? explode($separator, $value) : $default);
    }

    /** 获取字符串，非字符串或不存在时返回默认值 */
    public function string(string $key, string $default = '')
    {
        $value = $this->get($key, null);
        return is_string($value) ? $value : $default;
    }

    /** 获取字符串，若是数组则合并为字符串，其他类型或不存在时返回默认值 */
    public function joinIf(string $key, string $separator = ',', string $default = '')
    {
        $value = $this->get($key, null);
        return is_string($value) ? $value : (is_array($value) ? implode($separator, $value) : $default);
    }

    /** 获取布尔，其他类型或不存在时返回默认值 */
    public function boolean(string $key, bool $default = false)
    {
        $value = $this->get($key, null);
        return is_bool($value) ? $value : $default;
    }

    /** 获取数字，其他类型或不存在时返回默认值 */
    public function numeric(string $key, $default = 0)
    {
        $value = $this->get($key, null);
        return is_numeric($value) ? $value : $default;
    }

    /** 获取整数，非整数尝试转换，转换失败或不存在时返回默认值 */
    public function integer(string $key, int $default = 0)
    {
        $value = $this->get($key, null);
        if (is_int($value)) {
            return $value;
        }
        return is_numeric($value) ? (int) $value : $default;
    }

    /** 获取浮点数，非浮点数尝试转换，转换失败或不存在时返回默认值 */
    public function float(string $key, float $default = 0)
    {
        $value = $this->get($key, null);
        if (is_float($value)) {
            return $value;
        }
        return is_numeric($value) ? (float) $value : $default;
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
