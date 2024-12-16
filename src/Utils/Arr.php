<?php

namespace Suovawp\Utils;

class Arr
{
    public static function isAssoc(array $array): bool
    {
        $i = 0;
        foreach ($array as $key => $value) {
            if ($key !== $i) {
                return true;
            }
            ++$i;
        }
        return false;
    }

    /**
     * 所有元素通过回调测试时返回 true.
     *
     * @template TValue
     * @template TKey of array-key
     * @param array<TKey,TValue>          $array
     * @param callable(TValue,TKey): bool $callback
     */
    public static function every(array $array, callable $callback): bool
    {
        foreach ($array as $k => $v) {
            if (!$callback($v, $k)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 至少有一个元素通过回调测试时返回 true.
     *
     * @template TValue
     * @template TKey of array-key
     * @param array<TKey,TValue>          $array
     * @param callable(TValue,TKey): bool $callback
     */
    public static function some(array $array, callable $callback): bool
    {
        foreach ($array as $k => $v) {
            if ($callback($v, $k)) {
                return true;
            }
        }
        return false;
    }

    public static function pick(array $array, $keys)
    {
        return array_intersect_key($array, array_flip($keys));
    }

    public static function omit(array $array, $keys)
    {
        return array_diff_key($array, array_flip($keys));
    }

    /** 限制数组值在白名单中 */
    public static function only(array $array, $whitelist)
    {
        return array_filter($array, fn ($v) => in_array($v, $whitelist));
    }

    /** 限制数组值不在黑名单中 */
    public static function except(array $array, $blacklist)
    {
        return array_filter($array, fn ($v) => !in_array($v, $blacklist));
    }

    /** 数组项为空时使用默认数组的值 */
    public static function fillEmpty(array &$array, $default = [])
    {
        foreach ($default as $key => $value) {
            if (empty($array[$key])) {
                $array[$key] = $value;
            }
        }
    }

    public static function findOrDefault(array $array, $value, $defaultIndex = 0)
    {
        return in_array($value, $array) ? $value : ($array[$defaultIndex] ?? null);
    }

    public static function has($array, $key)
    {
        if (!$array) {
            return false;
        }
        if (false === strpos($key, '.')) {
            return array_key_exists($key, $array);
        }
        $keys = explode('.', $key);
        foreach ($keys as $key) {
            if (!is_array($array) || !array_key_exists($key, $array)) {
                return false;
            }
            $array = $array[$key];
        }
        return true;
    }

    public static function get($array, $key, $default = null)
    {
        if (false === strpos($key, '.')) {
            return $array[$key] ?? $default;
        }
        $keys = explode('.', $key);
        foreach ($keys as $key) {
            if (!is_array($array) || !array_key_exists($key, $array)) {
                return $default;
            }
            $array = $array[$key];
        }
        return $array;
    }

    public static function set(&$array, $key, $value = null)
    {
        if (false === strpos($key, '.')) {
            $array[$key] = $value;
            return;
        }
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        foreach ($keys as $key) {
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }
        $array[$lastKey] = $value;
    }

    public static function forget(&$array, $key)
    {
        if (false === strpos($key, '.')) {
            unset($array[$key]);
            return;
        }
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        foreach ($keys as $key) {
            if (!isset($array[$key]) || !is_array($array[$key])) {
                return;
            }
            $array = &$array[$key];
        }
        unset($array[$lastKey]);
    }

    public static function forgetMany(&$array, array $keys)
    {
        foreach ($keys as $key) {
            static::forget($array, $key);
        }
    }

    public static function equalsRecursive(array $array1, array $array2)
    {
        if (count($array1) !== count($array2)) {
            return false;
        }
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!is_array($array2[$key])) {
                    return false;
                }
                if (!self::equalsRecursive($value, $array2[$key])) {
                    return false;
                }
                continue;
            }
            if (!array_key_exists($key, $array2)) {
                return false;
            }
            if ($array2[$key] !== $value) {
                return false;
            }
        }
        return true;
    }

    public static function mergeRecursive(array $array1, array $array2)
    {
        $merged = $array1;
        foreach ($array2 as $key => $value) {
            if (isset($merged[$key]) && is_array($value) && is_array($merged[$key])) {
                $merged[$key] = static::mergeRecursive($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }

    /**
     * 递归更新数组.
     *
     * @template T
     * @param  T|array $data
     * @return T       返回更新后的数组。$newData和$data有相同结构才会完整更新，值类型不同或键不存在都会被忽略
     */
    public static function updateRecursive(array $data, array $newData)
    {
        $updated = $data;
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $newData)) {
                continue;
            }
            if (is_array($value)) {
                if (is_array($newData[$key])) {
                    $updated[$key] = static::updateRecursive($value, $newData[$key]);
                }
            } elseif (!is_array($newData[$key])) {
                $updated[$key] = $newData[$key];
            }
        }
        return $updated;
    }

    /**
     * 移动指定键和对应值到另一数组.
     *
     * @param array $keys 要移动的键
     */
    public static function move(array &$source, array &$target, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $target[$key] = $source[$key];
                unset($source[$key]);
            }
        }
    }

    /**
     * 移动指定键和对应值到另一数组，前提移动键是源数组已设置，目标数组未设置.
     */
    public static function moveSafe(array &$source, array &$target, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && !isset($target[$key])) {
                $target[$key] = $source[$key];
                unset($source[$key]);
            }
        }
    }

    /**
     * 数组项目需要存在于默认数组中，若结果为空则使用默认数组.
     */
    public static function inDefaultOrDefault(array $array, array $default)
    {
        if (empty($array)) {
            return $default;
        }
        $result = array_intersect($default, $array);
        return $result ?: $default;
    }

    /**
     * 解析数据中的模板插值
     *
     * @template T
     * @param  T|array $array
     * @return T
     */
    public static function parseEmbedVars(array $array, array $data)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::parseEmbedVars($value, $data);
            } elseif (is_string($value) && Str::startsWith($value, '{{') && Str::endsWith($value, '}}')) {
                $dataKey = substr($value, 2, -2);
                $array[$key] = static::get($data, $dataKey, '');
            }
        }
        return $array;
    }
}
