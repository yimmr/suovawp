<?php

namespace Suovawp\Utils;

/**
 * @template V
 *
 * @property int $length
 */
class Strval
{
    private $value;

    /**
     * @param V $value
     */
    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __get($name)
    {
        if ('length' == $name) {
            return mb_strlen($this->value);
        }
        return null;
    }

    public function copy()
    {
        return clone $this;
    }

    public function toString()
    {
        return $this->value;
    }

    public function __toString()
    {
        return $this->value;
    }

    public function startsWith(string $substring)
    {
        return 0 === strpos($this->value, $substring);
    }

    public function endsWith(string $substring)
    {
        return substr($this->value, -strlen($substring)) === $substring;
    }

    public function startsWithAny(array $characters)
    {
        foreach ($characters as $char) {
            if ($this->startsWith($char)) {
                return true;
            }
        }
        return false;
    }

    public function endsWithAny(array $characters)
    {
        foreach ($characters as $char) {
            if ($this->endsWithAny($char)) {
                return true;
            }
        }
        return false;
    }

    public function toSnake()
    {
        $this->value = Str::snake($this->value);
        return $this;
    }

    public function toCamel($ucfirst = false)
    {
        $this->value = Str::camel($this->value, $ucfirst);
        return $this;
    }

    public function separate($delimiter = '_')
    {
        $this->value = Str::separate($this->value, $delimiter);
        return $this;
    }

    public function toLowerCase()
    {
        $this->value = mb_strtolower($this->value);
        return $this;
    }

    public function toUpperCase()
    {
        $this->value = mb_strtoupper($this->value);
        return $this;
    }

    public function ucwords(string $separators = " \t\r\n\f\v")
    {
        $this->value = ucwords($this->value, $separators);
        return $this;
    }

    public function ucfirst()
    {
        $this->value = ucfirst($this->value);
        return $this;
    }

    public function lcfirst()
    {
        $this->value = lcfirst($this->value);
        return $this;
    }

    public function trim($characters = " \n\r\t\v\0")
    {
        $this->value = trim($this->value, $characters);
        return $this;
    }

    public function ltrim($characters = " \n\r\t\v\0")
    {
        $this->value = ltrim($this->value, $characters);
        return $this;
    }

    public function rtrim($characters = " \n\r\t\v\0")
    {
        $this->value = rtrim($this->value, $characters);
        return $this;
    }

    /**
     * @param array|string $search
     * @param array|string $replace
     */
    public function replace($search, $replace, int &$count)
    {
        $this->value = str_replace($search, $replace, $this->value, $count);
        return $this;
    }

    public function split(string $delimiter, int $limit = PHP_INT_MAX)
    {
        return new Arrval(explode($delimiter, $this->value, $limit));
    }

    public function splitRaw(string $delimiter, int $limit = PHP_INT_MAX)
    {
        return explode($delimiter, $this->value, $limit);
    }

    public function concat(string $str)
    {
        $this->value .= $str;
        return $this;
    }

    public function substring(int $start, ?int $length)
    {
        $this->value = mb_substr($this->value, $start, $length);
        return $this;
    }

    public function match(string $pattern)
    {
        preg_match($pattern, $this->value, $matches);
        return $matches ?: null;
    }

    public function matchAll(string $pattern)
    {
        preg_match_all($pattern, $this->value, $matches);
        return $matches;
    }

    public function padStart(int $length, string $padString = ' ')
    {
        $this->value = str_pad($this->value, $length, $padString, STR_PAD_LEFT);
        return $this;
    }

    public function padEnd(int $length, string $padString = ' ')
    {
        $this->value = str_pad($this->value, $length, $padString, STR_PAD_RIGHT);
        return $this;
    }

    public function repeat(int $count)
    {
        $this->value = str_repeat($this->value, $count);
        return $this;
    }

    public function charAt(int $index)
    {
        return mb_substr($this->value, $index, 1);
    }

    public function charCodeAt(int $index)
    {
        return ord(mb_substr($this->value, $index, 1));
    }
}
