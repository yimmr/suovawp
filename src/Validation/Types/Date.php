<?php

namespace Suovawp\Validation\Types;

use Suovawp\Utils\Date as UtilsDate;

/**
 * @template TV of string
 * @template TD
 *
 * @extends Any<TV,TD>
 */
class Date extends Any
{
    public const TYPE = 'datetime';

    protected $name = '日期时间';

    protected $dateFormat;

    public function is($value)
    {
        return false !== strtotime($value);
    }

    public function cast($value)
    {
        return UtilsDate::format('Y-m-d H:i:s', $value);
    }

    public function check(&$value, $method, $params = null)
    {
        switch ($method) {
            case 'max':
                return UtilsDate::checkMax($value, $params);
            case 'min':
                return UtilsDate::checkMin($value, $params);
            case 'between':
                return UtilsDate::checkMin($value, $params);
            case 'formatSame':
                return UtilsDate::isFormat($value, $params);
            case 'formatLike':
                return UtilsDate::isFormatLike($value, $params);
            default:
                return parent::check($value, $method, $params);
        }
    }

    public function max(string $datetime, $message = '%1$s的时间不能晚于%2$s')
    {
        return $this->addRule('max', $datetime, $message);
    }

    public function min(string $datetime, $message = '%1$s的时间不能早于%2$s')
    {
        return $this->addRule('min', $datetime, $message);
    }

    public function between(string $mindate, string $maxdate, $message = '%1$s的时间只能介于%2$s和%3$s之间')
    {
        return $this->addRule('between', $message, [$mindate, $maxdate]);
    }

    public function formatSame(string $format, $message = '%1$s的时间格式不正确')
    {
        return $this->addRule('formatSame', $message, $format);
    }

    public function formatLike(string $format, $message = '%1$s的时间格式无效')
    {
        return $this->addRule('formatLike', $message, $format);
    }

    /**
     * 验证时进行指定格式化转换.
     *
     * @param string $format
     */
    public function format($format = 'Y-m-d H:i:s')
    {
        return $this->transform(fn ($v) => UtilsDate::format($format, $v, $v));
    }
}
