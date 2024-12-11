<?php

namespace Suovawp;

use Suovawp\Utils\ArrAccessTrait;
use Suovawp\Utils\DataAccessorTrait;

class Option implements \ArrayAccess
{
    use DataAccessorTrait,ArrAccessTrait;

    private $name;

    private $vendor;

    private $optionNames;

    private $defaultLoader;

    /**
     * @param string $vendor 数据库储存的 `option_name` 前缀，设计模式：每个前缀关联一个类的实例
     */
    public function __construct(string $vendor = '')
    {
        $this->vendor = $vendor;
    }

    /** 不能使用此方法设置所有选项. */
    public function setValue(array $value)
    {
        return $this;
    }

    protected function beforeHas($key, $keys = null)
    {
        $this->load($key);
    }

    protected function beforeGet($key, $keys = null)
    {
        $this->load($key);
    }

    /**
     * 设置加载默认值的回调函数，在数据库不存在顶级选项时调用 .
     */
    public function setDefaultLoader(\Closure $loader)
    {
        $this->defaultLoader = $loader;
        return $this;
    }

    public function load(string $name, $reload = false)
    {
        if (!$reload && $this->isFirstKeySet($name)) {
            return;
        }
        $value = get_option($this->getFullOptionName($name));
        if (false === $value && $this->defaultLoader) {
            $value = call_user_func($this->defaultLoader, $name);
        }
        $this->value[$name] = $value;
    }

    /**
     * 一次性加载所有选项到对象中
     * - 如果没有预设前缀，可能会加载大量数据，未预设前缀时请勿调用此方法.
     */
    public function loadAll($reload = false)
    {
        if ($reload || isset($this->optionNames)) {
            return;
        }
        global $wpdb;
        $sql = "SELECT option_name FROM {$wpdb->options}";
        if ($this->vendor) {
            $sql = $wpdb->prepare("$sql WHERE option_name LIKE %s", $this->vendor.'_%');
        }
        $optionNames = $wpdb->get_col($sql);
        $optionNames = $optionNames && is_array($optionNames) ? $optionNames : [];
        foreach ($optionNames as $optionName) {
            $name = $this->vendor ? substr($optionName, strlen($this->vendor) + 1) : $optionName;
            $this->load($name, $reload);
        }
        $this->optionNames = $optionNames;
    }

    /**
     * 加载并获取所有选项，一般用于调试目的
     * - 如果没有预设前缀，可能会加载大量数据，未预设前缀时请勿调用此方法.
     */
    public function getAll()
    {
        $this->loadAll();
        return $this->value;
    }

    /**
     * 更新选项，持久化入库.
     *
     * @param string $name 选项名称（如果实例已预设vendor前缀，就不需带此前缀）
     */
    public function update(string $name, $value, $autoload = null)
    {
        return update_option($this->getFullOptionName($name), $value, $autoload);
    }

    /** 添加选项，同`add_option`，已预设vendor时不需前缀 */
    public function add(string $name, $value, $deprecated = '', $autoload = 'yes')
    {
        return add_option($this->getFullOptionName($name), $value, $deprecated, $autoload);
    }

    /** 从数据库删除选项 */
    public function delete(string $name)
    {
        return delete_option($this->getFullOptionName($name));
    }

    /**
     * 删除数据库中所有带此vendor前缀的选项，未预设前缀则不执行任何操作.
     */
    public function deleteAll()
    {
        if (empty($this->vendor)) {
            return;
        }
        global $wpdb;
        $like = $this->vendor.'_%';
        $sql = $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like);
        return $wpdb->query($sql);
    }

    /** 返回数据库实际储存的完整选项名称 */
    public function getFullOptionName($name)
    {
        return empty($this->vendor) ? $name : $this->vendor.'_'.$name;
    }
}
