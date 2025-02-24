<?php

namespace Suovawp;

/**
 * @template Ctx of Context
 */
class AdminPageModel
{
    /** @var Ctx */
    protected $ctx;

    protected $props = [];

    protected $loaded = false;

    public $userOptions = [];

    public function __construct(Context $ctx, $props = [])
    {
        $this->ctx = $ctx;
        $this->props = $props;
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

    public function getProps()
    {
        return $this->props;
    }

    public function get($prop, $default = null)
    {
        return $this->props[$prop] ?? $default;
    }

    public function set($prop, $value)
    {
        return $this->props[$prop] = $value;
    }

    public function addScreenOption($option, $optionName, $label = '', $default = 20, $args = [])
    {
        add_screen_option($option, [
            'label'   => $label,
            'default' => $default,
            'option'  => $optionName,
        ] + $args);
    }
}
