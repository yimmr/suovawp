<?php

namespace Suovawp\Http;

use Suovawp\Utils\URL;

/**
 * @property 'GET'|'POST'                              $method
 * @property URL                                       $url
 * @property string                                    $pathname
 * @property string                                    $headers
 * @property string                                    $body
 * @property string                                    $referrer
 * @property 'follow'|'error'|'manual'                 $redirect
 * @property string                                    $referrerPolicy
 * @property string                                    $cache
 * @property string                                    $destination
 * @property 'cors'|'no-cors'|'same-origin'|'navigate' $mode
 * @property string                                    $signal
 * @property array                                     $params
 */
class Request
{
    protected $url;
    protected $pathname;
    protected $body;
    protected $headers;
    protected $cache;
    protected $destination;
    protected $method;
    protected $mode = 'cors';
    protected $redirect;
    protected $referrer;
    protected $referrerPolicy;
    protected $signal;
    protected $params = [];
    protected $bodyParams = [];
    protected $query = [];
    protected $files = [];
    protected $cookies = [];
    protected $server = [];
    protected $allParams;

    public static function createFromGlobals()
    {
        $url = home_url($_SERVER['REQUEST_URI']);
        if (!$url) {
            $host = $_SERVER['HTTP_HOST'];
            $protocol = isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http';
            $url = $protocol."://$host$_SERVER[REQUEST_URI]";
        }
        if (!empty($_SERVER['FRAGMENT'])) {
            $url .= '#'.$_SERVER['FRAGMENT'];
        }
        $request = new static($url, [
            'method' => $_SERVER['REQUEST_METHOD'],
        ]);
        $request->bodyParams = $_POST;
        $request->query = $_GET;
        $request->files = $_FILES;
        $request->cookies = $_COOKIE;
        $request->server = $_SERVER;
        return $request;
    }

    public function __construct($url = '', $options = [])
    {
        $this->url = new URL($url);
        $this->pathname = $this->url->pathname;
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function all(?string $key = null, $default = '')
    {
        $this->allParams ??= $this->bodyParams + $this->query + $this->params;
        return isset($key) ? ($this->allParams[$key] ?? $default) : $this->allParams;
    }

    public function body(?string $key = null, $default = '')
    {
        return isset($key) ? ($this->bodyParams[$key] ?? $default) : $this->bodyParams;
    }

    public function query(?string $key = null, $default = '')
    {
        return isset($key) ? ($this->query[$key] ?? $default) : $this->query;
    }

    public function params(?string $key = null, $default = '')
    {
        return isset($key) ? ($this->params[$key] ?? $default) : $this->params;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setParams($params)
    {
        $this->params = $params;
    }

    public function server(?string $key = null, $default = null)
    {
        return isset($key) ? ($this->server[$key] ?? $default) : $this->server;
    }

    public function cookie(?string $key = null, $default = '')
    {
        return isset($key) ? ($this->cookies[$key] ?? $default) : $this->cookies;
    }

    public function ip()
    {
        return (string) filter_var($this->server('REMOTE_ADDR', ''), FILTER_VALIDATE_IP);
    }

    public function formData()
    {
    }

    public function json()
    {
    }

    public function text()
    {
    }
}
