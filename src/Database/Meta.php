<?php

namespace Suovawp\Database;

use Suovawp\Utils\Arr;
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

    /** @var string 无前缀表名，如postmeta */
    protected $table;

    /** @var string|'post'|'user'|'term'|'comment' 类型 */
    protected $type;

    /** @var string 若有则是对象的子类型 */
    protected $subType;

    protected $customTable = false;

    /** @var array{string,mixed} 以原始meta键做缓存键 */
    protected $data = [];

    protected $setedKeys = [];

    protected static $instances = [];

    /**
     * 实例属性和meta结构映射，选项与WP注册meta相似，额外支持一些参数：
     * - key 如果属性名与元数据键不同，则指定原始键
     * - cast 读写时强制转换的回调，以@开头的字符使用类内置转换器.
     * - type 未指定时默认是字符串.
     *
     * @var array<string,array{
     *  key:string,
     *  cast:string|callable,
     *  object_subtype:string,
     *  type: 'string'|'boolean'|'integer'|'number'|'array'|'object',
     *  label:string,
     *  description:string,
     *  single:boolean,
     *  default:mixed,
     *  sanitize_callback:callable,
     *  auth_callback:callable,
     *  show_in_rest:boolean|array,
     *  revisions_enabled:boolean,
     * }>
     **/
    protected static $fields = [];

    private static $classSetupMap = [];

    private $missing = [];

    public static function migrate(array $map, int $id = 0)
    {
        global $wpdb;
        $meta = new static($id);
        $table = $wpdb->prefix.$meta->table;
        $where = $id ? ' AND '.$meta->objectIdField().' = '.($id) : '';
        foreach ($map as $old => $new) {
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET meta_key = %s WHERE meta_key = %s".$where, $meta->k($new), $old));
        }
    }

    protected static function setupFields()
    {
        if (isset(self::$classSetupMap[static::class])) {
            return;
        }
        static::compose();
        self::$classSetupMap[static::class] = true;
    }

    /** 注册元数据 */
    protected static function compose()
    {
    }

    /**
     * 从其他类提取字段.
     *
     * @param class-string<Meta> $meta
     */
    protected static function extend($meta, $pick = null, $omit = null)
    {
        $meta::setupFields();
        $fields = $meta::$fields;
        if ($pick) {
            $fields = Arr::pick($fields, $pick);
        }
        if ($omit) {
            $fields = Arr::omit($fields, $omit);
        }
        static::$fields = static::$fields + $fields;
    }

    protected function objectIdField()
    {
        return $this->type.'_id';
    }

    public function __construct($id)
    {
        $this->id = (int) $id;
        add_filter('default_'.$this->type.'_metadata', [$this, 'setMissing'], 10, 3);
        static::setupFields();
        if ($this->customTable) {
            global $wpdb;
            $key = $this->type.'meta';
            $wpdb->$key = $wpdb->prefix.$this->table;
        }
    }

    public function setMissing($value, $object_id, $meta_key)
    {
        $this->missing[$meta_key] = true;
        return $value;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /** @uses self::getMeta()  */
    public function get(string $key, $default = null, $single = true)
    {
        $metaKey = $this->metaKey($key);
        if (isset($this->data[$metaKey])) {
            return $this->data[$metaKey];
        }
        $value = $this->getMeta($metaKey, $single);
        if (isset($this->missing[$metaKey])) {
            $value = $default;
        }
        if (!$single) {
            $value = is_array($value) && $value ? $value : $default;
        }
        $value = $this->castToSchema($value, $key);
        $this->data[$metaKey] = $value;
        return $value;
    }

    /**
     * 获取拥有多行记录的meta.
     *
     * @uses self::getMeta()
     */
    public function getMany(string $key, array $default = []): array
    {
        return $this->get($key, $default, false);
    }

    /** @uses self::addMeta()  */
    public function add(string $key, $value, $unique = false)
    {
        // $value = $this->prepareSave($key, $value);
        return $this->addMeta($this->metaKey($key), $value, $unique);
    }

    /** @uses self::addMeta()  */
    public function addIfNotExists(string $key, $value, $unique = false)
    {
        if ($this->exists($key, $value)) {
            return true;
        }
        return $this->add($key, $value, $unique);
    }

    /** @uses self::updateMeta()  */
    public function update(string $key, $value, $prevValue = '')
    {
        $value = $this->prepareSave($key, $value);
        $metaKey = $this->metaKey($key);
        unset($this->data[$metaKey]);
        return $this->updateMeta($metaKey, $value, $prevValue);
    }

    /**
     * 更新拥有多行记录的meta，先删除所有记录，再添加新记录.
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

    /** @uses self::deleteMeta()  */
    public function delete(string $key, $value = '')
    {
        $metaKey = $this->metaKey($key);
        unset($this->data[$metaKey]);
        return $this->deleteMeta($metaKey, $value);
    }

    /**
     * 删除拥有多行记录的meta.
     *
     * @uses delete_metadata()
     */
    public function deleteMany(string $key, $value = '', $deleteAll = false)
    {
        $metaKey = $this->metaKey($key);
        unset($this->data[$metaKey]);
        return delete_metadata($this->type, $this->id, $metaKey, $value, $deleteAll);
    }

    /** 删除数据库中对象id的所有元数据 */
    public function deleteAll()
    {
        global $wpdb;
        $this->data = [];
        return $wpdb->delete($wpdb->prefix.$this->table, [$this->objectIdField() => $this->id]);
    }

    /** 更新已注册的所有元数据（$data数组可混合使用原始键和别名键，自动忽略未注册的键） */
    public function updateAll(array $data)
    {
        $value = [];
        foreach ($this->keys() as $key) {
            $dataKey = $key;
            if (isset($data[$dataKey]) || isset($data[$dataKey = $this->metaKey($key)])) {
                $value[$key] = $this->isSingle($key)
                    ? $this->update($key, $data[$dataKey])
                    : $this->updateMany($key, $data[$dataKey]);
            }
        }
        return $value;
    }

    public function exists($key, $value = null)
    {
        global $wpdb;
        $metaKey = $this->metaKey($key);
        $table = $wpdb->prefix.$this->table;
        $sql = "SELECT 1 FROM {$table} WHERE post_id = %d AND meta_key = %s";
        if (isset($value)) {
            $sql .= ' AND meta_value = %s';
            $sql = $wpdb->prepare($sql, $this->id, $metaKey, $value);
        } else {
            $sql = $wpdb->prepare($sql, $this->id, $metaKey);
        }
        return $wpdb->get_var($sql) ? true : false;
    }

    /** 获取已注册的所有元数据，有缓存则不会重新读取 */
    public function all()
    {
        $value = [];
        foreach ($this->keys() as $key) {
            $value[$key] = $this->isSingle($key) ? $this->get($key) : $this->getMany($key);
        }
        return $value;
    }

    /**
     * @param string $key 属性名非元数据键
     */
    protected function prepareSave(string $key, $value)
    {
        $value = $this->castToSchema($value, $key);
        return $value;
    }

    protected function castToSchema($value, string $key)
    {
        if (!isset(static::$fields[$key])) {
            return $value;
        }
        if (isset(static::$fields[$key]['cast'])) {
            $cast = static::$fields[$key]['cast'];
            if (is_string($cast) && '@' === $cast[0]) {
                $cast = substr($cast, 1);
                $value = MetaCaster::$cast($value);
            } else {
                $value = $cast($value);
            }
        }
        $value = MetaCaster::coerce($value, static::$fields[$key]['type'] ?? 'string');
        return $value;
    }

    /** 仅更新设置对象中的值，需要手动调用save保存. */
    public function fill(array $data)
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /** 仅更新设置对象中的值，需要手动调用save保存. */
    public function set(string $key, $value)
    {
        $this->data[$this->metaKey($key)] = $value;
        $this->setedKeys[] = $key;
        return $this;
    }

    /** 保存已变更的值到数据库 */
    public function save()
    {
        foreach ($this->setedKeys as $key) {
            $this->update($key, $this->data[$this->metaKey($key)]);
        }
        $this->setedKeys = [];
    }

    public function isSingle(string $key)
    {
        return static::$fields[$key]['single'] ?? true;
    }

    /** 返回实例已注册的属性名数组. */
    public function keys()
    {
        return array_keys(static::$fields);
    }

    /** 从属性名获取真实元数据键，确保一致性 */
    public static function k($key)
    {
        static::setupFields();
        return static::$fields[$key]['key'] ?? $key;
    }

    /** 获取metaKey，未注册或与属性相同时返回原值 */
    public function metaKey(string $key): string
    {
        return static::$fields[$key]['key'] ?? $key;
    }

    /** 返回实例已注册的`meta_key`数组. */
    public function metaKeys()
    {
        $metaKeys = [];
        foreach (static::$fields as $key => $options) {
            $metaKeys[] = $options['key'] ?? $key;
        }
        return $metaKeys;
    }

    /**
     * 获取对象在数据表中所有的`meta_key`数组.
     *
     * @param  bool     $resetCache 本次查询是否重置缓存
     * @return string[] 返回元数据键数组，未设置表名时返回空数组
     */
    public function allMetaKeys($resetCache = false)
    {
        global $wpdb;
        if (!$this->table) {
            return [];
        }
        $group = $this->table.'_all_meta_keys';
        if (!$resetCache) {
            $keys = wp_cache_get($this->id, $group, false, $found);
            if ($found && is_array($keys)) {
                return $keys;
            }
        }
        $table = $wpdb->prefix.$this->table;
        $column = $this->objectIdField();
        $keys = $wpdb->get_col("SELECT meta_key FROM {$table} WHERE {$column} = {$this->id} GROUP BY meta_key");
        $keys = is_array($keys) ? $keys : [];
        wp_cache_set($this->id, $keys, $group);
        return $keys;
    }

    /** 获取对象在数据表中所有的元数据  */
    public function allMeta()
    {
        return (array) $this->getMeta('');
    }

    /**
     * 把原始key的元数据数组转为预设的别名key.
     */
    public function transform(array $metadata)
    {
        $value = [];
        foreach ($metadata as $key => $value) {
            $value[$this->metaKey($key)] = $value;
        }
        return $value;
    }

    /** 返回已加载的meta数组，未转换别名key */
    public function toArray()
    {
        return $this->all();
    }

    public function clean()
    {
        $this->data = [];
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

    /**
     * @param array $args 元数据参数，留空则从$fields中获取
     *                    - revisions_enabled 仅支持'post'对象类型
     *                    - auth_callback 回调在增删改元数据时调用
     */
    protected function registerMeta(string $key, array $args = [])
    {
        $args += static::$fields[$key] ?? [];
        if ($this->subType && !isset($args['object_subtype'])) {
            $args['object_subtype'] = $this->subType;
        }
        return register_meta($this->type, $this->metaKey($key), $args);
    }
}
