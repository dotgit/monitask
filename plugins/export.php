<?php

namespace Plugins;

Class Export
{
    // export section of ini file
    const VAR_TYPE  = 'type';

    // block section of ini file
    const VAR_TITLE     = 'title';
    const VAR_OPTIONS   = 'options';

    // metric description
    const METRIC_TITLE  = 'title';
    const METRIC_TYPE   = 'type';
    const METRIC_EVAL   = 'eval';
    const METRIC_HIDDEN = 'hidden';

    public $error;

    public function template(array $items, Store $store)
    {
        return true;
    }

    public function export(array $items, Store $store)
    {
        return true;
    }
}
