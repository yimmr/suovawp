<?php

namespace Suovawp\Database;

abstract class Meta
{
    abstract protected function getMeta(string $key = '', $single = false);

    abstract protected function addMeta(string $key, $value, $unique = false);

    abstract protected function updateMeta(string $key, $value, $prevValue = '');

    abstract protected function deleteMeta(string $key, $value = '');

    /** @var int */
    protected $id;

    protected $cache = [];

    protected $setedKeys = [];

    protected $alias = [];

    protected $extendAlias = [];

    public function __construct($id)
    {
        $this->id = (int) $id;
        $this->alias = $this->extendAlias + $this->alias;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    protected function realKey($key)
    {
        return $this->alias[$key] ?? $key;
    }

    public function get(string $key, $default = null)
    {
        $key = $this->realKey($key);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $value = $this->getMeta($key, true);
        $value = '' !== $value ? $value : $default;
        $this->cache[$key] = $value;
        return $value;
    }

    public function getMany(string $key, array $default = []): array
    {
        $key = $this->realKey($key);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $value = $this->getMeta($key, false);
        $value = is_array($value) && $value ? $value : $default;
        $this->cache[$key] = $value;
        return $value;
    }

    public function add(string $key, $value, $unique = false)
    {
        return $this->addMeta($this->realKey($key), $value, $unique);
    }

    public function update(string $key, $value, $prevValue = '')
    {
        return $this->updateMeta($this->realKey($key), $value, $prevValue);
    }

    public function delete(string $key, $value = '')
    {
        $key = $this->realKey($key);
        unset($this->cache[$key]);
        return $this->deleteMeta($key, $value);
    }

    /**
     * 先暂存对象中，并为实际储存，取值时取设置的值，需要手动调用save保存.
     */
    public function set(string $key, $value)
    {
        $key = $this->realKey($key);
        $this->cache[$key] = $value;
        $this->setedKeys[] = $key;
        return $this;
    }

    public function save()
    {
        foreach ($this->setedKeys as $key) {
            $this->updateMeta($key, $this->cache[$key]);
        }
        $this->setedKeys = [];
    }

    public function fill(array $data)
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /** 仅反回特定类型需要的meta键，用于批量获取，默认是从别名列表中的值，可覆盖此方法添加其他值 */
    public function getKeys()
    {
        return array_values($this->alias);
    }

    /** 返回 `getKeys()` 的所有meta，有缓存则不会重新读取 */
    public function all()
    {
        $value = [];
        foreach ($this->getKeys() as $key) {
            $value[$key] = $this->get($key);
        }
        return $value;
    }

    /** 等同于 `all()`，但转换键为别名键 */
    public function allAndTransform()
    {
        $value = [];
        $alias = array_flip($this->alias);
        foreach ($this->getKeys() as $key) {
            $value[$alias[$key] ?? $key] = $this->get($key);
        }
        return $value;
    }

    /** 返回数据库中所有meta  */
    public function allRaw()
    {
        return (array) $this->getMeta('');
    }

    public function toArray()
    {
        return $this->cache;
    }

    public function clean()
    {
        $this->cache = [];
        $this->setedKeys = [];
    }
}
