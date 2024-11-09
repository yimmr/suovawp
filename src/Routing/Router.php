<?php

namespace Suovawp\Routing;

/**
 * @template O of array
 *
 * @property TrieNode $root
 * @property string   $rootDir
 * @property O        $options
 * @property bool     $inited
 * @property O['param_validator'] $paramValidator
 */
class Router
{
    protected $root;

    protected $rootDir;

    protected $options;

    protected $inited = false;

    protected $paramValidator;

    protected $callbackCache = [];

    protected $maxMatchDepth = 100;

    /**
     * @param O|array{cache_enabled?:bool,cache_dir?:string,cache_trie?:bool,param_validator?:class-string} $options
     */
    public function __construct($rootDir = '', $options = [])
    {
        if (is_array($rootDir)) {
            $options = $rootDir;
            $rootDir = $options['root'] ?? '';
        }
        $this->rootDir = $rootDir;
        $this->options = $options;
        $this->paramValidator = $options['param_validator'] ?? null;
    }

    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * 在手动添加或匹配路由前需先调用此方法，若不需匹配路由则不调用.
     */
    public function boot()
    {
        if ($this->inited) {
            return;
        }
        $this->inited = true;
        if ($this->options['cache_enabled'] ?? false && !empty($this->options['cache_dir'])) {
            $cacheDir = $this->options['cache_dir'];
            $file = $cacheDir.DIRECTORY_SEPARATOR.'router.routes.php';
            if (file_exists($file)) {
                $routes = require $file;
            } else {
                $routes = $this->generateRoutes();
                if ($routes) {
                    $this->saveCache($file, $routes);
                }
            }
            $this->buildTrie($routes ?: []);
        } else {
            $this->buildTrie($this->generateRoutes());
        }
    }

    protected function buildTrie($routes)
    {
        $this->root = new TrieNode();
        foreach ($routes as $path) {
            $this->add($path, $path);
        }
    }

    public function saveCache($file, $data)
    {
        if (!is_writable($file)) {
            return;
        }
        $resource = fopen($file, 'w');
        fwrite($resource, '<?php return '.var_export($data, true).';');
        fclose($resource);
    }

    public function cleanCache()
    {
        if (empty($this->options['cache_dir'])) {
            return;
        }
        $dir = $this->options['cache_dir'];
        @unlink($dir.DIRECTORY_SEPARATOR.'router.routes.php');
    }

    public function generateRoutes($path = '', &$routes = [])
    {
        $dir = $this->rootDir.($path ? DIRECTORY_SEPARATOR.$path : '');
        $files = scandir($dir);
        $hasEndpoint = null;
        foreach ($files as $file) {
            if ('.' == $file || '..' == $file) {
                continue;
            }
            $subfile = $dir.DIRECTORY_SEPARATOR.$file;
            if (is_dir($subfile)) {
                $this->generateRoutes($path ? $path.'/'.$file : $file, $routes);
            } else {
                $hasEndpoint ??= ('page.php' == $file || 'server.php' == $file) ? true : null;
            }
        }
        if ($hasEndpoint) {
            $routes[] = '/'.$path;
        }
        return $routes;
    }

    protected function isOptionalSeg($segment)
    {
        return 0 === strpos($segment, '[[') && ']]' === substr($segment, -2);
    }

    public function bootMatch($path)
    {
        $this->boot();
        return $this->match($path);
    }

    public function match($path)
    {
        return $this->trieMatch($path);
    }

    public function add(string $path, $handler)
    {
        $node = $this->root;
        if ('/' !== $path) {
            $lastParent = null;
            $segments = explode('/', trim($path, '/'));
            foreach ($segments as $segment) {
                if (!isset($node->children[$segment])) {
                    $child = new TrieNode();
                    if ('' != $segment && '[' == $segment[0] && ']' == $segment[-1]) {
                        $keyProps = 'paramKeys';
                        $param = substr($segment, 1, -1);
                        if ('' != $param && '[' == $param[0] && ']' == $param[-1]) {
                            $param = substr($param, 1, -1);
                            $keyProps = 'optionalKeys';
                            $child->optional = true;
                            $lastParent = $node;
                        }
                        if (0 === strpos($param, '...')) {
                            $param = substr($param, 3);
                            $keyProps = 'wildcardKeys';
                            $child->optional = true;
                            $lastParent = $node;
                        }
                        if (false !== ($pos = strpos($param, '='))) {
                            $child->callback = substr($param, $pos + 1);
                            $param = substr($param, 0, $pos);
                        }
                        $child->param = $param;
                        $node->hasParamChild = true;
                        if (isset($child->callback)) {
                            array_unshift($node->{$keyProps}, $segment);
                        } else {
                            $node->{$keyProps}[] = $segment;
                        }
                    }
                    $node->children[$segment] = $child;
                }
                $node = $node->children[$segment];
            }
            if ('/' == $path[-1]) {
                $node->tsr = true;
            }
            if (isset($lastParent) && $node->optional) {
                if (!isset($lastParent->handler)) {
                    $lastParent->handler = $handler;
                    $lastParent->ref = $path;
                }
                $lastParent = null;
            }
        }
        $node->id = $path;
        $node->handler = $handler;
    }

    public function trieMatch($path)
    {
        $node = $this->root;
        $params = [];
        $tsr = false;
        if ('/' !== $path) {
            $segments = explode('/', trim($path, '/'));
            $node = $this->matchNode($node, $segments, 0, $params);
            if (!isset($node,$node->handler)) {
                return null;
            }
            if ('/' == $path[-1]) {
                $tsr = !$node->tsr;
            }
        } elseif (!isset($node->handler)) {
            return null;
        }
        return (object) [
            'id'      => $node->id,
            'handler' => $node->handler,
            'params'  => $params,
            'tsr'     => $tsr,
        ];
    }

    /**
     * @param TrieNode $node
     * @param string[] $segments
     * @param int      $index
     * @param array    $params
     */
    protected function matchNode($node, $segments, $index, &$params)
    {
        if ($index > $this->maxMatchDepth) {
            return null;
        }
        if (!isset($segments[$index])) {
            return isset($node->handler) ? $node : null;
        }
        $segment = $segments[$index];
        $nextIdx = $index + 1;
        if (isset($node->children[$segment]) && !isset($node->children[$segment]->param)) {
            $match = $this->matchNode($node->children[$segment], $segments, $nextIdx, $params);
            if ($match) {
                return $match;
            }
        }
        if (!$node->hasParamChild) {
            return null;
        }
        foreach ($node->paramKeys as $id) {
            $subNode = $node->children[$id];
            $value = $segment;
            if (isset($subNode->callback) && !($this->parseCallback($subNode->callback))($value)) {
                continue;
            }
            $match = $this->matchNode($subNode, $segments, $nextIdx, $params);
            if ($match) {
                $params[$subNode->param] = $value;
                return $match;
            }
        }
        foreach ($node->optionalKeys as $id) {
            $subNode = $node->children[$id];
            $value = $segment;
            if (isset($subNode->callback) && !($this->parseCallback($subNode->callback))($value)) {
                continue;
            }
            $match = $this->matchNode($subNode, $segments, $nextIdx, $params);
            if ($match) {
                $params[$subNode->param] = $value;
                return $match;
            }
            $match = $this->matchNode($subNode, $segments, $index, $params);
            if ($match) {
                $params[$subNode->param] = null;
                return $match;
            }
        }
        $segs = array_slice($segments, $index);
        $match = null;
        foreach ($node->wildcardKeys as $id) {
            $subNode = $node->children[$id];
            $value = $segs;
            $match = $subNode;
            if (!empty($subNode->children)) {
                $newValues = [];
                foreach ($value as $i => $seg) {
                    $match = $this->matchNode($subNode, $segs, $i, $params);
                    if ($match) {
                        break;
                    }
                    $newValues[] = $seg;
                }
                if (!$match && !isset($subNode->handler)) {
                    continue;
                }
                $value = $newValues;
            }
            if (isset($subNode->callback)) {
                $callback = $this->parseCallback($subNode->callback);
                $newValues = [];
                foreach ($value as $val) {
                    if (!$callback($val)) {
                        continue 2;
                    }
                    $newValues[] = $val;
                }
                $value = $newValues;
            }
            if ($match) {
                $params[$subNode->param] = $value;
                return $match;
            }
        }
        return null;
    }

    protected function parseCallback($callback)
    {
        if (isset($this->callbackCache[$callback])) {
            return $this->callbackCache[$callback];
        }
        if (isset($this->paramValidator) && method_exists($this->paramValidator, $callback)) {
            $callback = $this->paramValidator.'::'.$callback;
            $this->callbackCache[$callback] = $callback;
        }
        return $callback;
    }

    /**
     * 通过路由构建URL.
     *
     * @param string               $origin
     * @param string               $path
     * @param array<string,string> $params 参数键值对，未使用的参数用做查询参数
     *                                     - null或不存在的键跳过可选段，若是必填则停止解析，返回已解析的路径
     *                                     - 扩展参数段可以是数组或字符串，空数组则跳过
     */
    public function url($origin, $path, $params = [])
    {
        $urlPath = '';
        $segments = explode('/', trim($path, '/'));
        foreach ($segments as $segment) {
            $wildcard = $optional = false;
            if ('' != $segment && '[' == $segment[0] && ']' == $segment[-1]) {
                $param = substr($segment, 1, -1);
                if ('' != $param && '[' == $param[0] && ']' == $param[-1]) {
                    $param = substr($param, 1, -1);
                    $optional = true;
                }
                if (0 === strpos($param, '...')) {
                    $param = substr($param, 3);
                    $wildcard = $optional = true;
                }
                if (false !== ($pos = strpos($param, '='))) {
                    $param = substr($param, 0, $pos);
                }
                if (!isset($params[$param])) {
                    if ($optional) {
                        continue;
                    }
                    break;
                }
                $segment = $params[$param];
                unset($params[$param]);
                if ($wildcard && is_array($segment)) {
                    if (empty($segment)) {
                        continue;
                    }
                    $segment = implode('/', $segment);
                }
            }
            $urlPath .= '/'.$segment;
        }
        if ($params) {
            $urlPath = $urlPath ?: '/';
            $urlPath .= '?'.http_build_query($params);
        }
        return rtrim($origin, '/').$urlPath;
    }
}
