<?php

namespace Suovawp;

use Suovawp\Contracts\Context as ContractsContext;
use Suovawp\Database\Schema;
use Suovawp\Exceptions\AppException;
use Suovawp\Http\Request;
use Suovawp\Http\Response;
use Suovawp\Routing\LayerEngine;
use Suovawp\Routing\Router;
use Suovawp\Utils\SingletonTrait;
use Suovawp\Utils\Strval;
use Suovawp\Utils\URL;
use Suovawp\Validation\Types\Any;
use Suovawp\Validation\ValidatorException;

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
 * @property State  $state    自定义状态数据集
 * @property Admin  $admin    管理员界面辅助实例，仅后台可用
 * @property URL    $url      当前请求的URL
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
        $this->container->bind(Request::class, fn () => Request::createFromGlobals());
        $this->container->bind(Response::class);
        $this->container->bind(Config::class);
        $this->container->bind('wpoption', Option::class);
        $this->container->bind(State::class);
        $this->container->bind(Assets::class);
        if ($this->isAdmin = is_admin()) {
            $this->container->bind(Admin::class);
        }
        Schema::setContainer($this->container);
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
            case 'state':
                return $this->container->get(State::class);
            case 'admin':
                return $this->isAdmin ? $this->container->get(Admin::class) : null;
            case 'url':
                return $this->request->url;
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

    public function isAjax()
    {
        return wp_doing_ajax() || wp_is_json_request() || (defined('REST_REQUEST') && REST_REQUEST);
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

    /**
     * @template T
     * @param T|Any                                $schema
     * @param array|null                           $data    未提供时使用`request->all()`参数
     * @param callable(ValidatorException, static) $onError
     */
    public function validated(Any $schema, $data = null, $onError = null)
    {
        $result = $schema->safeParse($data ?? $this->request->all());
        if ($result['success']) {
            return $result['data'];
        }
        if ($onError) {
            return $onError($result['error'], $this);
        }
        $this->notFound();
    }

    public function getPageError()
    {
        return AppException::$lastError;
    }

    public function notFound($body = 'Page not found')
    {
        return $this->error(404, $body);
    }

    /**
     * @param int $status 调试模式下非400-599HTTP状态码会抛普通异常
     *
     * @throws AppException|\Exception
     */
    public function error($status, $body = null)
    {
        if ($this->isDev() && (!is_numeric($status) || $status < 400 || $status > 599)) {
            throw new \Exception("HTTP error status codes must be between 400 and 599 — {$status} is invalid");
        }
        throw new AppException($status, $body);
    }

    /**
     * @param int|array|mixed $data 整数会变成`[status=>$data]`
     */
    public function wpError(string $code = '', string $message = '', $data = 400)
    {
        return new \WP_Error($code, $message, is_int($data) ? ['status' => $data] : $data);
    }

    /**
     * @param int|array|mixed $data
     * @param int|null        $status
     */
    public function apiError(string $code = '', string $message = '', $data = 400, $status = null)
    {
        if (is_array($data)) {
            $data['status'] ??= $status ?? 400;
        } elseif (is_int($data)) {
            $data = ['status' => $data];
        } else {
            $data = ['status' => 400, 'errors' => $data];
        }
        return new \WP_Error($code, $message, $data);
    }

    public function apiSuccess($data = null, string $message = 'ok')
    {
        // rest_ensure_response();
        return ['status' => 'success', 'code' => 200, 'message' => $message, 'data' => $data];
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
            $this->loadLayerModelIf($this->layerEngine->getId(), $this->layerEngine->getPage(), false);
        }
        $this->nextLayer();
    }

    protected function loadLayerModelIf($routeId, $page = 'page.php', $withNode = true)
    {
        if ('/' != $routeId) {
            $segments = explode('/', trim($routeId, '/'));
            $keys = [];
            if ($withNode) {
                $node = $this->router->root;
                foreach ($segments as $segment) {
                    $node = $node->children[$segment];
                    if (isset($node->param)) {
                        $keys[] = 'id' == $node->param ? 'detail' : $node->param;
                    } else {
                        $keys[] = $segment;
                    }
                }
            } else {
                $keys = $segments;
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
    public function redirect($url, array $params = [], $status = 302, $by = 'WordPress')
    {
        if (0 === strpos($url, '/')) {
            $url = $this->router->url(home_url(), $url, $params);
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

    public function param($name, $default = '')
    {
        return $this->request->params($name, $default);
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

    public function state(string $key, $default = null)
    {
        return $this->state->get($key, $default);
    }

    public function json($body, $status = null, $options = 0)
    {
        return wp_send_json($body, $status, $options);
    }

    public function performance()
    {
        return $this->container->singleton(Performance::class, $this);
    }
}
