<?php

namespace Suovawp\Utils;

class Promise
{
    private $state = 'pending';
    private $value;
    private $callbacks = [];

    public function then($onFulfilled = null, $onRejected = null)
    {
        $this->callbacks[] = [$onFulfilled, $onRejected];
        if ('pending' !== $this->state) {
            $this->execute();
        }
        return $this;
    }

    public function resolve($value)
    {
        if ('pending' === $this->state) {
            $this->state = 'fulfilled';
            $this->value = $value;
            $this->execute();
        }
    }

    public function reject($reason)
    {
        if ('pending' === $this->state) {
            $this->state = 'rejected';
            $this->value = $reason;
            $this->execute();
        }
    }

    private function execute()
    {
        foreach ($this->callbacks as $callback) {
            $handler = 'fulfilled' === $this->state ? $callback[0] : $callback[1];
            if (is_callable($handler)) {
                call_user_func($handler, $this->value);
            }
        }
        $this->callbacks = [];
    }
}
