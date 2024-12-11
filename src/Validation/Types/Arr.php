<?php

namespace Suovawp\Validation\Types;

use Suovawp\Utils\Attachment;
use Suovawp\Utils\Entity;

/**
 * @template TV
 * @template TD
 * @template TSchema of Any
 * @template TCatchall of Any
 *
 * @extends Any<TV,TD>
 */
class Arr extends Any
{
    public const TYPE = 'array';

    protected $name = '数组';

    protected $isAssoc;

    /** @var TSchema[]|TSchema */
    protected $schema;

    protected $isPassthrough = false;

    /** @var TCatchall */
    protected $wildcardType;

    protected $validated = [];

    /**
     * @param TSchema[]|TSchema $schema 单个模式或指定结构模式
     *                                  - 单个模式视为索引数组，即使用此模式验证每个数组项目，返回索引数组
     *                                  - 数组模式视为关联数组，即按指定的键值对进行验证，索引数组键是序号
     */
    public function __construct($schema = [], $label = '', $messages = [])
    {
        $this->isAssoc = is_array($schema);
        $this->schema = $schema;
        parent::__construct($label, $messages);
    }

    public function is($value)
    {
        return is_array($value);
    }

    public function cast($value): array
    {
        if (is_object($value)) {
            if ($value instanceof \Traversable) {
                return iterator_to_array($value);
            }
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }
            if (method_exists($value, 'to_array')) {
                return $value->to_array();
            }
            return get_object_vars($value);
        }
        return (array) $value;
    }

    /**
     * 如果部分字段验证失败时，可通过此方法获取验证成功的部分数据.
     */
    public function getValidated()
    {
        $data = [];
        if (is_array($this->schema)) {
            foreach ($this->validated as $key => $value) {
                if ($this->schema[$key] instanceof self) {
                    $data[$key] = $this->schema[$key]->getValidated() ?: $value;
                } else {
                    $data[$key] = $value;
                }
            }
        } else {
            $data = $this->validated;
        }
        return $data;
    }

    protected function parseChildren($data)
    {
        $result = [];
        $path = $this->path ? $this->path.'.' : '';
        $top = $this->topLevelType ?? $this;
        if ($this->isAssoc) {
            $schema = $this->schema;
            if ($this->isPassthrough) {
                $wildcardType = $this->wildcardType ?: Any::create('*');
                foreach ($data as $key => $value) {
                    $schema[$key] ??= $wildcardType;
                }
            }
            foreach ($schema as $key => $type) {
                $type->isUndefined = !array_key_exists($key, $data);
                $type->label = $type->label ?: $this->label.'['.$key.']';
                $type->doProcessBefore($data);
                $value = $type->relation($path.$key, $top)->parse($data[$key] ?? null);
                if ($type->isNullable || isset($value)) {
                    $result[$key] = $value;
                }
            }
        } else {
            $label = $this->schema->label ?: $this->label;
            foreach ($data as $i => $item) {
                $schema = clone $this->schema;
                $schema->label = $label.'['.$i.']';
                $result[] = $schema->relation($path.$i, $top)->parse($item);
            }
        }
        if (isset($this->error) && !$this->error->isEmpty()) {
            $this->validated = $result;
            throw $this->error;
        }
        return $result;
    }

    public function check(&$value, $method, $params = null)
    {
        switch ($method) {
            case 'length':
                return count($value) == $params;
            case 'min':
                return count($value) >= $params;
            case 'max':
                return count($value) <= $params;
            case 'between':
                return count($value) >= $params[0] && count($value) <= $params[1];
            case 'tuple':
                return count($value) == count($this->schema);
            default:
                return parent::check($value, $method, $params);
        }
    }

    public function length($number, $message = '%1$s必须包含%2$s个')
    {
        return $this->addRule('length', $message, $number);
    }

    public function max($number, $message = '%1$s不能超过%2$s个')
    {
        return $this->addRule('max', $message, $number);
    }

    public function min($number, $message = '%1$s不能少于%2$s个')
    {
        return $this->addRule('min', $message, $number);
    }

    public function between($min, $max, $message = '%1$s数量应该在%2$s到%3$s之间')
    {
        return $this->addRule('between', $message, [$min, $max]);
    }

    /**
     * 实现类似元组的验证，数组长度和类型顺序必须和模式一致.
     */
    public function tuple($message = '%1$s不是有效的固定组合')
    {
        return $this->addRule('tuple', $message);
    }

    protected function sanitizeValue($value, $type = 'number', ...$params)
    {
        switch ($type) {
            case 'number':
                return array_filter($value, fn ($v) => Num::create()->is($v));
            case 'numeric':
                return array_filter($value, fn ($v) => is_numeric($v));
            case 'id':
                return array_filter($value, fn ($v) => Num::create()->isId($v));
            case 'attachid':
                return array_filter($value, fn ($v) => Attachment::isId($v, ...$params));
            case 'post_id':
                return Entity::postIdFilter($value);
            default:
                return parent::sanitizeValue($value, $type, ...$params);
        }
        return $this;
    }

    /**
     * 把验证成功后的值拼成字符串.
     *
     * @template T of Str
     * @param string|T $schema 可选对新值继续验证，也支持字符串规则（|分隔方法名:参数,参数...）
     */
    public function join($separator = ',', $schema = null)
    {
        return $this->addAfter(function ($value, $separator, $schema) {
            $value = implode($separator, $value);
            if (!isset($schema)) {
                return $value;
            }
            if (is_string($schema)) {
                $schema = (new Str($this->label, $this->messages))->useStrRule($schema);
            }
            $this->syncBaseInfoIf($schema);
            return $schema->parse($value);
        }, $separator, $schema);
    }

    /**
     * 索引数组的每个项目都用指定模式验证.
     *
     * @param Any $schema
     */
    public function item($schema)
    {
        $this->isAssoc = false;
        $this->schema = $schema;
        return $this;
    }

    /**
     * 返回当前模式，可用来访问特定字段的模式.
     *
     * @param  string|null                                       $key
     * @return ($key is null ? TSchema|TSchema[] : TSchema|null)
     */
    public function shape($key = null)
    {
        return null === $key ? $this->schema : ($this->schema[$key] ?? null);
    }

    /**
     * 扩展额外字段或覆盖已有字段.
     *
     * @param TSchema[] $schema
     */
    public function extend(array $schema)
    {
        if ($this->isAssoc) {
            foreach ($schema as $key => $value) {
                $this->schema[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * 合并两个类型.
     *
     * @param static $type
     */
    public function merge($type)
    {
        if ($this->isAssoc) {
            foreach ($type->schema as $key => $value) {
                $this->schema[$key] = $value;
            }
        }
        return $this;
    }

    public function pick(...$keys)
    {
        $schema = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->schema)) {
                $schema[$key] = $this->schema[$key];
            }
        }
        $newType = clone $this;
        $newType->schema = $schema;
        return $newType;
    }

    public function omit(...$keys)
    {
        $newType = clone $this;
        foreach ($keys as $key) {
            unset($newType->schema[$key]);
        }
        return $newType;
    }

    /**
     * 所有字段变为可选.
     */
    public function partial()
    {
        $newType = clone $this;
        foreach ($newType->schema as $type) {
            $type->optional();
        }
        return $newType;
    }

    /**
     * 所有字段变为可选，包括深层嵌套字段.
     */
    public function deepPartial()
    {
        $newType = clone $this;
        foreach ($newType->schema as $type) {
            if ($type instanceof self) {
                $type->deepPartial();
            } else {
                $type->optional();
            }
        }
        return $newType;
    }

    public function deepCopy()
    {
        $newType = clone $this;
        $schema = $newType->schema;
        $single = !is_array($schema);
        if ($single) {
            $schema = [$schema];
        }
        foreach ($schema as $key => $type) {
            if ($type instanceof self) {
                $schema[$key] = $type->deepCopy();
            } else {
                $schema[$key] = clone $type;
            }
        }
        $newType->schema = $single ? $schema[0] : $schema;
        return $newType;
    }

    /**
     * 保留未知字段.
     */
    public function passthrough()
    {
        $this->isPassthrough = true;
        return $this;
    }

    public function strict()
    {
        $this->isPassthrough = false;
        return parent::strict();
    }

    public function strip()
    {
        $this->isPassthrough = false;
        return parent::strip();
    }

    /**
     * 所有未知字段都用指定类型规则进行验证。
     *
     * @param TCatchall $type
     */
    public function wildcard(Any $type)
    {
        $this->isPassthrough = true;
        $this->wildcardType = $type;
        return $this;
    }

    /**
     * 只要有一个字段验证失败就终止后续字段验证.
     */
    public function abortEarly()
    {
        $this->isAbortEarly = true;
        return $this;
    }

    public function __clone()
    {
        $this->error = null;
        $this->topLevelType = null;
    }
}
