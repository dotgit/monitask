<!DOCTYPE html>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<title><?=$Hostname?> - Monitask</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
<style>
    body{padding-top:70px;background-color:#f8f8f8;}
    .page-header{padding-top:55px;margin-top:0;margin-bottom:0;}
    .lead{padding-top:55px;margin-top:0;margin-bottom:0;}
    .table{margin-bottom:0;}
    .table-condensed>tbody>tr>td,
    .table-condensed>tbody>tr>th,
    .table-condensed>tfoot>tr>td,
    .table-condensed>tfoot>tr>th,
    .table-condensed>thead>tr>td,
    .table-condensed>thead>tr>th{padding:2px;}
    .table-condensed>tfoot>tr>td,
    .table-condensed>tfoot>tr>th{padding-bottom:0;}
    table.stats th,table.stats td{text-align:right;}
    table.stats th:first-child,table.stats td:first-child{text-align:left;}
</style>
<body>

<nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container-fluid">
        <div class="nav navbar-brand">
            <?=$Hostname?>
        </div>

        <button id="refresh_btn" class="navbar-btn btn btn-default pull-right" onclick="update()">
            <span style="font-weight:900;">&orarr;</span>
            <span class="hidden-xs">Refresh</span>
        </button>

        <span class="navbar-btn dropdown pull-right">
            <button class="btn btn-default dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Section <span class="caret"></span></button>
            <ul class="dropdown-menu">
            <?=$Toc_html?>
            </ul>
        </span>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <div class="col" style="margin:10px;">
            <?=$Blocks_html?>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
<script type="text/javascript" src="//www.google.com/jsapi"></script>
<script type="text/javascript">
    var Blocker=0;
    var GCharts={};
    var Stats=["metric","first","min","avg","max","last"];
    google.load('visualization', '1', <?=$Packages_js?>);
    google.setOnLoadCallback(drawCharts);
    function loadJson(url,fn){
        var XHR=new XMLHttpRequest();
        incrementBlocker();
        XHR.onreadystatechange=function(){
            if(XHR.readyState===XMLHttpRequest.DONE){
                if(XHR.status===200)
                    fn(JSON.parse(XHR.responseText));
                else
                    decrementBlocker();
            }
        }
        XHR.open('<?=$Ajax_method?>',url);
        XHR.send();
    }
    function incrementBlocker(){
        if(!Blocker++)
            document.getElementById('refresh_btn').disabled=true;
    }
    function decrementBlocker(){
        if(!--Blocker)
            document.getElementById('refresh_btn').disabled=false;
    }
    function redraw(name){
        if(name===undefined){
            for(var i in GCharts){
                incrementBlocker();
                GCharts[i].draw();
            }
        }else{
            for(var i in GCharts){
                if(i.match('^'+name)){
                    incrementBlocker();
                    GCharts[i].draw();
                }
            }
        }
    }
    function getJsonDraw(id){
        loadJson(
            id+'.json',
            function(data){
                GCharts[id].setDataTable(data.<?=$Json_data?>);
                GCharts[id].setOption('hAxis.minValue',data.<?=$Json_from?>);
                GCharts[id].draw();
                updateStats(id,data.<?=$Json_stats?>,data.<?=$Json_update?>);
            }
        );
    }
    function update(){
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
        if(upd!==null)
            d.appendChild(document.createTextNode(upd));
        d.setAttribute('colspan',Stats.length);
        r.appendChild(d);
        F.appendChild(r);
        t.appendChild(F);
        if(ot=div.getElementsByTagName('table')[0])
            ot.remove();
        div.appendChild(t);
    }
    function toggleMore(el,cl){
        var div=document.getElementsByClassName(cl)[0];
        if(/ in$/.test(div.className)){
            div.className=div.className.replace(/ in$/,'');
            el.innerHTML='More...';
        }else{
            div.className+=' in';
            el.innerHTML='Less...';
            redraw(cl);
        }
    }
    function drawCharts(){
        <?=$Charts_js?>
        for(var i in GCharts)
            google.visualization.events.addListener(GCharts[i], 'ready', decrementBlocker);
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
</script>
