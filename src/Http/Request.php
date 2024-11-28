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

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        $uri = false !== ($pos = strpos($uri, '?')) ? substr($uri, 0, $pos) : $uri;
        $this->pathname = $uri;
        $this->params = [];
    }

    public function __get($name)
    {
        if ('url' == $name) {
            $this->url ??= new URL($this->getCurrentURL());
        }
        return $this->$name;
    }

    protected function getCurrentURL()
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
        return $url;
    }

    public function getParam(string $name, $default = '')
    {
        return $this->params[$name] ?? $default;
    }

    public function getParams($name = null)
    {
        return $this->params;
    }

    public function setParams($params)
    {
        $this->params = $params;
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
