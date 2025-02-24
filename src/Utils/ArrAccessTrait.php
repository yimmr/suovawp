<?php

namespace Suovawp\Utils;

if (PHP_VERSION_ID >= 80000) {
    trait ArrAccessTrait
    {
        public function offsetExists(mixed $offset): bool
        {
            return isset($this->value[$offset]);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->value[$offset];
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->value[$offset] = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->value[$offset]);
        }
    }
} else {
    trait ArrAccessTrait
    {
        public function offsetExists($offset)
        {
            return isset($this->value[$offset]);
        }

        public function offsetGet($offset)
        {
            return $this->value[$offset];
        }

        public function offsetSet($offset, $value)
        {
            $this->value[$offset] = $value;
        }

        public function offsetUnset($offset)
        {
            unset($this->value[$offset]);
        }
    }
}
