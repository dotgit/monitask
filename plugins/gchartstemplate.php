<!DOCTYPE html>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<title><?=$Hostname?> - Monitask</title>
<link rel="stylesheet" type="text/css" href="//netdna.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
<style>
body{padding-top:70px;background-color:#f8f8f8;}
.page-header{padding-top:55px;margin-top:0;margin-bottom:0;}
.lead{padding-top:55px;margin-top:0;margin-bottom:0;}
table.stats th,table.stats td{text-align:right;}
table.stats th:first-child,table.stats td:first-child{text-align:left;}
</style>
<body>

<nav class="navbar navbar-inverse navbar-fixed-top">
  <div class="container">
    <div class="pull-right">
      <button id="refresh_btn" href="#" class="navbar-btn btn btn-default" onclick="update()">
        <span style="font-weight:900;">&orarr;</span>
        <span class="hidden-xs">Refresh</span>
      </button>
    </div>
    <div class="navbar-header">
      <div class="navbar-brand"><?=$Hostname?></div>
    </div>
  </div>
</nav>

<div class="container">
  <div class="row">
    <div class="col-sm-3 col-sm-push-9 col-lg-2 col-lg-push-10">
      <?=$Toc_html?>
    </div>
    <div class="col-sm-9 col-sm-pull-3 col-lg-10 col-lg-pull-2">
      <?=$Blocks_html?>
    </div>
  </div>
</div>

<script type="text/javascript" src="//www.google.com/jsapi"></script>
<script type="text/javascript">
google.load('visualization', '1', {packages:['corechart']});
google.setOnLoadCallback(drawCharts);
var Blocker=0;
var GCharts={};
var Stats=["metric","first","min","avg","max","last"];
function loadJson(url,fn){
  var XHR=new XMLHttpRequest();
  XHR.onreadystatechange=function(){
    if(XHR.readyState==XMLHttpRequest.DONE && XHR.status==200)
      fn(JSON.parse(XHR.responseText));
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
      google.visualization.events.addListener(GCharts[i], 'ready', decrementBlocker);
    }
  }else{
    for(var i in GCharts){
      if(i.match('^'+name)){
        incrementBlocker();
        GCharts[i].draw();
        google.visualization.events.addListener(GCharts[i], 'ready', decrementBlocker);
      }
    }
  }
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
function getJsonDraw(id){
  incrementBlocker();
  loadJson(
    id+'.json',
    function(data){
      GCharts[id].setDataTable(data.<?=$Json_data?>);
      GCharts[id].draw();
      updateStats(id,data.<?=$Json_stats?>,data.<?=$Json_update?>);
      decrementBlocker();
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
function drawCharts(){
<?=$Charts_js?>
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
