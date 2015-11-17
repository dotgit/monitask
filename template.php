<!DOCTYPE html>
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="3600">
<title>RRDok site statistics</title>
<link rel="stylesheet" type="text/css" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="//netdna.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css">
<link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Roboto:400,100,700">
<link rel="icon" href="//oss.oetiker.ch/rrdtool/inc/favicon.ico" type="image/ico">
<style>
    body{font-family:Roboto;}
    .row{padding:5px;}
    .row.older{padding-top:0px;}
    .row.sw1{background-color:#f8f8f8;}
</style>
<body>
    <div class="container">
<?php

$sw = 1;
foreach ($PLUGIN_RRD_IMAGES as $plugin=>$rrd_images)
{
    $rrds = array();
    $buffer = array();
    foreach ($rrd_images as $rrd=>$images)
    {
        $sw = ++$sw % 2;
        $sw_class = " sw$sw";
        $rrds[] = htmlspecialchars($rrd);
        foreach ($images as $image=>$attr)
        {
            $prefix = $suffix = null;
            switch (basename(strrchr($image, '-'), '.png'))
            {
            case '-day':
                $prefix =
                    "<div class=\"row newer$sw_class\">".
                    "<div class=\"col-md-12 text-center\">\n";
                break;
            case '-week':
                $suffix =
                    "</div>".
                    "</div>\n";
                break;
            case '-month':
                $prefix =
                    "<div class=\"row older$sw_class\" id=\"$rrd-older\">".
                    "<div class=\"col-md-12 text-center\">\n";
                break;
            case '-year':
                $suffix =
                    "</div>".
                    "</div>\n";
                break;
            }
            $buffer[] =
                $prefix.
                "<img class=\"img-rounded toggler\" data-row-id=\"$rrd-older\" src=\"{$attr['src']}\" width=\"{$attr['width']}\" height=\"{$attr['height']}\"> ".
                "$suffix";
        }
    }
    echo
        '<h1 class="page-header">'.htmlspecialchars($plugin)." <small>".implode(', ', $rrds)."</small></h1>\n".
        implode('', $buffer);
}
?>
    </div>
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script type="text/javascript" src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
<script type="text/javascript">
$('.older').toggle();
$('.toggler').click(function(){$('#'+$(this).data('rowId')).toggle();});
</script>
