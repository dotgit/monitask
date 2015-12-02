<?php

namespace Plugins;

Class CsvStore extends Store
{
    // datastore section of ini file
    const VAR_FILENAME  = 'filename';

    // datastore type
    const TYPE_CSV  = 'csv';

    // file open modes
    const MODE_READ     = 'r';
    const MODE_WRITE    = 'w';

	public $filename;
	public $handle;

	public function __construct(array $params, array $periods)
	{
        // set datastore filename
        if (! isset($params[self::VAR_FILENAME]))
        {
            $this->error = __METHOD__.': '.self::VAR_FILENAME.' parameter not set';
            return;
        }
        $this->filename = $params[self::VAR_FILENAME];

        parent::__construct($params, $periods);
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

    public function load()
	{
        if (empty($this->handle) and ! $this->open(self::MODE_READ))
            return false;

        // read records from datafile and store in corresponding bins
        while ($line = fgetcsv($this->handle))
        {
            if (count($line) < 20)
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

    public function flush()
	{
        if (empty($this->handle) and ! $this->open(self::MODE_WRITE))
            return false;

        if (empty($this->metric_period_bins))
        {
            $this->error = __METHOD__.': datastore is empty';
            return false;
        }

        // define minimal dates per periods
        $period_min = [];
        foreach ($this->periods as $period_name=>$format)
            $period_min[$period_name] = strtotime($format, $_SERVER['REQUEST_TIME']);

        ksort($this->metric_period_bins);

        $errors = [];
        foreach ($this->metric_period_bins as $metric=>$periods)
        {
            foreach ($periods as $period_name=>$bins)
            {
                foreach ($bins as $bin_tm=>$bin)
                {
                    if ($bin_tm < $period_min[$period_name])
                        continue;
                    if (! fputcsv($this->handle, array_merge([$metric, $period_name, $bin_tm], $bin)))
                        $errors[] = sprintf(
                            "%s: error updating metric '%s', period '%s', bin '%s' in %s datafile",
                            __METHOD__,
                            $metric,
                            $period_name,
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

        return $this->close();
	}
}
