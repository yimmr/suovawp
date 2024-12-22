<?php

namespace Suovawp\Routing;

use Suovawp\Exceptions\AppException;

class LayerEngine
{
    private $id;

    private $root;

    private $subdir = '';

    private $subdirs = [];

    /** @var string[] */
    private $layers = [];

    private $index = 0;

    private $page = 'page.php';

    private $started = false;

    private $tailCallback;

    private $checkPage = true;

    public function __construct(string $root, string $routeId)
    {
        $this->root = $root;
        $this->id = $routeId;
        if ('/' != $this->id) {
            $this->subdirs = explode('/', trim($this->id, '/'));
            $this->subdir = DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $this->subdirs);
        }
    }

    /**
     * 执行流：
     * - 若有通用server.php文件，先加载.
     * - 若有页面文件则构建视图层
     */
    public static function start(string $root, string $routeId)
    {
        $engine = new self($root, $routeId);
        $engine->server();
        $engine->buildLayersIf();
        return $engine;
    }

    /** 使用不同页面文件名来构建视图层引擎的便捷方法 */
    public static function forward(string $root, string $path)
    {
        $basename = basename($path);
        if (false !== strpos($basename, '.')) {
            $page = $basename;
            $path = dirname($path);
            $server = pathinfo($basename, PATHINFO_FILENAME);
            $server = 'page' === $server ? 'server' : $server.'.server';
        }
        $engine = new self($root, $path);
        if (isset($page)) {
            $engine->setPage($page);
        }
        $engine->server($server ?? 'server');
        $engine->buildLayersIf();
        return $engine;
    }

    public function isStarted()
    {
        return $this->started;
    }

    public function isEmpty()
    {
        return empty($this->layers);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getPage()
    {
        return $this->page;
    }

    /** 自定义终点页面文件名 */
    public function setPage($page)
    {
        $this->page = $page;
        return $this;
    }

    protected function server($name = 'server')
    {
        $file = $this->root.$this->subdir.DIRECTORY_SEPARATOR.$name.'.php';
        if (file_exists($file)) {
            $this->started = true;
            $this->includeFile($file, function ($error) {
                $this->checkPage = false;
                $this->tailCallback = function () use ($error) {
                    throw $error;
                };
            });
        }
    }

    protected function buildLayersIf()
    {
        if (!empty($this->layers)) {
            return;
        }

        if ($this->checkPage && !file_exists($this->root.$this->subdir.DIRECTORY_SEPARATOR.$this->page)) {
            return;
        }
        $this->buildLayers();
    }

    protected function buildLayers()
    {
        $files = [];
        $dir = $this->root;
        // 必需一个根布局
        $files[] = $dir.DIRECTORY_SEPARATOR.'layout.php';
        foreach ($this->subdirs as $subdir) {
            $dir .= DIRECTORY_SEPARATOR.$subdir;
            $layout = $dir.DIRECTORY_SEPARATOR.'layout.php';
            if (file_exists($layout)) {
                $files[] = $layout;
            }
        }
        // 必需一个页面
        $files[] = $dir.DIRECTORY_SEPARATOR.$this->page;
        $this->layers = $files;
    }

    /**
     * 第一次调用引入第一层，模板文件内再调用下一层，上层才能捕获下层异常.
     */
    public function next()
    {
        $this->started = true;
        if (!isset($this->layers[$this->index])) {
            return;
        }
        $file = $this->layers[$this->index];
        ++$this->index;
        if (isset($this->tailCallback) && $this->index == count($this->layers)) {
            $this->execCallback($this->tailCallback, $file);
        } else {
            $this->includeFile($file);
        }
    }

    protected function includeFile($file, $fallback = null)
    {
        try {
            require_once $file;
        } catch (AppException $error) {
            if (isset($fallback)) {
                $fallback($error, $file);
            } else {
                $this->appErrorFallback($error, $file);
            }
        }
    }

    protected function execCallback($callback, $file)
    {
        try {
            $callback($file);
        } catch (AppException $error) {
            $this->appErrorFallback($error, $file);
        }
    }

    protected function appErrorFallback(AppException $error, $file)
    {
        $dir = dirname($file).DIRECTORY_SEPARATOR;
        if ('404' == $error->getCode() && file_exists($file = $dir.'not-found.php')) {
            require $file;
        } elseif (file_exists($file = $dir.'error.php')) {
            require $file;
        } else {
            throw $error;
        }
    }

    /**
     * 设置将替代最后一层的回调函数，可用于抛异常或自定义渲染.
     *
     * @param callable(string $file):void $callback
     */
    public function tail($callback)
    {
        $this->tailCallback = $callback;
    }
}
