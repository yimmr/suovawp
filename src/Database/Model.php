<?php

namespace Suovawp\Database;

/**
 * @template D
 * @template O
 */
abstract class Model
{
    protected $data = [];

    protected $changed = [];

    /** @var Schema */
    protected $schema;

    protected $idKey = 'id';

    /** @var int */
    protected $id;

    /**
     * @param D|array $data
     * @param O|array $options
     */
    public function __construct(array $data = [], array $options = [])
    {
        $this->data = $data;
        if ($options['schema'] ?? null) {
            $this->schema = $options['schema'];
            $this->idKey = $this->schema::ID;
        }
    }

    public function getId()
    {
        return $this->id ??= (int) $this->get($this->idKey, 0);
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
}
