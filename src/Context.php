<?php

namespace Suovawp;

use Suovawp\Contracts\Context as ContractsContext;
use Suovawp\Exceptions\AppException;
use Suovawp\Http\Request;
use Suovawp\Http\Response;
use Suovawp\Routing\LayerEngine;
use Suovawp\Routing\Router;
use Suovawp\Utils\SingletonTrait;
use Suovawp\Utils\Strval;

/**
 * @property array    $params
 * @property Request  $request
 * @property Response $response
 * @property Router   $router
 * @property object{id:string,params:array,
 * handler:string,tsr:bool}|null $route    路由匹配时非null
 * @property Assets $assets
 * @property Config $config
 * @property Option $option   自定义的选项
 * @property Option $wpoption 用于读写无预设前缀的选项，如wordpress内置选项
 * @property Admin  $admin    管理员界面辅助实例，仅后台可用
 */
class Context implements ContractsContext
{
    use SingletonTrait;

    /** @var class-string */
    public const LAYER_REGISTRY = '';

    public $configDir = '';

    // 未实现
    public $cookies;

    protected $route;

    /** @var Strval */
    protected $routeId;

    /** @var LayerEngine */
    protected $layerEngine;

    /** @var Container */
    protected $container;

    /** @var bool 延迟设置 */
    protected $isRestRequest;

    protected $isAdmin = false;

    public function __construct()
    {
        static::$instance = $this;
        $this->container = new Container();
        $this->container->instance(static::class, $this);
        $this->container->bind(static::class, null, ContractsContext::class);
        $this->container->alias(self::class, static::class);
        $this->container->bind(Request::class);
        $this->container->bind(Response::class);
        $this->container->bind(Config::class);
        $this->container->bind('wpoption', Option::class);
        $this->container->bind(Assets::class);
        if ($this->isAdmin = is_admin()) {
            $this->container->bind(Admin::class);
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'params':
                return $this->request->params;
            case 'request':
                return $this->container->get(Request::class);
            case 'response':
                return $this->container->get(Response::class);
            case 'router':
                return $this->container->get('router');
            case 'route':
                return $this->route;
            case 'config':
                return $this->container->get(Config::class);
            case 'assets':
                return $this->container->get(Assets::class);
            case 'option':
                return $this->container->get('option');
            case 'wpoption':
                return $this->container->get('wpoption');
            case 'admin':
                return $this->isAdmin ? $this->container->get(Admin::class) : null;
            default:
                return null;
        }
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function setHeaders()
    {
    }

    public function isDebugging()
    {
        return defined('WP_DEBUG') && \WP_DEBUG;
    }

    public function isDev()
    {
        return $this->isDebugging();
    }

    public function isRest()
    {
        if (isset($this->isRestRequest)) {
            return $this->isRestRequest;
        }
        if ((defined('REST_REQUEST') && REST_REQUEST)
            || (!empty($_GET['rest_route']) && '/' === $_GET['rest_route'][0])) {
            return $this->isRestRequest = true;
        }
        $restBase = parse_url(rest_url(), PHP_URL_PATH);
        $result = $restBase && 0 === strpos(trim($_SERVER['REQUEST_URI'], '/').'/', trim($restBase, '/').'/');
        return $this->isRestRequest = $result;
    }

    public function notFound($body = 'Page not found')
    {
        return $this->error(404, $body);
    }

    public function error($status, $body = null)
    {
        if ($this->isDev() && (!is_numeric($status) || $status < 400 || $status > 599)) {
            throw new \Exception("HTTP error status codes must be between 400 and 599 — {$status} is invalid");
        }
        throw new AppException($status, $body);
    }

    public function wpError($code = '', $message = '', $data = 403)
    {
        return new \WP_Error($code, $message, is_int($data) ? ['status' => $data] : $data);
    }

    protected function startLayerEngine($id)
    {
        $this->layerEngine = LayerEngine::start($this->router->rootDir, $id);
        if (!$this->layerEngine->isEmpty()) {
            $this->loadLayerModelIf($id);
        }
    }

    public function nextLayer()
    {
        isset($this->layerEngine) && $this->layerEngine->next();
    }

    public function forwardLayer($path)
    {
        if (isset($this->layerEngine) && $this->layerEngine->isStarted()) {
            wp_die('forwardLayer() can only be called before the first layer is rendered', 500);
        }
        $this->layerEngine = LayerEngine::forward($this->router->rootDir, $path);
        if (!$this->layerEngine->isEmpty()) {
            $this->loadLayerModelIf($this->layerEngine->getId(), $this->layerEngine->getPage());
        }
        $this->nextLayer();
    }

    protected function loadLayerModelIf($routeId, $page = 'page.php')
    {
        if ('/' != $routeId) {
            $segments = explode('/', trim($routeId, '/'));
            $node = $this->router->root;
            $keys = [];
            foreach ($segments as $segment) {
                $node = $node->children[$segment];
                if (isset($node->param)) {
                    $keys[] = 'id' == $node->param ? 'detail' : $node->param;
                } else {
                    $keys[] = $segment;
                }
            }
            if ('page.php' != $page) {
                $keys[] = pathinfo($page, PATHINFO_FILENAME);
            }
            $key = strtolower(implode('.', $keys));
        } else {
            $key = 'home';
        }
        if ($this->hasLayerModel($key)) {
            $this->getLayerModel($key)->loadIf();
        }
    }

    public function hasLayerModel($name)
    {
        return isset((static::LAYER_REGISTRY)::LAYERS[$name]);
    }

    protected function getLayerModel($name)
    {
        return $this->container->singleton((static::LAYER_REGISTRY)::LAYERS[$name], $this);
    }

    /**
     * @param string $url    完整URL或以/开始的路径
     *                       - 自动解析路径的动态段，去除未解析的段
     *                       - 直接传入 `$routeId` 也可
     * @param array  $params 路径参数或查询参数，路径未用的参数将充当查询参数
     */
    public function redirect($url, $params = [], $status = 302, $by = 'WordPress')
    {
        if (0 === strpos($url, '/')) {
            $url = $this->router->url($url, $params);
        } elseif ($params) {
            $url = add_query_arg($params, $url);
        }
        return wp_safe_redirect($url, $status, $by);
    }

    /**
     * 返回当前路由ID的字符串对象
     */
    public function routeId()
    {
        return $this->routeId ??= new Strval($this->route->id ?? '');
    }

    /** 指定Vite入口文件，所有相关依赖都会自动引入. */
    public function entry($entry)
    {
        return $this->assets->entry($entry);
    }

    public function config(string $key, $default = null)
    {
        return $this->config->get($key, $default);
    }

    public function option(string $key, $default = null)
    {
        return $this->option->get($key, $default);
    }

    public function wpoption(string $key, $default = null)
    {
        return $this->option->get($key, $default);
    }

    public function json($body, $status = null, $options = 0)
    {
        return wp_send_json($body, $status, $options);
    }
}
