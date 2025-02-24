<?php

namespace Suovawp;

class Admin
{
    protected $messages = [];

    public function addNotice($message, $type = 'info')
    {
        $this->messages[] = ['message' => $message, 'type' => $type];
        if (!has_action('admin_notices', [$this, 'renderNotices'])) {
            add_action('admin_notices', [$this, 'renderNotices']);
        }
    }

    /**
     * 钩子调用，渲染已添加的通知消息.
     */
    public function renderNotices()
    {
        while ($item = array_shift($this->messages)) {
            printf('<div class="notice notice-%s"><p>%s</p></div>', $item['type'], $item['message']);
        }
    }
}
