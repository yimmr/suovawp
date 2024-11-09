<?php

namespace Suovawp\Http;

/**
 * @property 'GET'|'POST'                              $method
 * @property string                                    $url
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
    protected $params;

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
        return $this->$name;
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
