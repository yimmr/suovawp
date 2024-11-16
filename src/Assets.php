<?php

namespace Suovawp;

class Assets
{
    public $version;

    /** @var Vite|null */
    public $vite;

    public $viteOutDirname = 'assets';

    public $vitePublicUrlFunc;

    private $scriptIds = [];

    private $styleIds = [];

    private $unloadScripts = [];

    private $unloadStyles = [];

    private $beforeCallbacks = [];

    private $afterCallbacks = [];

    private $optimizeCallbacks = [];

    private $optionEnabled = false;

    /** @var string */
    private $optionVarname;

    private $options = [];

    private $optionFileMap = [];

    private $optionDepId = 'jquery-core';

    private $optionLoader;

    public function __clone()
    {
        $this->vite = null;
    }

    public function copy()
    {
        return clone $this;
    }

    public function getThemeVersion()
    {
        return (string) wp_get_theme()->get('Version');
    }

    public function register()
    {
        is_admin() ? $this->adminRegister() : $this->frontendRegister();
    }

    public function frontendRegister($optimizepos = 99, $pos = 10, $preloadpos = 1)
    {
        add_action('wp_head', [$this, 'preload'], $preloadpos);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts'], $pos);
        add_action('wp_enqueue_scripts', [$this, 'optimizeScripts'], $optimizepos);
    }

    public function adminRegister($optimizepos = 99, $pos = 10, $preloadpos = 1)
    {
        add_action('admin_head', [$this, 'preload'], $preloadpos);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts'], $pos);
        add_action('admin_enqueue_scripts', [$this, 'optimizeScripts'], $optimizepos);
    }

    /**
     * 如果当前未设置entry，则使用提供的入口作为默认.
     */
    public function defaultEntry($entry)
    {
        return $this->entry($entry);
    }

    /**
     * 指定Vite入口文件，所有相关依赖都会自动引入.
     *
     * @param string $entry
     */
    public function entry($entry)
    {
        $this->vite ??= new Vite($this->viteOutDirname);
        $this->vite->setEntry($entry);
        if ($this->vitePublicUrlFunc) {
            $this->vite->setPublicUrlFunc($this->vitePublicUrlFunc);
        }
        return $this;
    }

    public function setViteOutDirname(string $dirname)
    {
        $this->viteOutDirname = $dirname;
        return $this;
    }

    public function setVitePublicUrlFunc(string $funcName)
    {
        $this->vitePublicUrlFunc = $funcName;
        return $this;
    }

    /** 获取主入口标识符 */
    public function getHandle()
    {
        return isset($this->vite) ? $this->vite->getHandle() : $this->optionDepId;
    }

    public function preload()
    {
        if (isset($this->vite)) {
            $this->vite->preload();
        }
    }

    public function enqueueScripts()
    {
        foreach ($this->beforeCallbacks as $callback) {
            $callback($this);
        }
        foreach ($this->styleIds as $id) {
            wp_enqueue_style($id);
        }
        foreach ($this->scriptIds as $id) {
            wp_enqueue_script($id);
        }
        if (isset($this->vite)) {
            $this->vite->enqueueScripts();
        }
        $this->localizeOptions($this->getHandle());
        foreach ($this->afterCallbacks as $callback) {
            $callback($this);
        }
        $this->styleIds = $this->scriptIds = [];
        $this->afterCallbacks = $this->beforeCallbacks = [];
    }

    public function optimizeScripts()
    {
        foreach ($this->unloadScripts as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }
        foreach ($this->unloadStyles as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
        foreach ($this->optimizeCallbacks as $callback) {
            $callback($this);
        }
    }

    /** 添加WP媒体库依赖 */
    public function media(array $args = [])
    {
        return $this->before(fn () => wp_enqueue_media($args));
    }

    /** 添加wp国际化脚本依赖 */
    public function i18n()
    {
        $this->scriptIds[] = 'wp-i18n';
        return $this;
    }

    /**
     * 添加JS依赖.
     *
     * @param string[] $handles
     */
    public function script(...$handles)
    {
        foreach ($handles as $handle) {
            $this->scriptIds[] = $handle;
        }
        return $this;
    }

    /**
     * 添加CSS依赖.
     *
     * @param string[] $handles
     */
    public function style(...$handles)
    {
        foreach ($handles as $handle) {
            $this->styleIds[] = $handle;
        }
        return $this;
    }

    /**
     * 添加css和js依赖.
     *
     * @param string[] $handles
     */
    public function bundle(...$handles)
    {
        foreach ($handles as $handle) {
            $this->scriptIds[] = $handle;
            $this->styleIds[] = $handle;
        }
        return $this;
    }

    /**
     * 延迟执行，卸载并移除已注册的JS.
     */
    public function unloadScript(...$handles)
    {
        foreach ($handles as $handle) {
            $this->unloadScripts[] = $handle;
        }
        return $this;
    }

    /**
     * 延迟执行，卸载并移除已注册的CSS.
     */
    public function unloadStyle(...$handles)
    {
        foreach ($handles as $handle) {
            $this->unloadStyles[] = $handle;
        }
        return $this;
    }

    /**
     * 注册引入资源前执行回调.
     *
     * @param callable(static) $callback
     */
    public function before(callable $callback)
    {
        $this->beforeCallbacks[] = $callback;
        return $this;
    }

    /**
     * 注册引入资源后执行回调.
     *
     * @param callable(static) $callback
     */
    public function after(callable $callback)
    {
        $this->afterCallbacks[] = $callback;
        return $this;
    }

    /**
     * 注册执行资源优化的回调.
     *
     * @param callable(static) $callback
     */
    public function optimize(callable $callback)
    {
        $this->optimizeCallbacks[] = $callback;
        return $this;
    }

    public function inlineScript(string $script, $position = 'after')
    {
        return $this->after(fn () => wp_add_inline_script($this->getHandle(), $script, $position));
    }

    public function inlineStyle(string $data)
    {
        return $this->after(fn () => wp_add_inline_style($this->getHandle(), $data));
    }

    /**
     * 本地化已注册的选项.
     */
    protected function localizeOptions($handle = '')
    {
        if (!$this->optionEnabled) {
            return;
        }
        if (isset($this->optionLoader)) {
            foreach ($this->optionFileMap as $name => $basename) {
                if ('localize' != ($basename ?? $name)) {
                    $this->options[$name] ??= $this->loadOption($name, $basename);
                }
            }
            $this->options += $this->loadOption('localize');
        }
        wp_localize_script($handle, $this->optionVarname, $this->options);
        $this->options = [];
    }

    /**
     * @param string|null $basename
     */
    public function loadOption(string $name, $basename = null)
    {
        return ($this->optionLoader)($name, $basename);
    }

    /**
     * 注册从配置文件自动加载的选项，已有同名选项时不加载，`localize.php` 文件默认是全局选项.
     *
     * @param string      $name     选项名称，前端访问方式：optionVarname.name
     * @param string|null $basename 无扩展名的文件名称，未提供时使用$name
     */
    public function option(string $name, $basename = null)
    {
        $this->optionFileMap[$name] = $basename;
        return $this;
    }

    /** 设置或覆盖一个本地化选项.  */
    public function setOption(string $name, $value)
    {
        $this->options[$name] = $value;
        return $this;
    }

    public function disableOption()
    {
        $this->optionEnabled = false;
        return $this;
    }

    public function enableOption()
    {
        $this->optionEnabled = true;
        return $this;
    }

    /** 设置选项的JS变量名 */
    public function setOptionVarname(string $varname)
    {
        $this->optionVarname = $varname;
        return $this;
    }

    /** 设置选项的JS依赖，默认是jquery-core，另外vite入口不需设置. */
    public function setOptionHandle(string $handle)
    {
        $this->optionDepId = $handle;
        return $this;
    }

    /**
     * 设置选项加载器.
     *
     * @param callable(string $name, ?string $basename): array $loader 接收选项名和无扩展名的文件名
     */
    public function setOptionLoader($loader)
    {
        $this->optionLoader = $loader;
        return $this;
    }
}
