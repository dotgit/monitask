#!/usr/local/bin/php
<?php

set_include_path(__DIR__.PATH_SEPARATOR.get_include_path());
spl_autoload_register();

// check input params
if ($argc < 2 or ! in_array($argv[1], ['--datastore', '--template', '--collect', '--export']))
    die(
        'Usage: '.$argv[0].' [--datastore, --template, --collect, --export] [path-to-config.ini]'.PHP_EOL.
        'where'.PHP_EOL.
        '  --datastore  creates datastore'.PHP_EOL.
        '  --template   outputs a template file'.PHP_EOL.
        '  --collect    collects metrics and updates datastore'.PHP_EOL.
        '  --export     analyzes and exports datastore data for use in template'.PHP_EOL
    );

// set $OP and $Config from input
$Op = $argv[1];
$Config = realpath(isset($argv[2]) ? $argv[2] : (__DIR__.'/ini/monitask.ini'));

if (! $Config)
    die("Config file {$argv[2]} does not exist");

if (! Monitask::init($Config))
    exit(1);

switch ($Op)
{
    case '--datastore': $result = Monitask::createStore(); break;
    case '--template': $result = Monitask::outputTemplate(); break;
    case '--collect': $result = Monitask::collectMetrics(); break;
    case '--export': $result = Monitask::exportData(); break;
}

exit ($result ? 0 : 2);
