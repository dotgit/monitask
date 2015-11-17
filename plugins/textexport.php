<?php

namespace Plugins;

Class TextExport extends Export
{
    public function __construct($params)
    {
    }

    public function export($items)
    {
        foreach ($items as $block=>$bk_items)
        {
            if (! empty($bk_items) and is_array($bk_items))
            {
                echo $block, PHP_EOL;
                foreach ($bk_items as $item)
                {
                }
            }
        }

        return true;
    }
}
