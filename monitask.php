<?php

use Plugins\CsvStore;
use Plugins\SQLiteStore;
use Plugins\TableExport;
use Plugins\TextExport;

Class Monitask
{
    // global vars
    const VAR_INCLUDE   = 'include';
    const VAR_BLOCK     = 'block';
    const VAR_PERIOD    = 'period';

    // main sections
    const SECTION_DATASTORE = 'datastore';
    const SECTION_EXPORT    = 'export';
    const SECTION_COMMANDS  = 'commands';

    // datastore types
    const DATASTORE_TYPE    = 'type';
    const DS_TYPE_SQLITE    = 'sqlite';
    const DS_TYPE_CSV       = 'csv';

    // export types
    const EXPORT_TYPE   = 'type';
    const XP_TYPE_TEXT  = 'text';
    const XP_TYPE_TABLE = 'table';

	public static $platform;        // "freebsd"
    public static $ini;             // {ini file contents}
	public static $periods = [];    // {"period-name":"strtotime-pattern", ...}
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

        if (self::$ini = self::parseIni($config_file))
		{
            // CORE DIRECTIVES

            // configure periods
            self::$periods = Lib::arrayExtract(self::$ini, self::VAR_PERIOD);
            if (! is_array(self::$periods))
                self::$periods = [];

            // configure datastore
            $datastore = Lib::arrayExtract(self::$ini, self::SECTION_DATASTORE);
			if ($ds_type = Lib::arrayExtract($datastore, self::DATASTORE_TYPE))
			{
				switch ($ds_type)
				{
				case self::DS_TYPE_CSV:
					self::$store = new CsvStore($datastore, self::$periods);
					break;
				case self::DS_TYPE_SQLITE:
					self::$store = new SQLiteStore($datastore);
					break;
				default:
                    error_log(sprintf(
                        "%s is not an implemented engine, use '%s' or '%s'",
                        $ds_type,
                        self::DS_TYPE_CSV,
                        self::DS_TYPE_SQLITE
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
			if (isset($export[self::EXPORT_TYPE]))
			{
				$ex_type = Lib::arrayExtract($export, self::EXPORT_TYPE);
				switch ($ex_type)
				{
				case self::XP_TYPE_TEXT:
					self::$export = new TextExport($export);
					break;
				case self::XP_TYPE_TABLE:
					self::$export = new TableExport($export);
					break;
				default:
                    error_log(sprintf(
                        "%s is not an implemented engine, use '%s' or '%s'",
                        $ex_type,
                        self::XP_TYPE_TEXT,
                        self::XP_TYPE_TABLE
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
        if ($config_fullname = realpath($config_file) and $ini = parse_ini_file($config_fullname, true))
        {
            // merge platform config
            $path = pathinfo($config_fullname);
            $platform_fullname = $path['dirname'].DIRECTORY_SEPARATOR.
                "{$path['filename']}-".self::$platform.
                (isset($path['extension']) ? ".{$path['extension']}" : '');
            if (file_exists($platform_fullname) and $ini_pl = parse_ini_file($platform_fullname, true))
                $ini = array_replace_recursive($ini, $ini_pl);

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
        $block = Lib::arrayExtract($ini, self::VAR_BLOCK);

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
        include 'template.php';
    }

	public static function collectMetrics()
    {
        if (empty(self::$store))
        {
            error_log('['.self::SECTION_DATASTORE.'] section is not configured');
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
        elseif (empty(self::$periods))
        {
            error_log(self::VAR_PERIOD.' directive is not set');
            return false;
        }
        elseif (self::$store->load())
        {
            if (self::$export->export(self::$items, self::$periods, self::$store))
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
