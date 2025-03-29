<?php

namespace Suovawp\Validation\Types;

use Suovawp\Utils\Str as UtilsStr;
use Suovawp\Validation\Sanitize;

/**
 * @template TV of string
 * @template TD
 *
 * @extends Any<TV,TD>
 */
class Str extends Any
{
    public const TYPE = 'string';

    protected $name = '字符串';

    public function is($value)
    {
        return is_string($value);
    }

    public function cast($value)
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value) || (is_object($value) && !method_exists($value, '__toString'))) {
            return json_encode($value);
        }
        return (string) $value;
    }

    public function check(&$value, $method, $params = null)
    {
        switch ($method) {
            case 'length':
                return mb_strlen($value) === $params;
            case 'max':
                return mb_strlen($value) <= $params;
            case 'min':
                return mb_strlen($value) >= $params;
            case 'includes':
                return false !== strpos($value, $params);
            case 'startsWith':
                return UtilsStr::startsWith($value, $params);
            case 'endsWith':
                return UtilsStr::endsWith($value, $params);
            case 'username':
                return UtilsStr::isUsername($value);
            case 'loginid':
                return UtilsStr::isLoginid($value);
            case 'phone':
                return UtilsStr::isPhone($value);
            case 'email':
                return UtilsStr::isEmail($value);
            case 'passwd':
                return UtilsStr::isPasswd($value, $params);
            case 'url':
                return (bool) filter_var($value, FILTER_VALIDATE_URL);
            case 'domain':
                return (bool) filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
            case 'emoji':
                return UtilsStr::isEmoji($value);
            case 'datetime':
                return UtilsStr::isDatetime($value);
            case 'duration':
                return UtilsStr::isDuration($value);
            case 'key':
                return 1 === preg_match('/^[\d\w]+$/', $value);
            case 'uuid':
                return UtilsStr::isUuid($value);
            case 'cuid':
                return UtilsStr::isCuid($value);
            case 'ip':
                return UtilsStr::isIp($value, $params);
            case 'json':
                return UtilsStr::isJson($value, $params);
            case 'regex':
                return 1 === preg_match($params, $value);
            case 'decimal':
                return (new Num())->isDecimal($value, $params[0], $params[1]);
            default:
                return parent::check($value, $method, $params);
        }
    }

    /**
     * 把验证成功后的值分割为数组.
     *
     * @template T of Arr
     * @param string|T $schema 可选对新值继续验证，也支持字符串规则（|分隔方法名:参数,参数...）
     */
    public function split($separator = ',', $schema = null)
    {
        return $this->addAfter(function ($value, $separator, $schema) {
            $value = explode($separator, $value);
            if (!isset($schema)) {
                return $value;
            }
            if (is_string($schema)) {
                $schema = (new Arr(Any::create(), $this->label, $this->messages))->useStrRule($schema);
            }
            $this->syncBaseInfoIf($schema);
            return $schema->parse($value);
        }, $separator, $schema);
    }

    public function code()
    {
        return $this->transform('stripslashes');
    }

    public function trim(string $characters = " \n\r\t\v\0")
    {
        return $this->addAfter('trim', $characters);
    }

    public function toLowerCase()
    {
        return $this->transform('strtolower');
    }

    public function toUpperCase()
    {
        return $this->transform('strtoupper');
    }

    protected function sanitizeValue($value, ...$params)
    {
        $type = $params ? array_shift($params) : '';
        switch ($type) {
            case 'content':
                return Sanitize::ksesUserContent($value, ...$params);
                break;
            case 'pre':
                return Sanitize::pre($value);
                break;
            case 'strip_tags':
                return Sanitize::stripAllTags($value);
            default:
                return parent::sanitizeValue($value, $type, ...$params);
        }
    }

    public function loginid($message = '%1$s不是有效的登录ID')
    {
        return $this->addRule('loginid', $message);
    }

    public function phone($message = '%1$s不是有效的手机号码')
    {
        return $this->addRule('phone', $message);
    }

    public function username($message = '%1$s不是有效的用户名（3-16位字母数字下划线组合）')
    {
        return $this->addRule('username', $message);
    }

    public function email($message = '%1$s不是有效的电子邮件地址')
    {
        return $this->addRule('email', $message);
    }

    /**
     * @param array{min:int,max:int,uppercase:true,lowercase:true,numbers:true,symbols:true} $options
     */
    public function passwd($options = [], $message = '%1$s不是有效的密码')
    {
        return $this->addRule('passwd', $message, $options);
    }

    public function max($number, $message = '%1$s长度不能超过%2$s个字符')
    {
        return $this->addRule('max', $message, $number);
    }

    public function min($number, $message = '%1$s长度不能小于%2$s个字符')
    {
        return $this->addRule('min', $message, $number);
    }

    public function length($number, $message = '%1$s长度必须是%2$s个字符')
    {
        return $this->addRule('length', $message, $number);
    }

    public function url($message = '%1$s不是有效的URL地址')
    {
        return $this->addRule('url', $message);
    }

    public function domain($message = '%1$s不是有效的域名')
    {
        return $this->addRule('domain', $message);
    }

    public function emoji($message = '%1$s不是有效的emoji表情')
    {
        return $this->addRule('emoji', $message);
    }

    public function key($message = '%1$s不是有效键')
    {
        return $this->addRule('key', $message);
    }

    public function uuid($message = '%1$s不是有效的UUID')
    {
        return $this->addRule('uuid', $message);
    }

    public function cuid($message = '%1$s不是有效的CUID')
    {
        return $this->addRule('cuid', $message);
    }

    public function duration($message = '%1$s不是有效的时间段格式')
    {
        return $this->addRule('duration', $message);
    }

    public function regex($pattern, $message = '%1$s不符合指定的正则表达式格式')
    {
        return $this->addRule('regex', $message, $pattern);
    }

    public function includes($substring, $message = '%1$s必须包含指定的子字符串')
    {
        return $this->addRule('includes', $message, $substring);
    }

    public function startsWith($prefix, $message = '%1$s必须以指定的前缀开头')
    {
        return $this->addRule('startsWith', $message, $prefix);
    }

    public function endsWith($suffix, $message = '%1$s必须以指定的后缀结尾')
    {
        return $this->addRule('endsWith', $message, $suffix);
    }

    public function datetime($message = '%1$s不是有效的日期时间格式')
    {
        return $this->addRule('datetime', $message);
    }

    /**
     * @param string $version v4|v6，默认为null表示任意版本
     */
    public function ip($version = null, $message = '%1$s不是有效的IP地址')
    {
        return $this->addRule('ip', $message, $version);
    }

    /**
     * @param int $depth 控制解析深度，越深越慢，但能检查更深层的JSON格式错误
     */
    public function json($depth = 10, $message = '%1$s不是有效的JSON格式')
    {
        return $this->addRule('json', $message, $depth);
    }

    public function decimal($precision, $scale = 0, $message = '%1$s仅支持%2$s位数且最多%3$s位小数')
    {
        return $this->addRule('decimal', $message, [$precision, $scale]);
    }

    public function amount($precision = 20, $scale = 2, $message = '%1$s不是有效金额，仅支持%2$s位数且最多%3$s位小数')
    {
        return $this->addRule('decimal', $message, [$precision, $scale]);
    }
}
