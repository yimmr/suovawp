<?php

namespace Suovawp\Database;

use Suovawp\Utils\DataFormatTrait;

/**
 * 实现目标：
 * - 管理元数据的CURD，轻松获取特定格式的数据
 * - 统一键名（通过别名机制实现，实现私有、公开键的统一访问）
 * - 同时实现键名常量，外部可直接使用一个固定key，通过别名映射实际key.
 */
abstract class Meta
{
    use DataFormatTrait;

    /** @var int */
    protected $id;

    /** @var string 类型，如post、user、comment等 */
    protected $type;

    /** @var string 无前缀表名，如postmeta */
    protected $table;

    protected $cache = [];

    protected $setedKeys = [];

    protected $alias = [];

    /** 子类若需要继承父类的所有meta，可用此属性定义别名 */
    protected $extendAlias = [];

    protected static $instances = [];

    /** 预定义强制转换的元数据类型，仅支持原始键 */
    protected $schema = [];

    public function __construct($id)
    {
        $this->id = (int) $id;
        $this->alias = $this->extendAlias + $this->alias;
        $this->handleCreated();
    }

    /** 初始化完成后执行自定义代码 */
    protected function handleCreated()
    {
    }

    /** 仅获取真实key的临时解决方案 */
    public static function k($key)
    {
        $instance = static::$instances[static::class] ??= new static(0);
        return $instance->key($key);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /** 获取实际的metaKey */
    public function key($key)
    {
        return $this->alias[$key] ?? $key;
    }

    /** @uses self::getMeta()  */
    public function get(string $key, $default = null)
    {
        $key = $this->key($key);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $value = $this->getMeta($key, true);
        $value = '' !== $value ? $value : $default;
        $this->cache[$key] = $value;
        return $value;
    }

    /** @uses self::addMeta()  */
    public function add(string $key, $value, $unique = false)
    {
        return $this->addMeta($this->key($key), $value, $unique);
    }

    /** @uses self::updateMeta()  */
    public function update(string $key, $value, $prevValue = '')
    {
        return $this->updateMeta($this->key($key), $value, $prevValue);
    }

    /** @uses self::deleteMeta()  */
    public function delete(string $key, $value = '')
    {
        $key = $this->key($key);
        unset($this->cache[$key]);
        return $this->deleteMeta($key, $value);
    }

    /**
     * 获取拥有多行记录的meta.
     *
     * @uses self::getMeta()
     */
    public function getMany(string $key, array $default = []): array
    {
        $key = $this->key($key);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $value = $this->getMeta($key, false);
        $value = is_array($value) && $value ? $value : $default;
        $this->cache[$key] = $value;
        return $value;
    }

    /**
     * 更新拥有多行记录的meta，先删除所有记录，再添加新记录.
     *
     * @uses self::deleteMany()
     * @uses self::add()
     */
    public function updateMany($key, $values)
    {
        $this->deleteMany($key);
        $result = [];
        foreach ($values as $value) {
            $result[] = $this->add($key, $value, false);
        }
        return $result;
    }

    /**
     * 删除拥有多行记录的meta.
     *
     * @uses delete_metadata()
     */
    public function deleteMany(string $key, $value = '')
    {
        $key = $this->key($key);
        unset($this->cache[$key]);
        return delete_metadata($this->type, $this->id, $key, $value, true);
    }

    /** 删除数据库中对象id的所有元数据 */
    public function deleteAll()
    {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix.$this->table, [$this->getTypeColumn() => $this->id]);
    }

    protected function getTypeColumn()
    {
        return $this->type.'_id';
    }

    /**
     * 先暂存对象中，并为实际储存，取值时取设置的值，需要手动调用save保存.
     */
    public function set(string $key, $value)
    {
        $key = $this->key($key);
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

    public function getCast($key, $default = null)
    {
        $key = $this->key($key);
        if (!isset($this->schema[$key])) {
            return $this->get($key, $default);
        }
        $keySchema = $this->schema[$key];
        $type = $keySchema['type'] ?? 'string';
        switch ($type) {
            case 'array':
                return $this->array($key, $default ?? ($keySchema['default'] ?? []));
            case 'boolean':
                return $this->boolean($key, $default ?? ($keySchema['default'] ?? false));
            case 'integer':
                return $this->integer($key, $default ?? ($keySchema['default'] ?? 0));
            case 'numeric':
                return $this->numeric($key, $default ?? ($keySchema['default'] ?? 0));
            default:
                return $this->string($key, $default ?? ($keySchema['default'] ?? ''));
        }
    }

    /**
     * 把原始key的元数据数组转为预设的别名key.
     */
    public function transform(array $metadata)
    {
        $alias = array_flip($this->alias);
        $value = [];
        foreach ($metadata as $key => $value) {
            $value[$alias[$key] ?? $key] = $value;
        }
        return $value;
    }

    /** 返回别名和没有别名的原始键，仅类本身定义的键，可覆盖此方法添加其他值. */
    public function keys()
    {
        return array_keys($this->alias);
    }

    /** 返回实际存储的键，仅类本身定义的键，可覆盖此方法添加其他值. */
    public function metaKeys()
    {
        return array_values($this->alias);
    }

    /** 未实现！返回别名和没有别名的原始键，类和父类定义的键. */
    public function allKeys()
    {
        return $this->getKeys();
    }

    /** 未实现！ 返回实际存储的键，类和父类定义的键. */
    public function allMetaKeys()
    {
        return $this->metaKeys();
    }

    /** 仅反回特定类型需要的meta键，用于批量获取，默认是从别名列表中的值，可覆盖此方法添加其他值 */
    public function getKeys()
    {
        return array_values($this->alias);
    }

    /** 等同于 `all()`，但转换键为别名键 */
    public function allAndTransform()
    {
        $alias = array_flip($this->alias);
        $value = [];
        foreach ($this->getKeys() as $key) {
            $value[$alias[$key] ?? $key] = $this->getCast($key);
        }
        return $value;
    }

    /** 返回 `getKeys()` 的所有meta，有缓存则不会重新读取 */
    public function all()
    {
        $value = [];
        foreach ($this->getKeys() as $key) {
            $value[$key] = $this->getCast($key);
        }
        return $value;
    }

    /** 仅更新 `getKeys()` 列出的所有meta，自动识别别名键（原始键和别名键可混合使用） */
    public function updateAll(array $data)
    {
        $value = [];
        $aliasFlip = array_flip($this->alias);
        foreach ($this->getKeys() as $key) {
            // 提供的数据可能是别名key，需要转换为原始key
            if (isset($data[$key]) || (($key = $aliasFlip[$key] ?? '') && isset($data[$key]))) {
                $value[$key] = $this->update($key, $data[$key]);
            }
        }
        return $value;
    }

    /** 返回数据库中所有meta，未转换别名key  */
    public function allMeta()
    {
        return (array) $this->getMeta('');
    }

    /** 返回已加载的meta数组，未转换别名key */
    public function toArray()
    {
        return $this->cache;
    }

    public function clean()
    {
        $this->cache = [];
        $this->setedKeys = [];
    }

    /**
     * @return mixed 返回值情况
     *               - $single=false返回值数组，$single=true返回元字段的值；
     *               - 如果$id无效（非数字、零或负值），返回 false；
     *               - 如果$id有效但实体不存在，返回 空字符串
     */
    protected function getMeta(string $key = '', $single = false)
    {
        return get_metadata($this->type, $this->id, $key, $single);
    }

    /** @return int|false 添加成功返回元数据 ID；失败返回 false */
    protected function addMeta(string $key, $value, $unique = false)
    {
        return add_metadata($this->type, $this->id, $key, $value, $unique);
    }

    /** @return int|bool 键不存在时返回元数据 ID；更新成功返回true；更新失败或数据库已有相同值返回false。 */
    protected function updateMeta(string $key, $value, $prevValue = '')
    {
        return update_metadata($this->type, $this->id, $key, $value, $prevValue);
    }

    /** @return bool */
    protected function deleteMeta(string $key, $value = '')
    {
        return delete_metadata($this->type, $this->id, $key, $value, false);
    }
}
