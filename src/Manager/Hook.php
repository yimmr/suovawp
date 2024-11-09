<?php

namespace Suovawp\Manager;

class Hook
{
    /**
     * 批量注册钩子.
     *
     * @param array $hooks
     */
    public static function register($hooks)
    {
        foreach ($hooks as $item) {
            if (isset($item['actions'])) {
                static::registerActions($item['class'], $item['actions']);
            }
            if (isset($item['filters'])) {
                static::registerFilters($item['class'], $item['filters']);
            }
        }
    }

    /**
     * 使用类或对象方法注册动作钩子.
     *
     * @param string|object $object
     * @param array         $actions
     */
    public static function registerActions($object, $actions = [])
    {
        foreach ($actions as $hook => $params) {
            if (is_numeric($hook)) {
                add_action($params, [$object, $params]);
            } elseif (is_string($params)) {
                add_action($hook, [$object, $params]);
            } elseif (is_int($params)) {
                add_action($hook, [$object, $hook], $params);
            } elseif (is_array($params)) {
                add_action($hook, [$object, is_string(reset($params)) ? array_shift($params) : $hook], ...$params);
            }
        }
    }

    /**
     * 使用类或对象方法注册过滤钩子.
     *
     * @param string|object $object
     */
    public static function registerFilters($object, $filters = [])
    {
        foreach ($filters as $hook => $params) {
            if (is_numeric($hook)) {
                add_filter($params, [$object, $params]);
            } elseif (is_string($params)) {
                add_filter($hook, [$object, $params]);
            } elseif (is_int($params)) {
                add_filter($hook, [$object, $hook], $params);
            } elseif (is_array($params)) {
                add_filter($hook, [$object, is_string(reset($params)) ? array_shift($params) : $hook], ...$params);
            }
        }
    }
}
