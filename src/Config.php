<?php

namespace Suovawp;

use Suovawp\Utils\ArrAccessTrait;
use Suovawp\Utils\DataAccessorTrait;

class Config implements \ArrayAccess
{
    use DataAccessorTrait,ArrAccessTrait;

    protected $baseDir;

    private $loaded = [];

    public function setBasedir(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '\/');
    }

    protected function beforeHas($key, $keys = null)
    {
        $this->load($key);
    }

    protected function beforeGet($key, $keys = null)
    {
        $this->load($key);
    }

    protected function afterForget($key, $keys = null)
    {
        // 首次加载后如果进行删除，可能是不需要再加载的，如果需要重新加载可调用其他方法
        // $this->loaded[$key] = false;
    }

    /**
     * 从配置文件加载配置
     * - 未设置基础目录、已加载或文件不存在时忽略
     * - 若文件不存在，多次调用不会重新加载，除非显式设置 $reload.
     *
     * @param string $name   与文件关联的标识符：
     *                       - 相对于配置目录下的路径
     *                       - 不带扩展名、不带前缀/
     *                       - 使用/分隔目录
     * @param bool   $reload 是否重新加载文件
     */
    public function load(string $name, $reload = false)
    {
        if (!isset($this->baseDir)) {
            return;
        }
        if (!$reload && ($this->loaded[$name] ?? false)) {
            return;
        }
        $basename = str_replace('/', \DIRECTORY_SEPARATOR, $name);
        $file = $this->baseDir.\DIRECTORY_SEPARATOR.$basename.'.php';
        if (file_exists($file)) {
            $this->set($name, include $file);
        }
        $this->loaded[$name] = true;
    }
}
