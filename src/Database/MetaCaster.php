<?php

namespace Suovawp\Database;

use Suovawp\Validation\Types\Arr;
use Suovawp\Validation\Types\Str;

class MetaCaster
{
    public static function defaultValue($value)
    {
        return is_array($value) ? $value : null;
    }

    public static function coerce($value, $type = 'string')
    {
        switch ($type) {
            case 'string':
                return is_string($value) ? $value : (new Str())->cast($value);
            case 'boolean':
                return (bool) $value;
            case 'integer':
                return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
            case 'number':
                return self::numeric($value);
            case 'array':
                return is_array($value) ? $value : ($value ? (new Arr())->cast($value) : []);
            case 'object':
                return is_object($value) ? $value : (object) $value;
            default:
                return $value;
        }
    }

    public static function count($value)
    {
        return max(0, (int) $value);
    }

    public static function boolint($value)
    {
        return $value ? 1 : 0;
    }

    public static function id($value)
    {
        return is_numeric($value) && $value > 0 ? (int) $value : 0;
    }

    public static function ids($value)
    {
        if (is_string($value)) {
            $value = str_replace(['，', '、'], ',', $value);
            $value = explode(',', $value);
        }
        if (is_array($value)) {
            return array_filter(array_map(static::class.'::id', $value));
        }
        return [];
    }

    public static function idstr($value)
    {
        return is_array($value) ? implode(',', $value) : strval($value);
    }

    public static function price($value, $n = 2)
    {
        if (!is_numeric($value)) {
            return '0';
        }
        $num = 10 ** $n;
        return (string) self::numeric(floor($value * $num) / $num);
    }

    public static function numeric($value)
    {
        if (!is_numeric($value)) {
            return 0;
        }
        if (is_string($value)) {
            if (false !== strpos($value, '.')) {
                $value = rtrim(rtrim($value, '0'), '.');
            }
            return $value;
        }
        $intValue = (int) $value;
        return $intValue == $value ? $intValue : (float) $value;
    }
}
