<?php

namespace Plugins;

use Lib;

Class GChartsExport extends Export
{
    // export type
    const TYPE_GCHARTS = 'gcharts';

    // export section of ini file
    const VAR_EXPORT_DIR    = 'export_dir';

    // item directives
    const VAR_CLASS         = 'class';
    const VAR_BASE          = 'base';
    const VAR_CRIT_VALUE    = 'critical_value';

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

	public $export_dir;

    public function __construct($params)
    {
        if (isset($params[self::VAR_EXPORT_DIR]) and is_dir($params[self::VAR_EXPORT_DIR]))
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
                $base = Lib::arrayExtract($item, self::VAR_BASE);
                $crit_value = Lib::arrayExtract($item, self::VAR_CRIT_VALUE);

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
                    '<p class="lead" id="ref_%s">%s<button class="pull-right btn btn-sm btn-default" onclick="toggleMore(this,\'%s\')">%s</button></p>%s',
                    Lib::sanitizeFilename($title),
                    htmlspecialchars($title),
                    $item_clean,
                    'More...',
                    PHP_EOL
                );
                foreach ($period_sanitized as $period_name=>$period_file)
                {
                    $options['title'] = "$title - $period_name";
                    $id = "$item_clean-$period_file";
                    $bk_charts[] = "<div id=\"$id\"></div><div id=\"stats_$id\"></div>";
                    if ($period_name == $first_period)
                        $bk_charts[] = "<div class=\"$collapse_class $item_clean\">";
                    $packages[strtolower($class)] = true;
                    $charts[] = "GCharts['$id']=new google.visualization.ChartWrapper(".json_encode([
                        "containerId"=>$id,
                        "chartType"=>$class,
                        'options'=>$options,
                    ], JSON_UNESCAPED_UNICODE).");";
                }
                $bk_charts[] = '</div>';
            }
            $toc[] = sprintf(
                '<ul class="list-group"><a class="list-group-item" href="#ref_%s"><b>%s</b></a>%s</ul>',
                Lib::sanitizeFilename($block),
                htmlspecialchars($block),
                implode(PHP_EOL, $bk_toc)
            );
            $blocks[] = sprintf(
                '<h3 class="page-header" id="ref_%s">%s</h3>%s%s',
                Lib::sanitizeFilename($block),
                htmlspecialchars($block),
                PHP_EOL,
                implode(PHP_EOL, $bk_charts)
            );
        }
        $charts_js = implode(PHP_EOL, $charts);
        $packages_js = json_encode(['packages'=>array_keys($packages)]);

        // vars for template
        $Hostname = htmlspecialchars(rtrim(`hostname`));
        $toc_html = implode(PHP_EOL, $toc);
        $blocks_html = implode(PHP_EOL, $blocks);
        $Html_container =
<<<EOhtml
<div class="row">
  <div class="col-sm-3 col-sm-push-9 col-lg-2 col-lg-push-10">
    $toc_html
  </div>
  <div class="col-sm-9 col-sm-pull-3 col-lg-10 col-lg-pull-2">
    $blocks_html
  </div>
</div>
EOhtml;
        $json_data = self::JSON_DATA;
        $json_stats = self::JSON_STATS;
        $json_update = self::JSON_UPDATE;
        $Js_footer =
<<<EOjs
google.load('visualization', '1', {packages:['corechart']});
google.setOnLoadCallback(drawChart);
var GCharts={};
var Stats=["metric","first","min","avg","max","last"];
function loadJson(url,fn){
    var XHR=new XMLHttpRequest();
    XHR.onreadystatechange=function(){
        if(XHR.readyState==XMLHttpRequest.DONE && XHR.status==200)
            fn(JSON.parse(XHR.responseText));
    }
    XHR.open('POST',url);
    XHR.send();
}
function redraw(name){
    if(name===undefined){
        for(var i in GCharts)
            GCharts[i].draw();
    }else{
        for(var i in GCharts)
            if(i.match('^'+name))
                GCharts[i].draw();
    }
}
function update(id){
    for(var i in GCharts)
        getJsonDraw(i);
}
function updateStats(id,st,upd){
    var div=document.getElementById('stats_'+id);
    var t=document.createElement('table');
    var H=document.createElement('thead');
    var B=document.createElement('tbody');
    var F=document.createElement('tfoot');
    var r=document.createElement('tr');
    var d,ot;
    t.className='table table-condensed stats';
    for (var s in Stats){
        var h=document.createElement('th');
        h.appendChild(document.createTextNode(Stats[s]));
        r.appendChild(h);
    }
    H.appendChild(r);
    t.appendChild(H);
    for(var m in st){
        r=document.createElement('tr');
        for (s in Stats){
            d=document.createElement('td');
            if(st[m][s]!==null)
                d.appendChild(document.createTextNode(st[m][s]));
            r.appendChild(d);
        }
        B.appendChild(r);
    }
    t.appendChild(B);
    r=document.createElement('tr');
    d=document.createElement('td');
    r.appendChild(d);
    d=document.createElement('td');
    d.appendChild(document.createTextNode(upd));
    d.setAttribute('colspan',Stats.length);
    r.appendChild(d);
    F.appendChild(r);
    t.appendChild(F);
    if(ot=div.getElementsByTagName('table')[0])
        ot.remove();
    div.appendChild(t);
}
function getJsonDraw(id){
    loadJson(
        id+'.json',
        function(data){
            GCharts[id].setDataTable(data.$json_data);
            GCharts[id].draw();
            updateStats(id,data.$json_stats,data.$json_update)
        }
    );
}
function toggleMore(el,cl){
    var div=document.getElementsByClassName(cl)[0];
    if(/ in\$/.test(div.className)){
        div.className=div.className.replace(/ in\$/,'');
        el.innerHTML='More...';
    }else{
        div.className+=' in';
        el.innerHTML='Less...';
        redraw(cl);
    }
}
function drawChart(){
$charts_js
update();
}
window.onresize = function(){
    if(typeof ResizeInProgress==='undefined'){
        ResizeInProgress=true;
        window.setTimeout(function(){
            ResizeInProgress=undefined;
            redraw();
        },1000);
    }
};
window.setInterval(update,300000);

EOjs;

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
        $period_sanitized = [];
        foreach ($periods as $period_name=>$format)
            $period_sanitized[$period_name] = Lib::sanitizeFilename($period_name);

        foreach ($items as $block=>$bk_items)
        {
            foreach ($bk_items as $item_name=>$item)
            {
                $class = Lib::arrayExtract($item, self::VAR_CLASS, 'AreaChart');
                $title = Lib::arrayExtract($item, self::VAR_TITLE, $item_name);
                $options = Lib::arrayExtract($item, self::VAR_OPTIONS, []);

                $metric_titles = [];
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
                        $metric_titles[$metric_name] = Lib::arrayExtract($metric, self::METRIC_TITLE, $metric_name);
                        $metric_types[$metric_name] = Lib::arrayExtract($metric, self::METRIC_TYPE, Store::TYPE_VALUE);
                        $metric_evals[$metric_name] = Lib::arrayExtract($metric, self::METRIC_EVAL);
                        if (! Lib::arrayExtract($metric, self::METRIC_HIDDEN))
                            $metric_visibles[$metric_name] = $metric_titles[$metric_name];

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
                    $stats = [];
                    $lu = null;
                    foreach ($metric_visibles as $metric_name=>$label)
                    {
                        $r = [$label];
                        foreach ($store->getMetricStats($metric_name, $period_name, $metric_types[$metric_name]) as $stat_name=>$stat_value)
                        {
                            if ($stat_name != Store::STAT_UPDATE)
                                $r[] = isset($stat_value) ? round($stat_value, 4) : null;
                            elseif ($stat_value)
                                $lu = date('Y-m-d H:i:s', $stat_value);
                        }
                        $stats[] = $r;
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
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    );
                }
            }
        }

        return true;
    }
}
