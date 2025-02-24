<?php

namespace Suovawp;

use Suovawp\Utils\ArrAccessTrait;
use Suovawp\Utils\DataAccessorTrait;

class State implements \ArrayAccess
{
    use DataAccessorTrait,ArrAccessTrait;

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    public function append($key, $value)
    {
        $array = $this->array($key, []);
        $array[] = $value;
        $this->set($key, $array);
        return $this;
    }

    public function prepend($key, $value)
    {
        $array = $this->array($key, []);
        array_unshift($array, $value);
        $this->set($key, $array);
        return $this;
    }
}
