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

        // insert metrics into existing bin or create a new one
        $period_first = [];
        foreach ($metrics as $metric=>$value)
        {
            foreach ($this->periods as $period=>$format)
            {
                if (isset($this->metric_period_bins[$metric][$period]))
                {
                    $bin_times = array_keys($this->metric_period_bins[$metric][$period]);
                    $bin_prev_id = max($bin_times);
                    $bin_id = $this->periodNextBin($period, $time, $bin_times);
                }
                elseif (isset($period_first[$period]))
                {
                    $bin_prev_id = null;
                    $bin_id = $period_first[$period];
                }
                else
                {
                    $bin_prev_id = null;
                    $bin_id = $period_first[$period] = $this->periodNextBin($period, $time);
                }

                if (isset($this->metric_period_bins[$metric][$period][$bin_id]))
                {
                    $bin = &$this->metric_period_bins[$metric][$period][$bin_id];
                    $time_inc = $time - $bin[self::BIN_LAST_TIME];
                    $value_inc = $value - $bin[self::BIN_LAST_VALUE];

                    // only update if time changed
                    if ($time_inc)
                    {
                        // set last value and increment
                        $bin[self::BIN_LAST_TIME] = $time;
                        $bin[self::BIN_LAST_TM_INC] = $time_inc;
                        $bin[self::BIN_LAST_VALUE] = $value;
                        $bin[self::BIN_LAST_INC] = $value_inc;
                        $rate = $value_inc / $time_inc;
                        // set min value and rate
                        if ($value < $bin[self::BIN_MIN_VALUE])
                            $bin[self::BIN_MIN_VALUE] = $value;
                        if ($rate < $bin[self::BIN_MIN_INC]/$bin[self::BIN_MIN_TM_INC])
                        {
                            $bin[self::BIN_MIN_TM_INC] = $time_inc;
                            $bin[self::BIN_MIN_INC] = $value_inc;
                        }
                        // set max value and rate
                        if ($bin[self::BIN_MAX_VALUE] < $value)
                            $bin[self::BIN_MAX_VALUE] = $value;
                        if ($bin[self::BIN_MAX_INC]/$bin[self::BIN_MAX_TM_INC] < $rate)
                        {
                            $bin[self::BIN_MAX_TM_INC] = $time_inc;
                            $bin[self::BIN_MAX_INC] = $value_inc;
                        }
                        // set sum of values and increments
                        $bin[self::BIN_SUM_VALUE] += $value;
                        $bin[self::BIN_SUM_INC] += $value_inc;
                        ++$bin[self::BIN_COUNT];
                    }
                }
                else
                {
                    $value_inc = isset($bin_prev_id)
                        ? $value - $this->metric_period_bins[$metric][$period][$bin_prev_id][self::BIN_LAST_VALUE]
                        : 0;
                    $time_inc = isset($bin_prev_id)
                        ? $time - $this->metric_period_bins[$metric][$period][$bin_prev_id][self::BIN_LAST_TIME]
                        : $this->periods_seconds[$period];
                    $this->metric_period_bins[$metric][$period][$bin_id] = [
                        self::BIN_FIRST_TIME=>$time,
                        self::BIN_FIRST_TM_INC=>$time_inc,
                        self::BIN_FIRST_VALUE=>$value,
                        self::BIN_FIRST_INC=>$value_inc,
                        self::BIN_LAST_TIME=>$time,
                        self::BIN_LAST_TM_INC=>$time_inc,
                        self::BIN_LAST_VALUE=>$value,
                        self::BIN_LAST_INC=>$value_inc,
                        self::BIN_MIN_TM_INC=>$time_inc,
                        self::BIN_MIN_VALUE=>$value,
                        self::BIN_MIN_INC=>$value_inc,
                        self::BIN_MAX_TM_INC=>$time_inc,
                        self::BIN_MAX_VALUE=>$value,
                        self::BIN_MAX_INC=>$value_inc,
                        self::BIN_SUM_VALUE=>$value,
                        self::BIN_SUM_INC=>$value_inc,
                        self::BIN_COUNT=>1,
                    ];
                }

                // if added new bin then check number of bins and remove older ones
                if ($bin_prev_id != $bin_id and isset($bin_prev_id))
                {
                    $bin_times = array_keys($this->metric_period_bins[$metric][$period]);
                    sort($bin_times, SORT_NUMERIC);
                    while (count($bin_times) > $this->bins_count)
                        unset($this->metric_period_bins[$metric][$period][array_shift($bin_times)]);
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
                foreach ($bins as $bin_id=>$bin)
                {
                    if (! fputcsv($this->handle, array_merge([$metric, $period, $bin_id], $bin)))
                        $errors[] = sprintf(
                            "%s: error updating metric '%s', period '%s', bin '%s' in %s datafile",
                            __METHOD__,
                            $metric,
                            $period,
                            date('Y-m-d H:i:s', $bin_id),
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
            if (count($line) < 16)
                continue;

            list(
                $metric, $period, $bin_tm,
                $first_time, $first_tm_inc, $first_value, $first_inc,
                $last_time, $last_tm_inc, $last_value, $last_inc,
                $min_tm_inc, $min_value, $min_inc,
                $max_tm_inc, $max_value, $max_inc,
                $sum_value, $sum_inc,
                $cnt
            ) = $line;

            if ($first_tm_inc == $this->periods_seconds[$period])
                $first_tm_inc = 300;
            if ($last_tm_inc == $this->periods_seconds[$period])
                $last_tm_inc = 300;
            if ($min_tm_inc == $this->periods_seconds[$period])
                $min_tm_inc = 300;
            if ($max_tm_inc == $this->periods_seconds[$period])
                $max_tm_inc = 300;

            $this->metric_period_bins[$metric][$period][$bin_tm] = [
                self::BIN_FIRST_TIME=>$first_time,
                self::BIN_FIRST_TM_INC=>$first_tm_inc,
                self::BIN_FIRST_VALUE=>$first_value,
                self::BIN_FIRST_INC=>$first_inc,
                self::BIN_LAST_TIME=>$last_time,
                self::BIN_LAST_TM_INC=>$last_tm_inc,
                self::BIN_LAST_VALUE=>$last_value,
                self::BIN_LAST_INC=>$last_inc,
                self::BIN_MIN_TM_INC=>$min_tm_inc,
                self::BIN_MIN_VALUE=>$min_value,
                self::BIN_MIN_INC=>$min_inc,
                self::BIN_MAX_TM_INC=>$max_tm_inc,
                self::BIN_MAX_VALUE=>$max_value,
                self::BIN_MAX_INC=>$max_inc,
                self::BIN_SUM_VALUE=>$sum_value,
                self::BIN_SUM_INC=>$sum_inc,
                self::BIN_COUNT=>$cnt,
            ];
        }

        return $this->close();
	}
}
