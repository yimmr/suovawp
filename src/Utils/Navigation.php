<?php

namespace Suovawp;

class Navigation
{
    public function redirect($path, $status = 307)
    {
        wp_safe_redirect($path, $status);
    }
}
