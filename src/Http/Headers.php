<?php

namespace Suovawp\Http;

/**
 * @template H
 */
class Headers
{
    /** @var H|array<string,string> */
    protected $headers;

    protected $originalKeys = [];

    /**
     * @param H|array<string,string> $init
     */
    public function __construct(array $init = [])
    {
        foreach ($init as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function has(string $name)
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * @template T of key-of<H>
     *
     * @param T $name
     *
     * @return H[T]|null
     */
    public function get(string $name)
    {
        if (isset($this->headers[$name])) {
            return $this->headers[$name];
        }
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }

    public function set(string $name, $value)
    {
        $lowerName = strtolower($name);
        $this->headers[$lowerName] = $value;
        $this->originalKeys[$lowerName] = $name;
    }

    public function append(string $name, $value)
    {
        $name = strtolower($name);
        $this->headers[$name] = isset($this->headers[$name]) ? $this->headers[$name].', '.$value : $value;
    }

    public function delete(string $name)
    {
        $name = strtolower($name);
        unset($this->headers[$name], $this->originalKeys[$name]);
    }

    /**
     * @param callable(H[key-of<H>],key-of<H>,static): bool $callback
     */
    public function forEach($callback)
    {
        foreach ($this->headers as $name => $value) {
            $callback($value, $name, $this);
        }
    }

    /**
     * @return (key-of<H>)[]
     */
    public function keys()
    {
        return array_keys($this->headers);
    }

    /**
     * @return string[]
     */
    public function values()
    {
        return array_values($this->headers);
    }

    /**
     * @return string[]
     */
    public function getSetCookie()
    {
        $value = $this->get('set-cookie');
        if (is_null($value)) {
            return [];
        }
        return array_map('trim', explode(',', $value));
    }

    public function getIterator()
    {
        foreach ($this->headers as $name => $value) {
            yield $name => $value;
        }
    }

    /**
     * @return H 返回小写$name的headers数组
     */
    public function toArray()
    {
        return $this->headers;
    }

    /**
     * @return H 返回原始$name的headers数组
     */
    public function raw()
    {
        $result = [];
        foreach ($this->headers as $name => $value) {
            $result[$this->originalKeys[$name]] = $value;
        }
        return $result;
    }

    public function __toString()
    {
        $string = '';
        foreach ($this->headers as $name => $value) {
            $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
            $string .= $name.': '.$value."\r\n";
        }
        return $string."\r\n";
    }
}
