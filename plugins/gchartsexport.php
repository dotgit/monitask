<?php

namespace Plugins;

use Lib;

Class GChartsExport extends Export
{
    // export type
    const TYPE_GCHARTS = 'gcharts';

    // export section of ini file
    const VAR_EXPORT_DIR    = 'export_dir';

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

	public $export_dir;

    public function __construct($params)
    {
        if (empty($params[self::VAR_EXPORT_DIR]) or ! is_dir($params[self::VAR_EXPORT_DIR]))
            $this->error = __METHOD__.': '.self::VAR_EXPORT_DIR.' parameter not set or is not a directory';
        else
            $this->export_dir = realpath($params[self::VAR_EXPORT_DIR]);
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
        $period_sanitized = [];
        foreach ($periods as $period_name=>$format)
            $period_sanitized[$period_name] = Lib::sanitizeFilename($period_name);
        $packages = [];
        $blocks = [];
        $charts = [];
        foreach ($items as $block=>$bk_items)
        {
            $bk_charts = [];
            foreach ($bk_items as $item_name=>$item)
            {
                $label = Lib::arrayExtract($item, self::VAR_LABEL, $item_name);
                $vert_label = Lib::arrayExtract($item, self::VAR_VERT_LABEL);
                $base = Lib::arrayExtract($item, self::VAR_BASE);
                $max_value = Lib::arrayExtract($item, self::VAR_MAX_VALUE);
                $crit_value = Lib::arrayExtract($item, self::VAR_CRIT_VALUE);
                $lower_limit = Lib::arrayExtract($item, self::VAR_LOWER_LIMIT);

                $metrics = [];
                foreach ($item as $metric_name=>$metric)
                {
                    if (is_array($metric))
                    {
                        $metrics[$metric_name] = true;
                    }
                }
                $bk_charts[] = '<h3>'.htmlspecialchars($label, null, Lib::CHARSET).'</h3>';
                foreach ($period_sanitized as $period_file)
                {
                    $id = "$item_name-$period_file";
                    $chart_type = 'AreaChart';
                    $bk_charts[] = "<div id=\"$id\"></div>";
                    $packages[strtolower($chart_type)] = true;
                    $charts[] = "getJsonDraw('$id.json',".json_encode([
                        "containerId"=>$id,
                        "chartType"=>$chart_type,
                        'options'=>[
                            'fontName'=>'Roboto',
                            'fontSize'=>12,
                            'height'=>300,
                            'isStacked'=>true,
                            'vAxis'=>['format'=>'short'],
                        ],
                    ], JSON_UNESCAPED_UNICODE).');';
                }
            }
            $blocks[] =
                '<h2>'.htmlspecialchars($block, null, Lib::CHARSET).'</h2>'.PHP_EOL.
                implode(PHP_EOL, $bk_charts);
        }
        $charts_js = implode(PHP_EOL, $charts);
        $packages_js = json_encode(['packages'=>array_keys($packages)]);

        // vars for template
        $Hostname = htmlspecialchars(rtrim(`hostname`), null, Lib::CHARSET);
        $Html_container =
            "<h1 class=\"page-header\">$Hostname</h1>".
            implode(PHP_EOL, $blocks);
        $Js_footer =
<<<EOjs
google.load('visualization', '1', {packages:['corechart']});
google.setOnLoadCallback(drawChart);
function getJsonDraw(url,chart){
    $.ajax({
        url:url,
        success:function(data){
            chart['dataTable'] = data;
            google.visualization.drawChart(chart);
        },
        dataType:"json"
    });
}
function drawChart(){
$charts_js
}
EOjs;

        include 'plugins/gchartstemplate.php';

        return true;
    }

    public function export(array $items, array $periods, Store $store)
    {
        $period_sanitized = [];
        foreach ($periods as $period_name=>$format)
            $period_sanitized[$period_name] = Lib::sanitizeFilename($period_name);

        foreach ($items as $block=>$bk_items)
        {
            foreach ($bk_items as $item_name=>$item)
            {
                $metric_labels = [];
                $metric_types = [];
                $metric_evals = [];
                $metric_hiddens = [];
                $metric_visibles = [];
                $period_bin_metric_values = [];

                // pass 1: fill $period_bin_metric_values
                foreach ($item as $metric_name=>$metric)
                {
                    if (is_array($metric))
                    {
                        $metric_labels[$metric_name] = Lib::arrayExtract($metric, self::METRIC_LABEL, $metric_name);
                        $metric_types[$metric_name] = Lib::arrayExtract($metric, self::METRIC_TYPE, Store::TYPE_VALUE);
                        $metric_evals[$metric_name] = Lib::arrayExtract($metric, self::METRIC_EVAL);
                        if (! Lib::arrayExtract($metric, self::METRIC_HIDDEN))
                            $metric_visibles[$metric_name] = $metric_labels[$metric_name];

                        foreach ($period_sanitized as $period_name=>$period_filename)
                        {
                            foreach ($store->getMetricData($metric_name, $period_name, $metric_types[$metric_name]) as $bin_time=>$value)
                                $period_bin_metric_values[$period_name][$bin_time][$metric_name] = $value;
                        }
                    }
                }

                // pass 2: normalize and output item per period
                foreach ($period_sanitized as $period_name=>$period_filename)
                {
                    $rows = [];

                    // headings line
                    $r = [$this->gcCol(self::FMT_TIMESTAMP)];
                    foreach ($metric_visibles as $metric_name=>$label)
                        $r[] = $this->gcCol(self::FMT_NUMERIC, $label);
                    $rows[] = $r;

                    // bins
                    if ($period_bin_metric_values)
                    {
                        ksort($period_bin_metric_values[$period_name]);
                        foreach ($period_bin_metric_values[$period_name] as $bin_time=>$metric_values)
                        {
                            $r = [$this->gcDateTime($bin_time)];
                            foreach ($metric_visibles as $metric_name=>$label)
                                $r[] = isset($metric_evals[$metric_name])
                                    ? $this->gcVal(eval(str_replace(
                                        array_keys($metric_values),
                                        array_values($metric_values),
                                        "return ({$metric_evals[$metric_name]});"
                                    )), self::FMT_NUMERIC)
                                    : (isset($metric_values[$metric_name])
                                        ? $this->gcVal($metric_values[$metric_name], self::FMT_NUMERIC)
                                        : null);
                            $rows[] = $r;
                        }
                    }
                    if (count($rows) < 2)
                    {
                        $rows[] = array_fill(0, count($metric_visibles) + 1, null);
                    }
                    file_put_contents(
                        sprintf(
                            '%s%s%s-%s.json',
                            $this->export_dir,
                            DIRECTORY_SEPARATOR,
                            Lib::sanitizeFilename($item_name),
                            $period_filename
                        ),
                        json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    );
                }
            }
        }

        return true;
    }
}
