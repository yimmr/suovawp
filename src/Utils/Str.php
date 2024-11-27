<?php

namespace Suovawp\Utils;

class Str
{
    protected static $cache = [];

    public static function create(string $value)
    {
        return new Strval($value);
    }

    public static function camel(string $value, $ucfirst = false): string
    {
        $key = "camel.$ucfirst.$value";
        if (isset(static::$cache[$key])) {
            return static::$cache[$key];
        }
        $value = str_replace(['-', '_'], ' ', $value);
        $value = str_replace(' ', '', ucwords($value));
        $value = $ucfirst ? $value : lcfirst($value);
        return static::$cache[$key] = $value;
    }

    public static function snake(string $value): string
    {
        return static::$cache['snake.'.$value] ??= strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($value)));
    }

    public static function separate(string $value, $delimiter = '_'): string
    {
        $key = "separate.$delimiter.$value";
        if (isset(static::$cache[$key])) {
            return static::$cache[$key];
        }
        if (preg_match('/[A-Z]/', $value)) {
            $value = preg_replace(['/\s+/u', '/(?<!^)[A-Z]/u'], ['', $delimiter.'$0'], $value);
        }
        return static::$cache[$key] = $value;
    }

    public static function isJson($string, $depth = 10)
    {
        try {
            json_decode($string, false, $depth, JSON_THROW_ON_ERROR);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isPhone($value)
    {
        return 1 === preg_match('/^1[3-9]\d{9}$/', $value);
    }

    public static function isEmail($value)
    {
        return false != filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    public static function isUsername($value)
    {
        return 1 === preg_match('/^[a-zA-Z0-9_-]{3,16}$/', $value);
    }

    public static function isLoginid($value)
    {
        return static::isEmail($value) || static::isPhone($value) || static::isUsername($value);
    }

    /**
     * @param array{min:int,max:int,uppercase:true,lowercase:true,numbers:true,symbols:true} $options
     */
    public static function isPasswd($string, $options = [])
    {
        $options += [
            'min'       => 8,
            'max'       => 32,
            'uppercase' => true,
            'lowercase' => true,
            'numbers'   => true,
            'symbols'   => true,
        ];
        $pattern = '/^';
        if ($options['uppercase']) {
            $pattern .= '(?=.*[A-Z])';
        }
        if ($options['lowercase']) {
            $pattern .= '(?=.*[a-z])';
        }
        if ($options['numbers']) {
            $pattern .= '(?=.*\d)';
        }
        if ($options['symbols']) {
            $pattern .= '(?=.*[!@#$%^&*(),.?":{}|<>])';
        }
        $pattern .= '.{'.$options['min'].','.$options['max'].'}$/';
        return 1 === preg_match($pattern, $string);
    }

    public static function isEmoji($string)
    {
        $pattern = '/[\x{1F600}-\x{1F64F}|\x{1F300}-\x{1F5FF}|\x{1F680}-\x{1F6FF}|\x{2600}-\x{26FF}|\x{2700}-\x{27BF}]/u';
        return 1 === preg_match($pattern, $string);
    }

    public static function isIp($string, $version = null)
    {
        if ('v4' === $version) {
            return false !== filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        } elseif ('v6' === $version) {
            return false !== filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }
        return false !== filter_var($string, FILTER_VALIDATE_IP);
    }

    public static function isUuid($string)
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return 1 === preg_match($pattern, $string);
    }

    public static function isCuid($string)
    {
        $pattern = '/^c[^\s-]{8,}$/i';
        return 1 === preg_match($pattern, $string);
    }

    public static function isDuration($string)
    {
        $pattern = '/^P(?:(\d+Y)?(\d+M)?(\d+D)?)?(T(?:(\d+H)?(\d+M)?(\d+S)?))?$/';
        return 1 === preg_match($pattern, $string);
    }

    public static function isDatetime($string)
    {
        return (bool) strtotime($string);
    }

    public static function includes($haystack, $needle)
    {
        return false !== strpos($haystack, $needle);
    }

    public static function startsWith($haystack, $needle)
    {
        return 0 === strncmp($haystack, $needle, strlen($needle));
    }

    public static function endsWith($haystack, $needle)
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }

    public static function parseStrFunc($string)
    {
        $openpos = strpos($string, '(');
        if (false === $openpos) {
            return ['name' => $string, 'args' => []];
        }
        $name = substr($string, 0, $openpos);
        $argsString = substr($string, $openpos + 1, -1);
        $args = explode(',', $argsString);
        $args = array_map('trim', $args);
        return ['name' => $name, 'args' => $args];
    }

    public static function parseVars($string, $data)
    {
        return str_replace(array_map(fn ($str) => "[$str]", array_keys($data)), array_values($data), $string);
    }
}
