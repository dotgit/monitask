<?php

namespace Plugins;

class Export
{
    // export section of ini file
    const VAR_TYPE = 'type';

    // block section of ini file
    const VAR_TITLE = 'title';
    const VAR_OPTIONS = 'options';

    // metric description
    const METRIC_TITLE = 'title';
    const METRIC_TYPE = 'type';
    const METRIC_FORMAT = 'format';
    const METRIC_EVAL = 'eval';
    const METRIC_HIDDEN = 'hidden';

    public $error;

    /**
     * @param array $items
     * @param Store $store
     * @return bool
     */
    public function template(array $items, Store $store)
    {
        return true;
    }

    /**
     * @param array $items
     * @param Store $store
     * @return bool
     */
    public function export(array $items, Store $store)
    {
        return true;
    }
}
