<?php

namespace Suovawp\Http;

/**
 * @template O
 *
 * @property bool   $ok
 * @property int    $status
 * @property string $statusText
 * @property Headers<O['headers']>|O['headers']  $headers
 * @property Cookies     $cookies
 * @property string|null $body
 */
class Response
{
    protected $ok = false;
    protected $status;
    protected $statusText;
    protected $body;
    protected $headers;
    protected $cookies;

    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * @param O|array{status?:int,statusText?:string,headers?:Headers|array} $options 默认状态是200，其余是空
     */
    public function __construct($body = null, $options = [])
    {
        $this->body = $body;
        $this->status = $options['status'] ?? 200;
        $this->statusText = $options['statusText'] ?? '';
        if (isset($options['headers'])) {
            $this->headers = $options['headers'] instanceof Headers ? $options['headers'] : new Headers($options['headers']);
        } else {
            $this->headers = new Headers();
        }
        $this->cookies = new Cookies();
        $this->ok = $this->status >= 200 && $this->status < 300;
    }

    public function json()
    {
        return json_decode($this->body, true);
    }

    public function redirect($url)
    {
    }
}
