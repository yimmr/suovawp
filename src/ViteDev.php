<?php

namespace Suovawp;

class ViteDev
{
    protected $host;

    protected $port;

    public function __construct($host = 'localhost', $port = 5173)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function init()
    {
        if (!$this->isViteDev()) {
            return;
        }
        add_filter('suovawp_vite_page_entry', function ($entry, Vite $vite) {
            $vite->enqueueScript('vite-client', $this->url('@vite/client'));
            $vite->enqueueScript($vite->entryToHandle($entry), $this->url($entry));
            $vite->addRefinerJS($vite->entryToHandle($entry));
            return null;
        }, 999, 2);
    }

    public function url($path = '')
    {
        return "http://localhost:{$this->port}".($path ? '/'.ltrim($path, '/') : '');
    }

    public function getRealHost()
    {
        if ($this->isDocker()) {
            return 'host.docker.internal';
        }
        return $this->host;
    }

    public function isViteDev()
    {
        if (is_resource($connection = @fsockopen($this->getRealHost(), $this->port, $errCode, $errMsg, 15))) {
            fclose($connection);
            return true;
        }
        return false;
    }

    public function isDocker()
    {
        return file_exists('/.dockerenv') || false !== getenv('DOCKER');
    }
}
