<?php

namespace Plugins;

Class Export
{
    // export section of ini file
    const VAR_TYPE  = 'type';

    // block section of ini file
    const VAR_LABEL         = 'label';
    const VAR_VERT_LABEL    = 'vertical_label';
    const VAR_BASE          = 'base';
    const VAR_MAX_VALUE     = 'max_value';
    const VAR_CRIT_VALUE    = 'critical_value';
    const VAR_LOWER_LIMIT   = 'lower_limit';

    // metric description
    const METRIC_LABEL  = 'label';
    const METRIC_TYPE   = 'type';
    const METRIC_INVERT = 'invert';

    public $error;

    public function export($items, $periods, Store $store)
    {
        return true;
    }
}
