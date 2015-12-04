<?php

use Plugins\CsvStore;
use Plugins\Export;
use Plugins\GChartsExport;
use Plugins\Store;
use Plugins\TextExport;

Class Monitask
{
    // global vars of ini file
    const VAR_INCLUDE   = 'include';

    // main sections
    const SECTION_DATASTORE = 'datastore';
    const SECTION_EXPORT    = 'export';
    const SECTION_COMMANDS  = 'commands';

	public static $platform;        // "freebsd"
	public static $architecture;    // "amd64"
    public static $ini;             // {ini file contents}
	public static $includes = [];   // {"file-full-name":true, ...}
	public static $commands = [];   // {"cmd-name":"command", ...}
	public static $metrics  = [];   // {"metric-name":["time", "value"], ...}
	public static $items    = [];   // {"block-name":[], ...}
	public static $store;           // {object of Store}
	public static $export;          // {object of Export}

	public static function init($config_file)
	{
        putenv('LANG=C');
        define('DIR', __DIR__);

        self::$platform = strtolower(trim(`uname`));
        self::$architecture = strtolower(trim(`uname -p`));

        if (self::$ini = self::parseIni($config_file))
		{
            // CORE DIRECTIVES

            // configure datastore
            $datastore = Lib::arrayExtract(self::$ini, self::SECTION_DATASTORE);
			if ($ds_type = Lib::arrayExtract($datastore, Store::VAR_TYPE))
			{
				switch ($ds_type)
				{
				case CsvStore::TYPE_CSV:
					self::$store = new CsvStore($datastore);
					break;
				default:
                    error_log(sprintf(
                        "%s is not an implemented engine, use '%s'",
                        $ds_type,
                        CsvStore::TYPE_CSV
                    ));
					return false;
				}

                if (! empty(self::$store->error))
                {
                    error_log(self::$store->error);
                    return false;
                }
			}

            // configure export
            $export = Lib::arrayExtract(self::$ini, self::SECTION_EXPORT);
			if (isset($export[Export::VAR_TYPE]))
			{
				$ex_type = Lib::arrayExtract($export, Export::VAR_TYPE);
				switch ($ex_type)
				{
				case TextExport::TYPE_TEXT:
					self::$export = new TextExport($export);
					break;
				case GChartsExport::TYPE_GCHARTS:
					self::$export = new GChartsExport($export);
					break;
				default:
                    error_log(sprintf(
                        "%s is not an implemented engine, use '%s' or '%s'",
                        $ex_type,
                        TextExport::TYPE_TEXT,
                        GChartsExport::TYPE_GCHARTS
                    ));
					return false;
				}

                if (! empty(self::$export->error))
                {
                    error_log(self::$export->error);
                    return false;
                }
			}

            // COMMON DIRECTIVES

            self::processIni(self::$ini);

            return true;
		}
		else
        {
            error_log("error parsing $config_file");
            return false;
        }
	}

	public static function parseIni($config_file)
	{
        if ($config_fullname = realpath($config_file)
            and $ini = parse_ini_file($config_fullname, true)
        )
        {
            $path = pathinfo($config_fullname);
            // merge platform config
            $platf_fullname =
                (isset($path['dirname']) ? $path['dirname'].DIRECTORY_SEPARATOR : '').
                "{$path['filename']}-".self::$platform.
                (isset($path['extension']) ? ".{$path['extension']}" : '');
            if (file_exists($platf_fullname)
                and $ini_platf = parse_ini_file($platf_fullname, true)
            )
            {
                $ini = array_replace_recursive($ini, $ini_platf);
            }
            // merge platform-architecture config
            $platf_arch_fullname =
                (isset($path['dirname']) ? $path['dirname'].DIRECTORY_SEPARATOR : '').
                "{$path['filename']}-".self::$platform.'-'.self::$architecture.
                (isset($path['extension']) ? ".{$path['extension']}" : '');
            if (file_exists($platf_arch_fullname)
                and $ini_platf_arch = parse_ini_file($platf_arch_fullname, true)
            )
            {
                $ini = array_replace_recursive($ini, $ini_platf_arch);
            }

            return $ini;
        }
        else
            return false;
    }

    public static function processIni($ini)
    {
        // merge commands
        $commands = Lib::arrayExtract($ini, self::SECTION_COMMANDS);
        if ($commands)
            self::$commands = array_merge_recursive(self::$commands, $commands);

        // get includes
        $includes = Lib::arrayExtract($ini, self::VAR_INCLUDE);

        // get block name and start periods
        $block = Lib::arrayExtract($ini, Store::VAR_BLOCK);

        // import items
        if (! empty($ini))
            self::$items[$block] = $ini;

        // process includes
        if ($includes)
        {
            foreach ((array)$includes as $include)
            {
                $file = realpath($include);
                if ($file and $ini2 = self::parseIni($file))
                {
                    if (empty(self::$includes[$file]))
                    {
                        self::$includes[$file] = true;
                        self::processIni($ini2);
                    }
                }
                else
                    error_log("include file $include does not exist or cannot be parsed");
            }
        }

        return true;
    }

	public static function createStore()
    {
        if (empty(self::$store))
        {
            error_log('['.self::SECTION_DATASTORE.'] section is not configured');
            return false;
        }
        elseif (self::$store->create())
            return true;
        else
        {
            error_log(self::$store->error);
            return false;
        }
    }

	public static function outputTemplate()
    {
        if (empty(self::$export))
        {
            error_log('['.self::SECTION_EXPORT.'] section is not configured');
            return false;
        }
        elseif (empty(self::$store))
        {
            error_log('['.self::SECTION_DATASTORE.'] section is not configured');
            return false;
        }
        elseif (self::$export->template(self::$items, self::$store))
            return true;
        else
        {
            error_log(self::$export->error);
            return false;
        }
    }

	public static function collectMetrics()
    {
        if (empty(self::$store))
        {
            error_log('['.self::SECTION_DATASTORE.'] section is not configured');
            return false;
        }
        elseif (empty(self::$store->periods))
        {
            error_log(Store::VAR_PERIOD.' directive is not set');
            return false;
        }

        $errors = [];
        foreach (self::$commands as $key=>$cmd)
        {
            // run commands
            $errorlevel = 0;
            $output = [];
            exec($cmd, $output, $errorlevel);
            if ($errorlevel)
                $errors[] = "error executing $key: $cmd";
            elseif (empty($output))
                ;
            elseif ($output and preg_match_all('/^(\S+)\s+(.+)$/m', implode(PHP_EOL, $output), $m))
            {
                // collect metrics
                foreach ($m[1] as $k=>$v)
                    self::$metrics[$v] = rtrim($m[2][$k]);
            }
            else
                $errors[] = "unformatted output from $key: $cmd";
        }

        // store metrics
        if (self::$metrics
            and ! self::$store->insertMetrics($_SERVER['REQUEST_TIME'], self::$metrics)
        )
            $errors[] = self::$store->error;

        if ($errors)
        {
            error_log(implode(PHP_EOL, $errors));
            return false;
        }

        return true;
	}

	public static function exportData()
    {
        if (empty(self::$export))
        {
            error_log('['.self::SECTION_EXPORT.'] section is not configured');
            return false;
        }
        elseif (empty(self::$items))
        {
            error_log('export items are not configured');
            return false;
        }
        elseif (empty(self::$store->periods))
        {
            error_log(Store::VAR_PERIOD.' directive is not set');
            return false;
        }
        elseif (self::$store->load())
        {
            if (self::$export->export(self::$items, self::$store))
                return true;
            else
            {
                error_log(self::$export->error ? self::$export->error : 'error exporting data');
                return false;
            }
        }
        else
        {
            error_log(self::$store->error ? self::$store->error : 'error loading data from datastore');
            return false;
        }
    }
}
