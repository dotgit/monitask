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
    const T_NUMERIC = 'n';
    const T_PCT     = 'p';
    const T_TIME    = 't';
    const T_STRING  = 's';

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

    public function gcCol($label, $type=self::T_NUMERIC)
    {
        switch ($type)
        {
        case self::T_NUMERIC:
        case self::T_PCT:
            return ['label'=>$label, 'type'=>'number'];
        case self::T_TIME:
            return ['label'=>$label, 'type'=>'datetime'];
        default:
            return ['label'=>$label, 'type'=>$type];
        }
    }

    public function gcVal($value, $type=self::T_NUMERIC)
    {
        if (!isset($value))
            return null;

        switch ($type)
        {
        case self::T_NUMERIC:
            return ['v'=>round($value, 6), 'f'=>(string)Lib::humanFloat($value)];
        case self::T_PCT:
            return ['v'=>round($value, 6), 'f'=>Lib::humanFloat($value*100).'%'];
        case self::T_TIME:
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
                        ],
                    ]).');';
                }
            }
            $blocks[] =
                '<h2>'.htmlspecialchars($block, null, Lib::CHARSET).'</h2>'.PHP_EOL.
                implode(PHP_EOL, $bk_charts);
        }
        $charts_js = implode(PHP_EOL, $charts);
        $packages_js = json_encode(['packages'=>array_keys($packages)]);
        $now = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']);

        // vars for template
        $Hostname = htmlspecialchars(rtrim(`hostname`), null, Lib::CHARSET);
        $Html_container =
            "<h1 class=\"page-header\">$Hostname <small>$now</small></h1>".
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
                foreach ($period_sanitized as $period=>$period_filename)
                {
                    $period_data[$period] = [
                        ['time'=>''],
                    ];
                }
                foreach ($item as $metric_name=>$metric)
                {
                    if (is_array($metric))
                    {
                        $label = Lib::arrayExtract($metric, self::METRIC_LABEL, $metric_name);
                        $type = Lib::arrayExtract($metric, self::METRIC_TYPE, Store::TYPE_VALUE);
                        foreach ($period_sanitized as $period=>$period_filename)
                        {
                            $period_data[$period][0][$metric_name] = $label;
                            foreach ($store->getMetricData($metric_name, $period, $type) as $time=>$value)
                            {
                                $period_data[$period][$time][$metric_name] = $this->gcVal($value, self::T_NUMERIC);
                            }
                        }
                    }
                }
                foreach ($period_sanitized as $period=>$period_filename)
                {
                    ksort($period_data[$period]);
                    $rows = [];
                    foreach ($period_data[$period] as $bin_id=>$row)
                    {
                        if ($bin_id)
                        {
                            $r = [];
                            foreach ($cols as $c=>$c_label)
                                $r[] = is_array($c_label)
                                    ? $this->gcDateTime($bin_id)
                                    : (isset($row[$c]) ? $row[$c] : null);
                            $rows[] = $r;
                        }
                        else
                        {
                            $cols = $row;
                            $cols['time'] = ['type'=>'datetime'];
                            $rows[] = array_values($cols);
                        }
                    }
                    file_put_contents(
                        sprintf(
                            '%s%s%s-%s.json',
                            $this->export_dir,
                            DIRECTORY_SEPARATOR,
                            Lib::sanitizeFilename($item_name),
                            $period_filename
                        ),
                        json_encode($rows, JSON_PRETTY_PRINT)
                    );
                }
            }
        }

        return true;
    }
}
