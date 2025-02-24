<?php

namespace Suovawp;

/**
 * @template Ctx of Context
 */
abstract class LayerModel
{
    /** @var Ctx */
    protected $ctx;

    protected $userId;

    protected $loaded = false;

    protected $cache = [];

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
        $this->userId = get_current_user_id();
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
