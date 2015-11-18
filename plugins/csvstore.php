<?php

namespace Plugins;

Class CsvStore extends Store
{
    // datastore section of ini file
    const VAR_FILENAME = 'filename';

    const MODE_READ     = 'r';
    const MODE_WRITE    = 'a';

    // database description
    const FLD_TIME      = 0;
    const FLD_METRIC    = 1;
    const FLD_VALUE     = 2;

	public $filename;
	public $handle;

	public function __construct($params)
	{
        if (empty($params[self::VAR_FILENAME]))
            $this->error = __METHOD__.': '.self::VAR_FILENAME.' parameter not set';
        else
    		$this->filename = $params[self::VAR_FILENAME];
	}

	public function create()
	{
		if (file_exists($this->filename))
		{
			$this->error = __METHOD__.": $this->filename datafile already exists";
			return false;
		}
		elseif (touch($this->filename))
			return true;
		else
        {
			$this->error = __METHOD__.": error creating $this->filename datafile";
			return false;
        }
	}

	public function open($mode=self::MODE_READ)
	{
        if (! file_exists($this->filename))
        {
            $this->error = __METHOD__.": $this->filename datafile does not exist";
            return false;
        }
        elseif (! $this->handle = fopen($this->filename, $mode))
        {
            $this->error = __METHOD__.": cannot open $this->filename datafile";
            return false;
        }

        return true;
	}

	public function close()
	{
        if (! $this->handle)
        {
            $this->error = __METHOD__.": $this->filename datafile is not open";
            return false;
        }
        elseif (! $this->handle = fclose($this->filename))
        {
            $this->error = __METHOD__.": cannot close $this->filename datafile";
            return false;
        }

        return true;
	}

	public function insertOne($time, $metric, $value)
	{
        if (empty($this->handle) and ! $this->open(self::MODE_WRITE))
            return false;

        if (fputcsv($this->handle, [$time, $metric, $value]))
            return 1;
        else
        {
            $this->error = sprintf(
                "%s: error inserting '%s' = '%s' at %s into %s datafile",
                __METHOD__,
                $metric,
                $value,
                date('Y-m-d H:i:s', $time),
                $this->filename
            );
            return false;
        }
	}

	public function insertMany($metrics=[])
	{
        if (empty($this->handle) and ! $this->open(self::MODE_WRITE))
            return false;

        $errors = [];
        if (! empty($metrics) and is_array($metrics))
        {
            foreach ((array)$metrics as $metric=>$arr)
                if (! fputcsv($this->handle, [$arr[0], $metric, $arr[1]]))
                    $errors[] = sprintf(
                        "%s: error inserting '%s' = '%s' at %s into %s datafile",
                        __METHOD__,
                        $metric,
                        $arr[1],
                        date('Y-m-d H:i:s', $arr[0]),
                        $this->filename
                    );
        }

        if ($errors)
        {
            $this->error = implode(PHP_EOL, $errors);
            return false;
        }
        else
            return true;
	}

    public function load($items, $periods)
	{
        if (empty($this->handle) and ! $this->open(self::MODE_READ))
            return false;

        $now = $_SERVER['REQUEST_TIME'];
        $start_times = [];          // {"-2 days":"start-time",...}
        $period_times = [];         // {"-2 days":["bin1time","bin2time",...],...}
        $metric_period_bins = [];   // {"metric":{"-2 days":{"bin1time":{},...},...},...}
        $errors = [];
        foreach ($periods as $name=>$pattern)
        {
            if ($start_tm = strtotime($pattern, $now))
            {
                $start_times[$name] = $start_tm;
                $delta = (int)(($now - $start_tm) / self::LOAD_BINS_CNT);
                $period_times[$name] = [];
                for ($i = $start_tm + $delta; $i < $now; $i += $delta)
                    $period_times[$name][] = $i;
                $period_times[$name][] = $now;
            }
            else
                $errors[] = sprintf(
                    "%s: '%s' is not a valid strtotime pattern in period['%s']",
                    __METHOD__,
                    $pattern,
                    $name
                );
        }

        if ($errors)
        {
            $this->error = implode(PHP_EOL, $errors);
            return false;
        }
        if (empty($start_times))
        {
            $this->error = __METHOD__.': periods are not defined';
            return false;
        }
        $min_time = min($start_times);
        if (empty($min_time))
        {
            $this->error = __METHOD__.': periods are not defined';
            return false;
        }

        // skip older records
        do
        {
            $line = fgetcsv($this->handle);
        }
        while ($line !== false
            and (empty($line[self::FLD_TIME]) or $line[self::FLD_TIME] < $min_time)
        );

        // exit if no fresh records found
        if ($line === false)
        {
            $this->error = __METHOD__.': file scanned, no data found';
            return false;
        }

        // read fresh records and store in corresponding bins
        for (;$line !== false; $line = fgetcsv($this->handle))
        {
            if (! isset($line[self::FLD_VALUE]))
                continue;

            $line_time = $line[self::FLD_TIME];
            foreach ($start_times as $name=>$start_tm)
            {
                if ($line_time > $start_tm)
                {
                    foreach ($period_times[$name] as $tm)
                        if ($line_time < $tm)
                            break;
                    if (empty($metric_period_bins[$line[self::FLD_METRIC]][$name][$tm]))
                        $metric_period_bins[$line[self::FLD_METRIC]][$name][$tm] = [
                            self::BIN_VALUE_MIN=>$line[self::FLD_VALUE],
                            self::BIN_VALUE_MAX=>$line[self::FLD_VALUE],
                            self::BIN_VALUE_SUM=>$line[self::FLD_VALUE],
                            self::BIN_VALUE_CNT=>1,
                        ];
                    else
                    {
                        $bin = &$metric_period_bins[$line[self::FLD_METRIC]][$name][$tm];
                        if ($line[self::FLD_VALUE] < $bin[self::BIN_VALUE_MIN])
                            $bin[self::BIN_VALUE_MIN] = $line[self::FLD_VALUE];
                        if ($bin[self::BIN_VALUE_MAX] < $line[self::FLD_VALUE])
                            $bin[self::BIN_VALUE_MAX] = $line[self::FLD_VALUE];
                        $bin[self::BIN_VALUE_SUM] += $line[self::FLD_VALUE];
                        ++$bin[self::BIN_VALUE_CNT];
                    }
                }
            }
        }

        return $metric_period_bins;
	}
}
