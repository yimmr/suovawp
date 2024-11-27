<?php

namespace Suovawp\Utils;

trait DataFormatTrait
{
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
}
