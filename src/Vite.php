<?php

namespace Suovawp;

class Vite
{
    protected $outDir;

    protected $baseURL;

    /** @var string|null */
    protected $handle;

    protected $entry;

    protected $headHook;

    protected $footerHook;

    protected $scriptsHook;

    protected $modulepreloads = [];

    protected $publicUrlFunc = '__toAssetsUrl';

    public function __construct($dirname = 'dist', $baseURL = null, $outDir = null)
    {
        $this->baseURL = rtrim($baseURL ?? get_theme_file_uri($dirname), '/');
        $this->outDir = rtrim($outDir ?? get_theme_file_path($dirname), '\/');
        if (is_admin()) {
            $this->headHook = 'admin_head';
            $this->footerHook = 'admin_footer';
            $this->scriptsHook = 'admin_enqueue_scripts';
        } else {
            $this->headHook = 'wp_head';
            $this->footerHook = 'wp_footer';
            $this->scriptsHook = 'wp_enqueue_scripts';
        }
    }

    public function setEntry(string $entry)
    {
        $this->entry = $entry;
        return $this;
    }

    public function setPublicUrlFunc($funcName)
    {
        $this->publicUrlFunc = $funcName;
        return $this;
    }

    public function getHandle()
    {
        return $this->handle ?: $this->entryToHandle($this->entry);
    }

    public function register($priority = 10)
    {
        // if(function_exists('wp_preload_resources')){
        //     add_action('wp_preload_resources', [$this, 'preload']);
        // }
        add_action($this->headHook, [$this, 'preload'], 1);
        add_action($this->scriptsHook, [$this, 'enqueueScripts'], $priority);
    }

    public function preload()
    {
        foreach ($this->modulepreloads as $source) {
            echo ' <link rel="modulepreload" crossorigin href="'.$this->url($source).'">';
        }
        $this->modulepreloads = [];
    }

    public function addRefinerJS($id)
    {
        wp_add_inline_script($id, "function {$this->publicUrlFunc}(f='') {return '{$this->baseURL}/'+f;}", 'before');
    }

    public function enqueueScripts()
    {
        $entry = apply_filters('suovawp_vite_page_entry', $this->entry, $this);
        if (!$entry || 0 === strpos($entry, '_')) {
            return;
        }

        $manifest = $this->getManifest();
        if (!isset($manifest[$entry])) {
            return;
        }

        $chunk = $manifest[$entry];
        if (!$chunk['isEntry']) {
            return;
        }

        if (isset($chunk['imports'])) {
            foreach ((array) $chunk['imports'] as $import) {
                $importChunk = $manifest[$import];
                $this->modulepreloads[] = $importChunk['file'];
                if (isset($importChunk['css'])) {
                    $this->enqueueStyles($importChunk['css'], pathinfo($import, \PATHINFO_FILENAME));
                }
            }
        }
        $this->modulepreloads[] = $chunk['file'];

        $info = pathinfo($chunk['src']);
        $id = $this->handle = $this->entryToHandle($entry);
        $this->enqueueScript($id, $this->url($chunk['file']));
        $this->addRefinerJS($id);
        if (isset($chunk['css'])) {
            $this->enqueueStyles($chunk['css'], $id);
        }
        $legacyEntry = (isset($info['dirname']) && '.' !== $info['dirname'] ? $info['dirname'].DIRECTORY_SEPARATOR : '')
            .$info['filename'].'-legacy'.(isset($info['extension']) ? '.'.$info['extension'] : '');
        if (isset($manifest[$legacyEntry]['file'])) {
            $this->legacy($manifest[$legacyEntry]['file'], $manifest['vite/legacy-polyfills-legacy']['file'] ?? '');
        }
    }

    public function enqueueStyles($styles, $baseHandle = '')
    {
        foreach ($styles as $i => $style) {
            $handle = $baseHandle ? $baseHandle.(0 === $i ? '' : '-'.$i) : pathinfo($style, \PATHINFO_FILENAME);
            wp_enqueue_style($handle, $this->url($style), [], null, 'all');
        }
    }

    public function entryToHandle($entry)
    {
        $handle = pathinfo($entry, \PATHINFO_FILENAME);
        if ('index' === $handle && ($entry = dirname($entry))) {
            $newHandle = pathinfo($entry, \PATHINFO_FILENAME);
            $handle = $newHandle !== $entry ? $newHandle : $handle;
        }
        return (string) $handle;
    }

    public function url($path = '')
    {
        return $path ? $this->baseURL.'/'.ltrim($path, '/') : $this->baseURL;
    }

    public function getManifest()
    {
        if (is_file($file = $this->outDir.'/.vite/manifest.json')) {
            return json_decode(file_get_contents($file), true);
        }
        if (is_file($file = $this->outDir.'/manifest.json')) {
            return json_decode(file_get_contents($file), true);
        }
        return [];
    }

    /**
     * @param string           $handle
     * @param string           $src
     * @param string[]         $deps
     * @param string|bool|null $ver
     * @param array|bool       $args
     */
    public function enqueueScript($handle, $src = '', $deps = [], $ver = null, $args = false)
    {
        wp_enqueue_script($handle, $src, $deps, $ver, $args);
        $func = fn ($t, $h, $s) => $s === $src
            ? preg_replace('/<script([^>]*?src=[\'"]'.preg_quote($s, '/').'[\'"])/i',
                '<script type="module"$1', $t)
            : $t;
        add_filter('script_loader_tag', $func, 10, 3);
    }

    public function legacy($entry, $polyfill)
    {
        add_action($this->headHook, [$this, 'outputLegacy']);
        add_action($this->footerHook, fn () => $this->outputLegacyNomodule($this->url($entry), $this->url($polyfill)));
    }

    public function outputLegacy()
    {
        echo <<<SCRIPT
        <script type="module">import.meta.url;import("_").catch(()=>1);async function* g(){};if(location.protocol!="file:"){window.__vite_is_modern_browser=true}</script>
        <script type="module">!function(){if(window.__vite_is_modern_browser)return;console.warn("vite: loading legacy chunks, syntax error above and the same error below should be ignored");var e=document.getElementById("vite-legacy-polyfill"),n=document.createElement("script");n.src=e.src,n.onload=function(){System.import(document.getElementById('vite-legacy-entry').getAttribute('data-src'))},document.body.appendChild(n)}();</script>
        SCRIPT;
    }

    public function outputLegacyNomodule($entry, $polyfill)
    {
        echo <<<SCRIPT
        <script nomodule>!function(){var e=document,t=e.createElement("script");if(!("noModule"in t)&&"onbeforeload"in t){var n=!1;e.addEventListener("beforeload",(function(e){if(e.target===t)n=!0;else if(!e.target.hasAttribute("nomodule")||!n)return;e.preventDefault()}),!0),t.type="module",t.src=".",e.head.appendChild(t),t.remove()}}();</script>
        <script nomodule crossorigin id="vite-legacy-polyfill" src="$polyfill"></script>
        <script nomodule crossorigin id="vite-legacy-entry" data-src="$entry">System.import(document.getElementById('vite-legacy-entry').getAttribute('data-src'))</script>
        SCRIPT;
    }

    protected function handleManifestError($message)
    {
        if (WP_DEBUG) {
            error_log("Vite manifest error: $message");
        }
    }
}
