<?php

namespace Suovawp\Validation;

class Validator
{
    /** @var array<string,class-string> */
    protected static $types = [];

    /**
     * 注册自定义类型.
     *
     * @template T of Types\Any
     * @param string          $name
     * @param class-string<T> $type
     */
    public static function register($name, $type)
    {
        static::$types[$name] = $type;
    }

    /**
     * @template T of key-of<self::$types>
     * @param T $name
     * @return new<self::$types[T]>
     */
    public static function get(string $name, ...$params)
    {
        $type = static::$types[$name] ?? null;
        if (null === $type) {
            if (method_exists(static::class, $name)) {
                return static::$name(...$params);
            }
            throw new \InvalidArgumentException('未找到类型[%s]', $name);
        }
        return new $type(...$params);
    }

    public static function any($label = '', $messages = [])
    {
        return new Types\Any($label, $messages);
    }

    public static function array($schema = [], $label = '', $messages = [])
    {
        return new Types\Arr($schema, $label, $messages);
    }

    /**
     * @return Types\Str<null,null>
     */
    public static function string($label = '', $messages = [])
    {
        return new Types\Str($label, $messages);
    }

    /**
     * @return Types\Num<null,null>
     */
    public static function number($label = '', $messages = [])
    {
        return new Types\Num($label, $messages);
    }

    /**
     * @return Types\Num<null,null>
     */
    public static function integer($label = '', $messages = [])
    {
        return (new Types\Num($label, $messages))->int();
    }

    /**
     * @return Types\Num<null,null>
     */
    public static function bigint($label = '', $messages = [])
    {
        return (new Types\Num($label, $messages))->int();
    }

    /**
     * @return Types\Num<null,null>
     */
    public static function float($label = '', $messages = [])
    {
        return (new Types\Num($label, $messages))->float();
    }

    /**
     * @return Types\Booln<null,null>
     */
    public static function boolean($label = '', $messages = [])
    {
        return new Types\Booln($label, $messages);
    }

    /**
     * @return Types\Date<null,null>
     */
    public static function date($label = '', $messages = [])
    {
        return new Types\Date($label, $messages);
    }

    /**
     * @return Types\File<null,null>
     */
    public static function file($label = '', $messages = [])
    {
        return new Types\File($label, $messages);
    }

    /**
     * @template T of  Types\Any
     * @param  T[] $types 类型数组。可以只给首个类型设置标题和消息，其他类型验证失败时自动继承
     * @return T   返回首个类型
     */
    public static function union($types)
    {
        $type = $lastType = array_shift($types);
        foreach ($types as $ortype) {
            $lastType->or($ortype);
            $lastType = $ortype;
        }
        return $type;
    }

    /**
     * 混合规则转为模式继续验证，不推荐，存在兼容问题.
     */
    public static function validate($data, $rules)
    {
        return static::array(array_map([static::class, 'ruleToSchema'], $rules))->safeParse($data);
    }

    public static function ruleToSchema($rule)
    {
        if (is_string($rule)) {
            $segs = explode('|', $rule);
            $type = array_shift($segs);
            $schema = static::$type();
            foreach ($segs as $method) {
                $method = 'default' == $method ? $method.':' : $method;
                if (false === strpos($method, ':')) {
                    $schema->{$method}();
                } else {
                    [$method, $param] = explode(':', $method);
                    $params = false === strpos($param, ',') ? [$param] : explode(',', $param);
                    $schema->{$method}(...$params);
                }
            }
        } else {
            $type = $rule['type'];
            unset($rule['type']);
            if (isset($rule['items'])) {
                $schema = static::array(static::ruleToSchema($rule['items']));
                unset($rule['items']);
            } elseif (isset($rule['props'])) {
                $schema = static::array(array_map([static::class, 'ruleToSchema'], $rule['props']));
                unset($rule['props']);
            } else {
                $schema = static::{$type}();
            }
            foreach ($rule as $method => $param) {
                if (is_array($param)) {
                    $param = 'array' == $type ? [$param] : $param;
                } else {
                    $params = false === strpos($param, ',') ? [$param] : explode(',', $param);
                }
                $schema->{$method}(...$params);
            }
        }
        return $schema;
    }
}
