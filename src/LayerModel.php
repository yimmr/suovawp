<?php

namespace Suovawp;

/**
 * @template Ctx of Context
 */
abstract class LayerModel
{
    /** @var Ctx */
    protected $ctx;

    protected $loaded = false;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    public function getContext()
    {
        return $this->ctx;
    }

    public function load()
    {
    }

    public function loadIf()
    {
        if (!$this->loaded) {
            $this->load();
            $this->loaded = true;
        }
    }
}
