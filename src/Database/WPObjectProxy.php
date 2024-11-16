<?php

namespace Suovawp\Database;

/**
 * @template W
 * @template M
 * @template D
 * @template O
 *
 * @property M $meta
 *
 * @extends Model<D, O>
 */
class WPObjectProxy extends Model
{
    public const META_CLASS = '';

    /** @var W|null */
    protected $wpObject;

    /** @var M */
    protected $metaModel;

    public function __construct($object = [], $options = [])
    {
        if ($object instanceof \WP_Post) {
            $this->wpObject = $object;
            $object = [];
        } elseif (!is_array($object)) {
            $object = (array) $object;
        }
        parent::__construct($object, $options);
        $metaClass = static::META_CLASS;
        $this->metaModel = new $metaClass($this->getId());
    }

    public function __get($name)
    {
        if ('meta' == $name) {
            return $this->metaModel;
        }
        return null;
    }

    public function get($key, $default = null)
    {
        if (isset($this->wpObject->{$key})) {
            return $this->wpObject->{$key};
        }
        return parent::get($key, $default);
    }

    public function save()
    {
    }
}
