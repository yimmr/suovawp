<?php

namespace Suovawp\Validation\Types;

use Suovawp\Utils\Arr;
use Suovawp\Utils\Str;
use Suovawp\Validation\Sanitize;
use Suovawp\Validation\ValidatorException;

/**
 * @template TV of null
 * @template TD of null
 *
 * @phpstan-type Rule array{method:string,message:string,params:mixed|mixed[]|null}
 *
 * @phpstan-import-type ValidatorIssue from ValidatorException
 */
class Any
{
    public const TYPE = 'any';

    protected $name = '任何';

    public $label;

    protected $path = '';

    /** @var TD */
    protected $defaultValue;

    protected $hasDefault = false;

    protected $isOptional = false;

    protected $isNullable = false;

    /** 数组等复杂类型可以调整其属性的类型是否已定义（即键有输入值） */
    protected $isUndefined = false;

    protected $isCoerce = false;

    /** @var Rule[] */
    protected $rules = [];

    protected $messages;

    /** @var Any */
    protected $orType;

    protected $catchValue;

    protected $hasCatchValue = false;

    protected $isStrict = false;

    protected $after = [];

    /** @var ValidatorException */
    protected $error;

    /** @var self|null */
    protected $topLevelType;

    protected $throwable = true;

    /** @var ValidatorIssue[] */
    protected $issues = [];

    protected $unionChaining = [];

    protected $isAbortEarly = false;

    protected $requiredWhen;

    protected $hasChild = false;

    protected $isUnsetEmpty = false;

    public function __construct($label = '', $messages = [])
    {
        $this->label = $label;
        $this->messages = $messages;
    }

    /**
     * @return static<TV,TD>
     */
    public static function create($label = '', $messages = [])
    {
        return new static($label, $messages);
    }

    public function label($label)
    {
        $this->label = $label;
        return $this;
    }

    public function is($value)
    {
        return true;
    }

    /**
     * @template T
     * @param  T  $value
     * @return TV
     */
    public function cast($value)
    {
        return $value;
    }

    /**
     * 指定默认值，未提供时使用此值验证.
     *
     * @template T
     * @param  T         $value
     * @return Any<TV,T>
     */
    public function default($value)
    {
        $this->defaultValue = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function unsetEmpty()
    {
        $this->isUnsetEmpty = true;
        if (!isset($this->requiredWhen)) {
            $this->isOptional = true;
        }
        return $this;
    }

    public function optional()
    {
        $this->isOptional = true;
        return $this;
    }

    public function nullable()
    {
        $this->isNullable = true;
        return $this;
    }

    public function nonnull()
    {
        $this->isNullable = false;
        return $this;
    }

    /**
     * 在验证前进行类型转换.
     */
    public function coerce()
    {
        $this->isCoerce = true;
        return $this;
    }

    /**
     * 不要在验证前进行类型转换.
     */
    public function noncoerce()
    {
        $this->isCoerce = false;
        return $this;
    }

    public function check(&$value, $method, $params = null)
    {
        switch ($method) {
            case 'eq':
                return $value == $params;
            case 'ne':
                return $value != $params;
            case 'lt':
                return $value < $params;
            case 'lte':
                return $value <= $params;
            case 'gt':
                return $value > $params;
            case 'gte':
                return $value >= $params;
            case 'same':
                return $value === $params;
            case 'different':
                return $value !== $params;
            case 'nonempty':
                return !empty($value);
            case 'enum':
                return in_array($value, $params[0]);
            case 'instanceof':
                return is_a($value, $params);
            case 'sanitize':
                $value = $this->sanitizeValue($value, ...$params);
                return true;
            case 'transform':
                $value = $params($value);
                return true;
            case 'refine':
                return $params($value, $this);
            default:
                $this->error(['code' => 'UNKNOWN_CONDITION', 'method' => $method]);
                break;
        }
    }

    protected function parseChildren($value)
    {
        return $value;
    }

    /**
     * 不抛出验证器异常，而是返回包含数据或错误数组；而其他异常会被抛出.
     *
     * @template T
     * @param  T                                                                                             $value
     * @return array{success:true,data:T,error:null}|array{success:false,error:ValidatorException,data:null}
     */
    public function safeParse($value)
    {
        try {
            return ['success' => true, 'data' => $this->parse($value)];
        } catch (ValidatorException $th) {
            return ['success' => false, 'error' => $th];
        }
    }

    /**
     * @template T
     * @param  T                                 $value
     * @return ((TD is null ? T|TV : (T|TV|TD)))
     *
     * @throws ValidatorException
     */
    public function parse($value)
    {
        $rawvalue = $value;

        try {
            if ($this->isUnsetEmpty && empty($value)) {
                $this->isUndefined = true;
            }
            if ($this->isUndefined) {
                if ($this->hasDefault) {
                    $rawvalue = $value = $this->defaultValue;
                } elseif ($this->isOptional) {
                    return;
                } else {
                    $this->error('REQUIRED', $value);
                }
            }
            if ($this->isNullable && null === $value) {
                return null;
            }
            if (!$this->is($value)) {
                if (!$this->isCoerce || !$this->is($value = $this->cast($value))) {
                    $this->error('INVALID_TYPE', $value);
                }
            }
            foreach ($this->rules as $rule) {
                if (!$this->check($value, $rule['method'], $rule['params'])) {
                    $this->error($rule, $value);
                }
            }
            return $this->doAfter($this->parseChildren($value));
        } catch (ValidatorException $th) {
            // 全局共享一个异常，若出现不同实例，则可能未正确设置嵌套关系，此时合并错误
            if (($error = $this->getError()) !== $th) {
                foreach ($th->getErrors() as $issue) {
                    $issue['path'] = $issue['path'] ?: $this->path;
                    $issue['label'] = $issue['label'] ?: $this->label;
                    $issue['is_from_other'] = true;
                    $this->issues[] = $issue;
                }
            }
            if (isset($this->orType)) {
                return $this->prepareUnoinNext()->parse($rawvalue);
            }
            if ($this->hasCatchValue) {
                return is_callable($this->catchValue) ? call_user_func($this->catchValue, [
                    'input'  => $rawvalue,
                    'issues' => $this->issues,
                ]) : $this->catchValue;
            }
            $error->addIssues($this->issues);
            $this->issues = [];
            if ($this->throwable || !isset($this->topLevelType)) {
                throw $error;
            }
        }
    }

    /**
     * @param Rule|string $rule  规则或错误码
     * @param mixed       $input 当前验证的输入值
     */
    protected function error($rule = [], $input = null)
    {
        if (is_string($rule)) {
            $code = $rule;
            $message = $this->messages[$code] ?? ($this->messages[strtolower($code)] ?? '');
        } else {
            $message = $rule['message'] ?? '';
            $code = $rule['code'] ?? null;
            if (!isset($code)) {
                $code = substr(static::class, strlen(__NAMESPACE__) + 1);
                $code = strtoupper($code.'_'.Str::snake($rule['method'] ?? ''));
            }
            if (isset($rule['params'])) {
                if ('refine' != $rule['method'] && 'transform' != $rule['method']) {
                    $params = is_array($rule['params'])
                    ? $this->toScalarParams($rule['params']) : [$this->toScalarParam($rule['params'])];
                }
            }
        }
        $issue = [
            'code'    => $code,
            'message' => $message,
            'path'    => $this->path,
            'input'   => $input,
            'label'   => $this->label,
        ];
        if ('INVALID_TYPE' === $code) {
            $issue['expected'] = static::TYPE;
            $issue['received'] = gettype($input);
        }
        if (isset($params)) {
            $issue['params'] = $params;
        }
        if ($this->unionChaining) {
            $issue['union_errors'] = $this->unionChaining;
        }
        $this->issues[] = $issue;
        $error = $this->getError();
        throw $error;
    }

    protected function toScalarParam($param)
    {
        return is_scalar($param) ? $param : (is_array($param) ? $this->toScalarParams($param) : gettype($param));
    }

    protected function toScalarParams($params)
    {
        $result = [];
        foreach ($params as $param) {
            $result[] = $this->toScalarParam($param);
        }
        return $result;
    }

    protected function getError()
    {
        return $this->error ??= (isset($this->topLevelType) ? $this->topLevelType->getError() : new ValidatorException([], $this));
    }

    protected function addRule($method, $message, $params = null)
    {
        $this->rules[] = [
            'method'  => $method,
            'params'  => $params,
            'message' => $message,
        ];
        return $this;
    }

    protected function addAfter($func, ...$params)
    {
        $this->after[] = [$func, $params];
        return $this;
    }

    /**
     * @template T
     * @param  T $value
     * @return T
     */
    protected function doAfter($value)
    {
        foreach ($this->after as [$func, $params]) {
            $value = $func($value, ...$params);
        }
        return $value;
    }

    public function instanceof($class, $message = '请确保%1$s是正确的实例')
    {
        return $this->addRule('instanceof', $message, $class);
    }

    /**
     * @param mixed ...$params 首个参数是过滤器类型（如text），若有后续参数将依序传给过滤器
     *                         - 具体参考 `\Suovawp\Validation\Sanitize::sanitize()`
     */
    public function sanitize(...$params)
    {
        return $this->addRule('sanitize', '', $params);
    }

    protected function sanitizeValue($value, ...$params)
    {
        return Sanitize::sanitize($value, ...$params);
    }

    public function nonempty($message = '%1$s不能留空')
    {
        return $this->addRule('nonempty', $message);
    }

    public function eq($value, $message = '请确保%1$s等于%2$s')
    {
        return $this->addRule('eq', $message, $value);
    }

    public function ne($value, $message = '%1$s不能等于%2$s')
    {
        return $this->addRule('ne', $message, $value);
    }

    public function lt($value, $message = '请确保%1$s小于%2$s')
    {
        return $this->addRule('lt', $message, $value);
    }

    public function lte($value, $message = '请确保%1$s小于等于%2$s')
    {
        return $this->addRule('lte', $message, $value);
    }

    public function gt($value, $message = '%1$s应该大于%2$s')
    {
        return $this->addRule('gt', $message, $value);
    }

    public function gte($value, $message = '%1$s应该大于等于%2$s')
    {
        return $this->addRule('gte', $message, $value);
    }

    public function same($value, $message = '%1$s应该和%2$s完全一样')
    {
        return $this->addRule('same', $message, $value);
    }

    public function different($value, $message = '%1$s不能和%2$s完全一样')
    {
        return $this->addRule('different', $message, $value);
    }

    public function enum($values, $message = '%1$s不在范围内[%2$s]')
    {
        return $this->addRule('enum', $message, [$values]);
    }

    public function in($values, $message = '%1$s不在范围内[%2$s]')
    {
        return $this->addRule('enum', $message, [$values]);
    }

    /**
     * @param callable(TV,static):bool $callback
     */
    public function refine($callback, $message = '')
    {
        return $this->addRule('refine', $message, $callback);
    }

    /**
     * 进行数据转换，同时自动禁用验证后类型转换.
     *
     * @param callable $callback
     */
    public function transform($callback)
    {
        return $this->addRule('transform', '', $callback);
    }

    /**
     * 解析失败时返回给定值
     *
     * @template T
     * @param T|callable(array{input:mixed,issues:ValidatorIssue[]}):TV $value
     */
    public function catch($value)
    {
        $this->catchValue = $value;
        $this->hasCatchValue = true;
        return $this;
    }

    /**
     * 使用严格模式：
     * - 自动剔除未知字段.
     */
    public function strict()
    {
        $this->isStrict = true;
        return $this;
    }

    /**
     * 关闭严格模式，重置回默认行为：
     * - 自动剔除未知字段.
     */
    public function strip()
    {
        $this->isStrict = false;
        return $this;
    }

    public function required()
    {
        $this->isOptional = false;
        return $this;
    }

    /**
     * 满足条件时才变成必填.
     *
     * @param string|string[]    $field    字段键名。操作符 `with,with_all,without,without_all` 依赖字段数组
     * @param string             $operator 支持的操作符：=,!=,>,>=,<,<=,in,nin,empty,nonempty,with,with_all,without,without_all
     * @param scalar|array|mixed $value
     */
    public function requiredIf($field, $operator, $value = null)
    {
        $this->requiredWhen = [$field, $operator, $value];
        return $this;
    }

    protected function isRequiredWhen($data)
    {
        [$field, $operator, $value] = $this->requiredWhen;
        switch ($operator) {
            case 'empty':
                return empty($data[$field]);
            case 'nonempty':
                return !empty($data[$field]);
            case '=':
                return isset($data[$field]) && ($data[$field] == $value);
            case '!=':
                return isset($data[$field]) && ($data[$field] != $value);
            case '>':
                return isset($data[$field]) && ($data[$field] > $value);
            case '<':
                return isset($data[$field]) && ($data[$field] < $value);
            case '>=':
                return isset($data[$field]) && ($data[$field] >= $value);
            case '<=':
                return isset($data[$field]) && ($data[$field] <= $value);
            case 'in':
                return isset($data[$field]) && in_array($data[$field], $value);
            case 'nin':
                return isset($data[$field]) && !in_array($data[$field], $value);
            case 'with':
                return Arr::some($field, fn ($k) => !empty($data[$k]));
            case 'with_all':
                return Arr::every($field, fn ($k) => !empty($data[$k]));
            case 'without':
                return Arr::some($field, fn ($k) => empty($data[$k]));
            case 'without_all':
                return Arr::every($field, fn ($k) => empty($data[$k]));
            default:
                return false;
        }
    }

    protected function doProcessBefore($data)
    {
        if (isset($this->requiredWhen)) {
            $this->isOptional = !$this->isRequiredWhen($data);
        }
    }

    /**
     * 联合类型，验证失败时使用指定的类型验证输入值.
     *
     * @param self $type 类型将在验证失败时可选地继承标题和消息
     */
    public function or(self $type)
    {
        $this->orType = $type;
        return $this;
    }

    protected function prepareUnoinNext()
    {
        $type = $this->orType;
        $this->syncBaseInfoIf($type);
        $type->unionChaining = [...$this->issues, ...$type->unionChaining];
        return $type;
    }

    protected function syncBaseInfoIf(self $type)
    {
        $type->label = $type->label ?: $this->label;
        $type->messages = $type->messages ?: $this->messages;
        if (isset($this->topLevelType)) {
            $type->relation($this->path, $this->topLevelType);
        }
        return $type;
    }

    protected function relation($path, self $top)
    {
        $this->path = $path;
        $this->topLevelType = $top;
        $this->throwable = $top->isAbortEarly;
        return $this;
    }

    /**
     * 通过特定字符串格式添加验证规则.
     *
     * @param string $string 字符串以|分隔方法名:参数,参数...
     */
    public function useStrRule($string)
    {
        $rules = explode('|', $string);
        foreach ($rules as $method) {
            if (false === strpos($method, ':')) {
                $this->{$method}();
            } else {
                [$method, $param] = explode(':', $method);
                $params = false === strpos($param, ',') ? [$param] : explode(',', $param);
                $this->{$method}(...$params);
            }
        }
        return $this;
    }
}
