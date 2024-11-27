<?php

namespace Suovawp\Database;

/**
 * @template E
 * @template D
 * @template O
 *
 * @extends Model<D, O>
 */
class EntityProxy extends Model
{
    /** @var E|null */
    protected $entity;

    /**
     * @param E $entity
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
    }

    public function get($key, $default = null)
    {
        if (isset($this->entity->{$key})) {
            return $this->entity->{$key};
        }
        return parent::get($key, $default);
    }

    public function save()
    {
    }

    public function resetExists()
    {
        $object = $this->fetchEntity();
        $this->exists = is_object($object);
        $this->entity = $object;
        return $this->exists;
    }

    protected function fetchEntity()
    {
        return get_post($this->getId());
    }
}
