<?php

namespace Suovawp;

class Performance
{
    public function timestartDiff($precision = null)
    {
        $diff = microtime(true) - $GLOBALS['timestart'];
        return null === $precision ? $diff : round($diff, $precision);
    }

    public function outputTimeDiffHTML($class = '')
    {
        $time = $this->timestartDiff(3);
        echo '<div class="position-relative bg-white p-2 px-3'.$class.'" style="z-index:9999999;"><code>此处耗时：'.$time.'秒</code></div>';
    }
}
