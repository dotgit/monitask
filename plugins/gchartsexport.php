<?php

namespace Plugins;

use Lib;

Class GChartsExport extends Export
{
    // export type
    const TYPE_GCHARTS = 'gcharts';

    // export section of ini file
    const VAR_EXPORT_DIR    = 'export_dir';
    const VAR_AJAX_METHOD   = 'ajax_method';

    // item directives
    const VAR_CLASS         = 'class';
    const VAR_BASE          = 'base';
    const VAR_CRIT_VALUE    = 'critical_value';

    // ajax method values
    const A_M_POST  = 'POST';
    const A_M_GET   = 'GET';

    // types of values
    const FMT_NUMERIC   = 'n';
    const FMT_PCT       = 'p';
    const FMT_TIMESTAMP = 't';

    // column attributes
    const GC_COL_LABEL  = 'label';
    const GC_COL_TYPE   = 'type';
    const GC_COL_ROLE   = 'role';

    // column types
    const GC_C_T_STRING     = 'string';
    const GC_C_T_NUMBER     = 'number';
    const GC_C_T_DATE       = 'date';
    const GC_C_T_DATETIME   = 'datetime';
    const GC_C_T_TIME       = 'time';
    const GC_C_T_BOOLEAN    = 'boolean';

    // column roles
    const GC_C_R_ANNOTATION         = 'annotation';
    const GC_C_R_ANNOTATION_TEXT    = 'annotationText';
    const GC_C_R_INTERVAL           = 'interval';
    const GC_C_R_CERTAINTY          = 'certainty';
    const GC_C_R_EMPHASIS           = 'emphasis';
    const GC_C_R_SCOPE              = 'scope';
    const GC_C_R_STYLE              = 'style';
    const GC_C_R_TOOLTIP            = 'tooltip';
    const GC_C_R_DOMAIN             = 'domain';
    const GC_C_R_DATA               = 'data';

    const JSON_DATA     = 'data';
    const JSON_STATS    = 'stats';
    const JSON_UPDATE   = 'update';
    const JSON_FROM     = 'from';

	public $export_dir;
	public $ajax_method = self::A_M_GET;

    public function __construct($params)
    {
        if (isset($params[self::VAR_EXPORT_DIR]) and is_dir($params[self::VAR_EXPORT_DIR]))
            $this->export_dir = realpath($params[self::VAR_EXPORT_DIR]);
        if (isset($params[self::VAR_AJAX_METHOD]) and in_array(strtoupper($params[self::VAR_AJAX_METHOD]), [self::A_M_GET, self::A_M_POST]))
            $this->ajax_method = strtoupper($params[self::VAR_AJAX_METHOD]);
    }

    public function gcDateTime($time)
    {
        return str_replace('MON', date('m', $time) - 1, 'Date('.date('Y,\M\O\N,d,H,i,s', $time).')');
    }

    public function gcCol($format=self::FMT_NUMERIC, $label=null, $role=null)
    {
        switch($format)
        {
        case self::FMT_NUMERIC:
        case self::FMT_PCT:
            $col = [self::GC_COL_TYPE=>self::GC_C_T_NUMBER];
            break;
        case self::FMT_TIMESTAMP:
            $col = [self::GC_COL_TYPE=>self::GC_C_T_DATETIME];
            break;
        default:
            $col = [self::GC_COL_TYPE=>self::GC_C_T_STRING];
        }
        if (isset($label))
            $col += [self::GC_COL_LABEL=>$label];
        if (isset($role))
            $col += [self::GC_COL_ROLE=>$role];

        return $col;
    }

    public function gcVal($value, $format=self::FMT_NUMERIC)
    {
        if (!isset($value))
            return null;

        switch ($format)
        {
        case self::FMT_NUMERIC:
            return ['v'=>round($value, 6), 'f'=>(string)Lib::humanFloat($value)];
        case self::FMT_PCT:
            return ['v'=>round($value, 6), 'f'=>Lib::humanFloat($value*100).'%'];
        case self::FMT_TIMESTAMP:
            return $this->gcDateTime($value);
        default:
            return $value;
        }
    }

    public function template(array $items, array $periods, Store $store)
    {
        $period_times = [];
        $period_sanitized = [];
        foreach ($periods as $period_name=>$format)
        {
            $period_times[$period_name] = strtotime($format, $_SERVER['REQUEST_TIME']);
            $period_sanitized[$period_name] = Lib::sanitizeFilename($period_name);
        }
        list($first_period) = array_keys($periods);
        $packages = [];
        $toc = [];
        $blocks = [];
        $charts = [];
        $collapse_class = 'collapse';
        foreach ($items as $block=>$bk_items)
        {
            $bk_toc = [];
            $bk_charts = [];
            foreach ($bk_items as $item_name=>$item)
            {
                $class = Lib::arrayExtract($item, self::VAR_CLASS, 'AreaChart');
                $title = Lib::arrayExtract($item, self::VAR_TITLE, $item_name);
                $options = Lib::arrayExtract($item, self::VAR_OPTIONS, []);
                Lib::arrayExtract($item, self::VAR_BASE);
                Lib::arrayExtract($item, self::VAR_CRIT_VALUE);

                $item_clean = Lib::sanitizeFilename($item_name);

                if (! is_array($options))
                    $options = [];
                foreach ($options as $opt_key=>$opt_value)
                {
                    if (strpos($opt_key, '.'))
                    {
                        eval("\$options['".str_replace('.', "']['", addslashes($opt_key))."']=\$opt_value;");
                        unset($options[$opt_key]);
                    }
                }

                $options = array_replace_recursive($options, [
                    'backgroundColor'=>'none',
                    'fontSize'=>12,
                    'height'=>300,
                    'hAxis'=>['minValue'=>null],
                    'vAxis'=>['format'=>'short'],
                ]);

                $metrics = [];
                $i = 0;
                foreach ($item as $metric_name=>$metric)
                {
                    if (is_array($metric))
                    {
                        $metrics[$metric_name] = true;
                        if (! Lib::arrayExtract($metric, self::METRIC_HIDDEN))
                        {
                            foreach ($metric as $d_key=>$d_value)
                            {
                                if (strpos($d_key, 'series.') === 0)
                                {
                                    eval("\$options['".str_replace(
                                        '.',
                                        "']['",
                                        addslashes(str_replace('series.', "series.$i.", $d_key)
                                    ))."']=\$d_value;");
                                }
                            }
                            $i++;
                        }
                    }
                }
                $bk_toc[] = sprintf(
                    '<a class="list-group-item" href="#ref_%s">%s</a>%s',
                    Lib::sanitizeFilename($title),
                    htmlspecialchars($title),
                    PHP_EOL
                );
                $bk_charts[] = sprintf(
'<p class="lead" id="ref_%s">
  <button class="pull-right btn btn-sm btn-link" onclick="toggleMore(this,\'%s\')">%s</button>
  %s
</p>
<div class="panel panel-default">
  <div class="panel-body">',
                    Lib::sanitizeFilename($title),
                    $item_clean,
                    'More...',
                    htmlspecialchars($title)
                );
                foreach ($period_sanitized as $period_name=>$period_file)
                {
                    $options['title'] = "$title - $period_name";
                    $options['hAxis']['minValue'] = $this->gcDateTime($period_times[$period_name]);
                    $id = "$item_clean-$period_file";
                    $bk_charts[] = "<div id=\"$id\"></div><div id=\"stats_$id\"></div>";
                    if ($period_name == $first_period)
                        $bk_charts[] = "<div class=\"$collapse_class $item_clean\">";
                    $packages[strtolower($class)] = true;
                    $charts[] = "GCharts['$id']=new google.visualization.ChartWrapper(".json_encode([
                        "containerId"=>$id,
                        "chartType"=>$class,
                        'options'=>$options,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).");";
                }
                $bk_charts[] =
'  </div></div>
</div>';
            }
            $toc[] = sprintf(
                '<div class="list-group"><a class="list-group-item" href="#ref_%s"><b>%s</b></a>%s</div>',
                Lib::sanitizeFilename($block),
                htmlspecialchars($block),
                implode('', $bk_toc)
            );
            $blocks[] = sprintf(
                '<h3 class="page-header" id="ref_%s">%s</h3>%s%s%s',
                Lib::sanitizeFilename($block),
                htmlspecialchars($block),
                PHP_EOL,
                implode(PHP_EOL, $bk_charts),
                PHP_EOL
            );
        }
        $Packages_js = json_encode(
            ['packages'=>array_keys($packages)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $Charts_js = implode(PHP_EOL, $charts);
        $Ajax_method = $this->ajax_method;

        // vars for template
        $Hostname = htmlspecialchars(rtrim(`hostname`));
        $Toc_html = implode(PHP_EOL, $toc);
        $Blocks_html = implode(PHP_EOL, $blocks);

        $Json_data = self::JSON_DATA;
        $Json_stats = self::JSON_STATS;
        $Json_update = self::JSON_UPDATE;
        $Json_from = self::JSON_FROM;

        include 'plugins/gchartstemplate.php';

        return true;
    }

    public function export(array $items, array $periods, Store $store)
    {
        if (empty($this->export_dir))
        {
            $this->error = __METHOD__.': '.self::VAR_EXPORT_DIR.' parameter not set or is not a directory';
            return false;
        }
        $period_times = [];
        $period_sanitized = [];
        foreach ($periods as $period_name=>$format)
        {
            $period_times[$period_name] = strtotime($format, $_SERVER['REQUEST_TIME']);
            $period_sanitized[$period_name] = Lib::sanitizeFilename($period_name);
        }

        foreach ($items as $bk_items)
        {
            foreach ($bk_items as $item_name=>$item)
            {
                Lib::arrayExtract($item, self::VAR_CLASS, 'AreaChart');
                Lib::arrayExtract($item, self::VAR_TITLE, $item_name);
                Lib::arrayExtract($item, self::VAR_OPTIONS, []);

                $metric_titles = [];
                $metric_types = [];
                $metric_evals = [];
                $metric_visibles = [];
                $period_bin_metric_values = [];
                $period_metric_stats = [];

                // pass 1: fill $period_bin_metric_values
                foreach ($item as $metric_name=>$metric)
                {
                    if (is_array($metric))
                    {
                        $metric_titles[$metric_name] = Lib::arrayExtract($metric, self::METRIC_TITLE, $metric_name);
                        $metric_types[$metric_name] = Lib::arrayExtract($metric, self::METRIC_TYPE, Store::TYPE_VALUE);
                        $metric_evals[$metric_name] = Lib::arrayExtract($metric, self::METRIC_EVAL);
                        if (! Lib::arrayExtract($metric, self::METRIC_HIDDEN))
                            $metric_visibles[$metric_name] = $metric_titles[$metric_name];

                        foreach ($period_sanitized as $period_name=>$period_filename)
                        {
                            foreach ($store->getMetricData($metric_name, $period_name, $metric_types[$metric_name]) as $bin_time=>$value)
                                $period_bin_metric_values[$period_name][$bin_time][$metric_name] = $value;
                            $period_metric_stats[$period_name][$metric_name] = $store->getMetricStats($metric_name, $period_name, $metric_types[$metric_name]);
                        }
                    }
                }

                // pass 2: normalize and output item per period
                foreach ($period_sanitized as $period_name=>$period_filename)
                {
                    // fill data table
                    $data = [];

                    // headings line
                    $r = [$this->gcCol(self::FMT_TIMESTAMP)];
                    foreach ($metric_visibles as $metric_name=>$label)
                        $r[] = $this->gcCol(self::FMT_NUMERIC, $label);
                    $data[] = $r;

                    // bins
                    if ($period_bin_metric_values)
                    {
                        ksort($period_bin_metric_values[$period_name]);
                        foreach ($period_bin_metric_values[$period_name] as $bin_time=>$metric_values)
                        {
                            $metric_parsed = [];
                            $r = [$this->gcDateTime($bin_time)];
                            foreach ($metric_visibles as $metric_name=>$label)
                            {
                                if (isset($metric_evals[$metric_name]))
                                {
                                    if (empty($metric_parsed))
                                        foreach ($metric_values as $m_name=>$value)
                                            $metric_parsed[$m_name] = isset($value) ? "($value)" : 'null';
                                    $r[] = $this->gcVal(eval(str_replace(
                                        array_keys($metric_parsed),
                                        array_values($metric_parsed),
                                        "return ({$metric_evals[$metric_name]});"
                                    )), self::FMT_NUMERIC);
                                }
                                else
                                    $r[] = isset($metric_values[$metric_name])
                                        ? $this->gcVal($metric_values[$metric_name], self::FMT_NUMERIC)
                                        : null;
                            }
                            $data[] = $r;
                        }
                    }
                    if (count($data) < 2)
                    {
                        $data[] = array_fill(0, count($metric_visibles) + 1, null);
                    }

                    // fill statistics
                    $stats_metric_parsed = [];
                    $stats = [];
                    $lu = null;
                    foreach ($metric_visibles as $metric_name=>$label)
                    {
                        $st = $period_metric_stats[$period_name][$metric_name];
                        if (isset($metric_evals[$metric_name]))
                        {
                            if (empty($stats_metric_parsed))
                                foreach ($period_metric_stats[$period_name] as $m_name=>$m_stats)
                                {
                                    $stats_metric_parsed[Store::STAT_FIRST][$m_name] = isset($period_metric_stats[$period_name][$m_name][Store::STAT_FIRST])
                                        ? "({$period_metric_stats[$period_name][$m_name][Store::STAT_FIRST]})"
                                        : 'null';
                                    $stats_metric_parsed[Store::STAT_MIN][$m_name] = isset($period_metric_stats[$period_name][$m_name][Store::STAT_MIN])
                                        ? "({$period_metric_stats[$period_name][$m_name][Store::STAT_MIN]})"
                                        : 'null';
                                    $stats_metric_parsed[Store::STAT_AVG][$m_name] = isset($period_metric_stats[$period_name][$m_name][Store::STAT_AVG])
                                        ? "({$period_metric_stats[$period_name][$m_name][Store::STAT_AVG]})"
                                        : 'null';
                                    $stats_metric_parsed[Store::STAT_MAX][$m_name] = isset($period_metric_stats[$period_name][$m_name][Store::STAT_MAX])
                                        ? "({$period_metric_stats[$period_name][$m_name][Store::STAT_MAX]})"
                                        : 'null';
                                    $stats_metric_parsed[Store::STAT_LAST][$m_name] = isset($period_metric_stats[$period_name][$m_name][Store::STAT_LAST])
                                        ? "({$period_metric_stats[$period_name][$m_name][Store::STAT_LAST]})"
                                        : 'null';
                                }
                            $eval = "return ({$metric_evals[$metric_name]});";
                            $st = [
                                Store::STAT_FIRST=>eval(str_replace(
                                    array_keys($stats_metric_parsed[Store::STAT_FIRST]),
                                    array_values($stats_metric_parsed[Store::STAT_FIRST]),
                                    $eval
                                )),
                                Store::STAT_MIN=>eval(str_replace(
                                    array_keys($stats_metric_parsed[Store::STAT_MIN]),
                                    array_values($stats_metric_parsed[Store::STAT_MIN]),
                                    $eval
                                )),
                                Store::STAT_AVG=>eval(str_replace(
                                    array_keys($stats_metric_parsed[Store::STAT_AVG]),
                                    array_values($stats_metric_parsed[Store::STAT_AVG]),
                                    $eval
                                )),
                                Store::STAT_MAX=>eval(str_replace(
                                    array_keys($stats_metric_parsed[Store::STAT_MAX]),
                                    array_values($stats_metric_parsed[Store::STAT_MAX]),
                                    $eval
                                )),
                                Store::STAT_LAST=>eval(str_replace(
                                    array_keys($stats_metric_parsed[Store::STAT_LAST]),
                                    array_values($stats_metric_parsed[Store::STAT_LAST]),
                                    $eval
                                )),
                            ];
                        }
                        $stats[] = [
                            $label,
                            isset($st[Store::STAT_FIRST]) ? Lib::humanFloat($st[Store::STAT_FIRST]) : null,
                            isset($st[Store::STAT_MIN]) ? Lib::humanFloat($st[Store::STAT_MIN]) : null,
                            isset($st[Store::STAT_AVG]) ? Lib::humanFloat($st[Store::STAT_AVG]) : null,
                            isset($st[Store::STAT_MAX]) ? Lib::humanFloat($st[Store::STAT_MAX]) : null,
                            isset($st[Store::STAT_LAST]) ? Lib::humanFloat($st[Store::STAT_LAST]) : null,
                        ];
                        if (isset($st[Store::STAT_UPDATE]) and empty($lu))
                            $lu = date('Y-m-d H:i:s', $st[Store::STAT_UPDATE]);
                    }

                    file_put_contents(
                        sprintf(
                            '%s%s%s-%s.json',
                            $this->export_dir,
                            DIRECTORY_SEPARATOR,
                            Lib::sanitizeFilename($item_name),
                            $period_filename
                        ),
                        json_encode([
                            self::JSON_DATA=>$data,
                            self::JSON_STATS=>$stats,
                            self::JSON_UPDATE=>$lu,
                            self::JSON_FROM=>$this->gcDateTime($period_times[$period_name]+($store->periods_seconds[$period_name]<<1)),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    );
                }
            }
        }

        return true;
    }
}
