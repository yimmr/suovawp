<?php

namespace Suovawp\Database;

use Suovawp\Utils\DataFormatTrait;
use Zerofoam\Utils\Func;

/**
 * @template D
 * @template O
 */
abstract class Model
{
    use DataFormatTrait;

    protected $data = [];

    protected $changed = [];

    /** @var Schema */
    protected $schema;

    protected $idKey = 'id';

    /** @var int */
    protected $id;

    protected $exists = false;

    protected $instances = [];

    protected $binds = [];

    protected $delayProps = [];

    /**
     * @param D|array $data
     * @param O|array $options
     */
    public function __construct(array $data = [], array $options = [])
    {
        $this->data = $data;
        $this->exists = $options['exists'] ?? false;
        if ($options['schema'] ?? null) {
            $this->schema = $options['schema'];
            $this->idKey = $this->schema::ID;
        }
        $this->handleCreated();
    }

    protected function handleCreated()
    {
    }

    public function __get($name)
    {
        if (isset($this->instance[$name])) {
            return $this->instance[$name];
        }
        if (isset($this->binds[$name])) {
            return $this->instance($name);
        }
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    public function exists()
    {
        return $this->exists;
    }

    public function getId()
    {
        return $this->id ??= (int) $this->get($this->idKey, 0);
    }

    public function getCreatedAt($format = '')
    {
        if (empty($this->schema) || !$this->schema::CREATED_AT) {
            return '';
        }
        $value = $this->get($this->schema::CREATED_AT, '');
        return $value && $format ? Func::dateTimeFormat($format, $value) : $value;
    }

    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
        $this->changed[$key] = $key;
        return $this;
    }

    public function fill($data)
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * 保存或创建模型对应的数据库行
     * - 默认用Schema保存，可覆盖完全自定义，实现一样的效果即可.
     */
    public function save()
    {
        if (!$schema = $this->schema) {
            return false;
        }
        $pk = $schema::ID;
        $pkValue = $this->get($pk);
        $data = $this->data;
        unset($data[$pk]);
        if (!$pkValue || !$schema::has($pkValue)) {
            $id = $schema::create($data);
            if ($id) {
                $this->data[$pk] = $id;
            }
            return (bool) $id;
        }
        // 还没想法，先全部更新吧
        // $changedData = array_intersect_key($data, $this->changed);
        return false !== $schema::updateOr(
            [
                'where' => [$pk => $pkValue],
                'data'  => $data,
            ],
            function () use ($pk, $data, $schema, $pkValue) {
                if ($schema::has($pkValue)) {
                    return true;
                }
                $id = $schema::create($data);
                if ($id) {
                    $this->data[$pk] = $id;
                }
                return (bool) $id;
            }
        );
    }

    /** 重新从数据库加载数据 */
    public function reload()
    {
        if (!$schema = $this->schema) {
            return false;
        }
        $pk = $schema::ID;
        $pkValue = $this->get($pk);
        if (!$pkValue) {
            return false;
        }
        $data = $schema::where([$pk => $pkValue])->findFirst();
        if (!$data) {
            return false;
        }
        $this->fill((array) $data);
        $this->changed = [];
        return true;
    }

    /**
     * 关联另一个单例.
     *
     * @template C
     * @param  string $prop 唯一键名，也用作魔术属性名，访问属性时自动实例化
     * @return C
     */
    protected function instance(string $prop)
    {
        if (isset($this->instances[$prop])) {
            return $this->instances[$prop];
        }
        $class = $this->binds[$prop];
        $instance = is_string($class) ? new $class($this->getId(), $this) : $class($this->getId(), $this);
        $this->instances[$prop] = $instance;
        return $instance;
    }

    /**
     * @param string                                  $prop  唯一键名，也用作魔术属性名，访问属性时自动实例化
     * @param string|\Closure(int $id, static $model) $class 关联的类名或创建实例的回调
     */
    protected function bind($prop, $class)
    {
        $this->binds[$prop] = $class;
        return $this;
    }
}
