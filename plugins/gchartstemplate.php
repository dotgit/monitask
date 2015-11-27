<!DOCTYPE html>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<title><?=$Hostname?> - Monitask</title>
<link rel="stylesheet" type="text/css" href="http://netdna.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
<style>
body{padding-top:70px;background-color:#eee;}
</style>
<body>

<nav class="navbar navbar-inverse navbar-fixed-top">
  <div class="container">
    <div class="navbar-header">
      <div class="navbar-brand"><?=$Hostname?></div>
    </div>
    <div class="navbar-right" style="padding-left:1px;">
      <button href="#" class="navbar-btn btn btn-default" onclick="update()">Reload</button>
    </div>
    <div class="navbar-right navbar-text">Last update: <span id="<?=$Time_id?>"></span>&nbsp;</div>
  </div>
</nav>

<div class="container">
<?=$Html_container?>
</div>

<script type="text/javascript" src="http://www.google.com/jsapi"></script>
<script type="text/javascript">
<?=$Js_footer?>
</script>
