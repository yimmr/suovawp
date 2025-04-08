<?php

namespace Suovawp\Exceptions;

class AppException extends \Exception
{
    protected $body;

    /** @var static|null */
    public static $lastError;

    public function __construct($status, $body = null, $previous = null)
    {
        if (is_array($body)) {
            $this->body = $body;
        } elseif ($body) {
            $this->body = ['message' => $body];
        } else {
            $this->body = ['message' => "Error: $status"];
        }
        parent::__construct($this->body['message'] ?? '', $status, $previous);
        static::$lastError = $this;
    }

    public function __toString()
    {
        return json_encode($this->body);
    }
}
