<?php

namespace Suovawp\Validation\Types;

/**
 * @template TV of bool
 * @template TD
 *
 * @extends Any<TV,TD>
 */
class Booln extends Any
{
    public const TYPE = 'boolean';

    protected $name = '布尔';

    public function is($value)
    {
        return is_bool($value);
    }

    public function cast($value)
    {
        return (bool) $value;
    }

    /**
     * 将布尔值转为整数.
     *
     * @return static<int,TD>
     */
    public function intval()
    {
        return $this->addAfter('intval');
    }
}
