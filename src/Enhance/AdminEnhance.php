<?php

namespace Suovawp\Enhance;

use Suovawp\AdminPageModel;

class AdminEnhance extends AdminPageModel
{
    /** @var \WP_Screen|null */
    protected $screen;

    /** @var TaxForm|null */
    protected $taxForm;

    /** @var string|null */
    protected $taxonomy;

    /** @var string|null */
    protected $postType;

    /** @var Metabox[] */
    protected $metaboxes = [];

    protected $termSaved = false;

    public function load()
    {
        if (!empty($this->props['assets'])) {
            $this->assets($this->props['assets']);
        }
        $this->postMetaboxes();
        $taxForm = $this->getTaxForm();
        $taxForm && $taxForm->setupPage();
    }

    public function setScreen(\WP_Screen $screen)
    {
        $this->screen = $screen;
        return $this;
    }

    protected function assets($assets)
    {
        $instance = clone $this->ctx->assets;
        if (is_string($assets)) {
            $instance->entry($assets);
        } elseif (is_array($assets)) {
            $assets['media'] ??= false;
            if ($assets['form'] ?? false) {
                $assets['media'] ??= [];
                $instance->script('wp-api');
                $instance->style('wp-components', 'font-inter');
            }
            if (false !== $assets['media']) {
                $instance->media($assets['media']);
            }
            unset($assets['form'],$assets['media']);

            foreach ($assets as $key => $value) {
                call_user_func_array([$instance, $key], (array) $value);
            }
        } elseif (is_callable($assets)) {
            $assets($instance);
        }
        $instance->adminRegister();
    }

    protected function postMetaboxes()
    {
        if (empty($this->props['metaboxes'])) {
            return;
        }
        $postType = $this->getPostType();
        foreach ($this->props['metaboxes'] as $metabox) {
            $metabox['post_type'] = $postType;
            $instance = new Metabox($metabox);
            // $this->metaboxes[] = $instance;
            $instance->setupPage();
        }
        $this->props['metaboxes'] = [];
    }

    public function getPostType()
    {
        return $this->postType ??= ($this->screen->post_type ?? $this->get('post_type', ''));
    }

    public function getTaxonomy()
    {
        return $this->taxonomy ??= ($this->screen->taxonomy ?? $this->get('taxonomy', ''));
    }

    public function setTaxonomy(string $taxonomy)
    {
        $this->taxonomy = $taxonomy;
        return $this;
    }

    /**
     * @return TaxForm
     */
    public function getTaxForm()
    {
        if (isset($this->taxForm)) {
            return $this->taxForm;
        }
        $options = $this->get('tax_form', []);
        if (!$options || !($taxonomy = $this->getTaxonomy())) {
            return null;
        }
        $options['taxonomy'] = $taxonomy;
        $this->taxForm = new TaxForm($options);
        return $this->taxForm;
    }

    public function termSave($termId)
    {
        if ($this->termSaved) {
            return;
        }
        if ($taxForm = $this->getTaxForm()) {
            $this->termSaved = true;
            return $taxForm->save($termId);
        }
    }
}
