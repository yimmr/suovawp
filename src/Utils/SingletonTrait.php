<?php

namespace Suovawp\Utils;

trait SingletonTrait
{
    /** @var static|null */
    private static $instance;

    public static function getInstance()
    {
        return static::$instance ??= new static();
    }

    /** @return static */
    public static function i()
    {
        return static::$instance;
    }
}
