<?php

namespace Plugins;

Class CsvStore extends Store
{
    // datastore section of ini file
    const VAR_FILENAME  = 'filename';
    const VAR_START_TIME= 'start_time';
    const VAR_BINS  = 'bins';

    // file open modes
    const MODE_READ     = 'r';
    const MODE_WRITE    = 'w';

	public $filename;
	public $handle;
    public $start_time;
    public $periods_seconds = [];
	public $bins_count;

	public function __construct($params, $periods)
	{
        // set bins count
        $this->bins_count = ! empty($params[self::VAR_BINS]) ? (int)$params[self::VAR_BINS] : 100;
        if ($this->bins_count < 1)
        {
            $this->error = __METHOD__.': '.self::VAR_BINS.' parameter must be positive integer';
            return;
        }

        // set start time
        $this->start_time = strtotime(isset($params[self::VAR_START_TIME]) ? $params[self::VAR_START_TIME] : '2015-01-01');
        if (empty($this->start_time))
        {
            $this->error = __METHOD__.': '.self::VAR_START_TIME.' parameter not set or is not formatted as YYYY-MM-DD';
            return;
        }

        // set datastore filename
        if (! isset($params[self::VAR_FILENAME]))
        {
            $this->error = __METHOD__.': '.self::VAR_FILENAME.' parameter not set';
            return;
        }
        $this->filename = $params[self::VAR_FILENAME];

        // set periods
        $errors = [];
        $this->periods = $periods;
        if (! is_array($this->periods))
        {
            $this->error = __METHOD__.': period must be an array';
            return;
        }
        foreach ($periods as $name=>$format)
        {
            if ($period_tm = strtotime($format)
                and $period_tm < $_SERVER['REQUEST_TIME']
            )
                $this->periods_seconds[$name] = (int)(($_SERVER['REQUEST_TIME'] - $period_tm)/$this->bins_count);
            else
                $errors[] = __METHOD__.": wrong strtotime format '$format' in period '$name'";
        }

        if ($errors)
            $this->error = implode(PHP_EOL, $errors);
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
        if (! $this->handle or fclose($this->handle))
        {
            unset($this->handle);
            return true;
        }
        else
        {
            $this->error = __METHOD__.": cannot close $this->filename datafile";
            return false;
        }
	}

	public function periodNextBin($period, $time, $bin_times=[])
	{
        $tm = $bin_times ? max($bin_times) : $this->start_time;
        while ($tm < $time)
            $tm += $this->periods_seconds[$period];
        return $tm;
	}

	public function insertMetrics($time, $metrics=[])
	{
        if ($this->load() === false)
            return false;

        // insert metrics into existing structure or create a new one
        $period_first = [];
        foreach ($metrics as $metric=>$value)
        {
            if (isset($this->metric_period_bins[$metric]))
            {
                foreach ($this->periods as $period=>$format)
                {
                    if (isset($this->metric_period_bins[$metric][$period]))
                    {
                        $bin_tm = $this->periodNextBin($period, $time, array_keys($this->metric_period_bins[$metric][$period]));
                        if (isset($this->metric_period_bins[$metric][$period][$bin_tm]))
                        {
                            $bin = &$this->metric_period_bins[$metric][$period][$bin_tm];
                            $bin[self::BIN_LAST_TIME] = $time;
                            $bin[self::BIN_LAST_VALUE] = $value;
                            if ($value < $bin[self::BIN_MIN_VALUE])
                                $bin[self::BIN_MIN_VALUE] = $value;
                            if ($bin[self::BIN_MAX_VALUE] < $value)
                                $bin[self::BIN_MAX_VALUE] = $value;
                            $bin[self::BIN_SUM] += $value;
                            ++$bin[self::BIN_COUNT];
                        }
                        else
                        {
                            $this->metric_period_bins[$metric][$period][$bin_tm] = [
                                self::BIN_LAST_TIME=>$time,
                                self::BIN_LAST_VALUE=>$value,
                                self::BIN_MIN_VALUE=>$value,
                                self::BIN_MAX_VALUE=>$value,
                                self::BIN_SUM=>$value,
                                self::BIN_COUNT=>1,
                            ];

                            // check number of bins and remove older ones
                            $bin_times = array_keys($this->metric_period_bins[$metric][$period]);
                            sort($bin_times, SORT_NUMERIC);
                            while (count($bin_times) > $this->bins_count)
                                unset($this->metric_period_bins[$metric][$period][array_shift($bin_times)]);
                        }
                    }
                    else
                    {
                        if (empty($period_first[$period]))
                            $period_first[$period] = $this->periodNextBin($period, $time);
                        $this->metric_period_bins[$metric][$period][$period_first[$period]] = [
                            self::BIN_LAST_TIME=>$time,
                            self::BIN_LAST_VALUE=>$value,
                            self::BIN_MIN_VALUE=>$value,
                            self::BIN_MAX_VALUE=>$value,
                            self::BIN_SUM=>$value,
                            self::BIN_COUNT=>1,
                        ];
                    }
                }
            }
            else
            {
                if (empty($period_first))
                {
                    foreach ($this->periods as $period=>$format)
                        $period_first[$period] = $this->periodNextBin($period, $time);
                }
                foreach ($this->periods as $period=>$format)
                {
                    $this->metric_period_bins[$metric][$period][$period_first[$period]] = [
                        self::BIN_LAST_TIME=>$time,
                        self::BIN_LAST_VALUE=>$value,
                        self::BIN_MIN_VALUE=>$value,
                        self::BIN_MAX_VALUE=>$value,
                        self::BIN_SUM=>$value,
                        self::BIN_COUNT=>1,
                    ];
                }
            }
        }

        // write the structure to the file
        if (! $this->open(self::MODE_WRITE))
            return false;

        $errors = [];
        foreach ($this->metric_period_bins as $metric=>$periods)
        {
            foreach ($periods as $period=>$bins)
            {
                foreach ($bins as $bin_tm=>$values)
                {
                    if (! fputcsv($this->handle, array_merge([$metric, $period, $bin_tm], $values)))
                        $errors[] = sprintf(
                            "%s: error updating metric '%s', period '%s', bin '%s' in %s datafile",
                            __METHOD__,
                            $metric,
                            $period,
                            date('Y-m-d H:i:s', $bin_tm),
                            $this->filename
                        );
                }
            }
        }
        if ($errors)
        {
            $this->error = implode(PHP_EOL, $errors);
            return false;
        }

        return true;
	}

    public function load()
	{
        if (empty($this->handle) and ! $this->open(self::MODE_READ))
            return false;

        // read fresh records and store in corresponding bins
        while ($line = fgetcsv($this->handle))
        {
            if (! isset($line[7]))
                continue;

            list($metric, $period, $bin_tm, $time, $last, $min, $max, $sum, $cnt) = $line;
            $this->metric_period_bins[$metric][$period][$bin_tm] = [
                self::BIN_LAST_TIME=>$time,
                self::BIN_LAST_VALUE=>$last,
                self::BIN_MIN_VALUE=>$min,
                self::BIN_MAX_VALUE=>$max,
                self::BIN_SUM=>$sum,
                self::BIN_COUNT=>$cnt,
            ];
        }

        return $this->close();
	}
}
