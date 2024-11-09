<?php

namespace Suovawp\Validation\Types;

use Suovawp\Utils\Attachment;

/**
 * @template TV of int|float
 * @template TD
 *
 * @extends Any<TV,TD>
 */
class Num extends Any
{
    public const TYPE = 'number';

    protected $name = '数字';

    public function is($value)
    {
        return is_int($value) || is_float($value);
    }

    public function cast($value)
    {
        if (is_numeric($value)) {
            return $value + 0;
        }
        if (is_object($value) && !method_exists($value, '__toString')) {
            return 1;
        }
        return ($float = (float) $value) == ($int = (int) $float) ? $int : $float;
    }

    public function check(&$value, $method, $params = null)
    {
        switch ($method) {
            case 'min':
                return $value >= $params;
            case 'max':
                return $value <= $params;
            case 'between':
                return $value >= $params[0] && $value <= $params[1];
            case 'int':
                return is_int($value);
            case 'float':
                return $this->isFloat($value, $params);
            case 'id':
                return $this->isId($value);
            case 'amount':
                return $this->isFloat($value, $params);
            case 'decimal':
                return $this->isDecimal($value, $params[0], $params[1]);
            case 'attachid':
                return Attachment::isId($value, $params);
            case 'step':
                return 0 == $value % $params;
            case 'finite':
                return is_finite($value);
            case 'safe':
                return $value >= PHP_INT_MIN && $value <= PHP_INT_MAX;
            case 'positive':
                return $value > 0;
            case 'nonnegative':
                return $value >= 0;
            case 'negative':
                return $value < 0;
            case 'nonpositive':
                return $value <= 0;
            default:
                return parent::check($value, $method, $params);
        }
    }

    public function isFloat($value, $precision = null)
    {
        if (!is_float($value)) {
            return false;
        }
        if (null === $precision) {
            return true;
        }
        $formatted = number_format($value, $precision, '.', '');
        return $formatted === (string) $value;
    }

    public function isId($value)
    {
        return is_int($value) && $value > 0;
    }

    public function isDecimal($value, $precision, $scale = 0)
    {
        if (!is_numeric($value)) {
            return false;
        }
        $string = (string) $value;
        if (false !== strpos($string, 'e')) {
            return false;
        }
        $string = ltrim($string, '-');
        $dotpos = strpos($string, '.');
        if (false === $dotpos) {
            $intlen = strlen($string);
            $fractlen = 0;
        } else {
            $intlen = $dotpos;
            $fractlen = strlen($string) - $dotpos - 1;
        }
        return ($intlen + $fractlen) <= $precision && $fractlen <= $scale;
    }

    public function max($number, $message = '%1$s不能大于%2$s')
    {
        return $this->addRule('max', $message, $number);
    }

    public function min($number, $message = '%1$s不能小于%2$s')
    {
        return $this->addRule('min', $message, $number);
    }

    public function between($min, $max, $message = '%1$s的值应介于%2$s和%3$s之间')
    {
        return $this->addRule('between', $message, [$min, $max]);
    }

    public function int($message = '%1$s的值应是整数')
    {
        return $this->addRule('int', $message);
    }

    public function float($precision = null, $message = '%1$s仅支持%2$s位小数')
    {
        return $this->addRule('float', $message, $precision);
    }

    public function decimal($precision, $scale = 0, $message = '%1$s仅支持%2$s位数且最多%3$s位小数')
    {
        return $this->addRule('decimal', $message, [$precision, $scale]);
    }

    public function id($message = '%1$s不是有效的ID')
    {
        return $this->addRule('id', $message);
    }

    /** 类似Float方法但消息不一样 */
    public function amount($precision = 2, $message = '%1$s不是有效金额，最多支持%2$s位小数')
    {
        return $this->addRule('amount', $message, $precision);
    }

    /**
     * 这个方法还验证了ID是否真实存在.
     */
    public function attachid($type = 'image', $message = '%1$s不是有效的%2$s ID')
    {
        return $this->addRule('attachid', $message, $type);
    }

    public function step($number, $message = '%1$s只能是%2$s的倍数')
    {
        return $this->addRule('step', $message, $number);
    }

    public function finite($message = '%1$s只能是有限数')
    {
        return $this->addRule('finite', $message);
    }

    public function safe($message = '%1$s只能是安全数')
    {
        return $this->addRule('safe', $message);
    }

    public function positive($message = '%1$s只能是正数')
    {
        return $this->addRule('positive', $message);
    }

    public function nonpositive($message = '%1$s只能是非正数')
    {
        return $this->addRule('nonpositive', $message);
    }

    public function negative($message = '%1$s只能是负数')
    {
        return $this->addRule('negative', $message);
    }

    public function nonnegative($message = '%1$s只能是非负数')
    {
        return $this->addRule('nonnegative', $message);
    }

    protected function sanitizeValue($value, $type = 'text', ...$params)
    {
        switch ($type) {
            case 'attachid':
                return Attachment::isId($value, ...$params) ? $value : 0;
            default:
                return parent::sanitizeValue($value, $type, ...$params);
        }
    }
}
