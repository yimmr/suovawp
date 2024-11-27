<?php

namespace Suovawp\Utils;

/**
 * @phpstan-type LogoInfo array{url:string,image:string,html:string}
 */
class SiteLogo
{
    /** @var array<string,LogoInfo> */
    protected $value = [];

    protected $loader;

    /**
     * @param \Closure(string $name,static):LogoInfo $loader
     */
    public function __construct(\Closure $loader)
    {
        $this->loader = $loader;
    }

    public function url(string $name = 'main')
    {
        return $this->get($name, 'url');
    }

    public function image(string $name = 'main')
    {
        return $this->get($name, 'image');
    }

    public function html(string $name = 'main')
    {
        return $this->get($name, 'html');
    }

    public function output(string $name = 'main', string $type = 'html')
    {
        echo $this->get($name, $type);
    }

    /**
     * @param key-of<LogoInfo> $type
     */
    public function get(string $name = 'main', string $type = 'html')
    {
        if (!isset($this->value[$name])) {
            $this->value[$name] = ($this->loader)($name);
        }
        return $this->value[$name][$type];
    }

    public function buildMain(string $url, array $imgAttrs = [], array $linkAttrs = [])
    {
        $isUnlinkHomepage = (bool) get_theme_support('custom-logo', 'unlink-homepage-logo');
        $isHome = is_front_page() && !is_paged();
        $attrs = ['class' => 'custom-logo-link'];
        if ($isUnlinkHomepage && $isHome) {
        } else {
            $attrs += ['href' => home_url('/'), 'rel' => 'home'];
            if ($isHome) {
                $attrs['aria-current'] = 'page';
            }
        }
        return $this->build($url, 'full', $imgAttrs, $linkAttrs + $attrs);
    }

    public function parse(string $html)
    {
        preg_match('/<img[^>]+src=["\'](.*?)["\'][^>]+>/i', $html, $matches);
        return ['url' => $matches[1] ?? '', 'image' => $matches[0] ?? '', 'html' => $html];
    }

    /**
     * @param int|string $imgid     支持图片ID或URL
     * @param array|null $htmlAttrs 默认null值表示不创建html，href属性不为空则创建a标签，反之创建span标签
     */
    public function build($imgid, string $size = 'full', array $imgAttrs = [], ?array $htmlAttrs = null)
    {
        $imgAttrs['alt'] ??= get_bloginfo('name');
        $url = $image = $html = '';
        if (is_numeric($imgid)) {
            $url = (string) wp_get_attachment_image_url($imgid, $size);
            $image = $url ? wp_get_attachment_image($imgid, $size, false, $imgAttrs) : '';
        } elseif ($imgid) {
            $url = (string) $imgid;
            $image = $this->buildImage($imgid, $size, $imgAttrs);
        }
        if (!is_null($htmlAttrs)) {
            $tag = empty($htmlAttrs['href']) ? 'span' : 'a';
            $html = '<'.$tag;
            foreach ($htmlAttrs as $name => $value) {
                $html .= " $name=".'"'.$value.'"';
            }
            $html .= ">{$image}</{$tag}>";
        }
        return ['url' => $url, 'image' => $image, 'html' => $html];
    }

    public function buildImage(string $src, string $size = 'full', array $attrs = [])
    {
        $attrs['class'] = (isset($attrs['class']) ? $attrs['class'].' ' : '')."attachment-$size size-$size";
        $attrs['decoding'] ??= 'async';
        $html = '<img src="'.$src.'"';
        foreach ($attrs as $name => $value) {
            $html .= " $name=".'"'.$value.'"';
        }
        $html .= ' />';
        return $html;
    }
}
