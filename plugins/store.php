<?php

namespace Plugins;

Class Store
{
    // how many bins to use per graph
    const LOAD_BINS_CNT = 10;

    // bin parameters
    const BIN_VALUE_MIN = 0;
    const BIN_VALUE_MAX = 1;
    const BIN_VALUE_SUM = 2;
    const BIN_VALUE_CNT = 3;

    public $error;
    public $start_time      = [];   // {"-2 days":"start-time",...}
    public $period_times    = [];   // {"-2 days":["bin1time","bin2time",...],...}

    /** creates and initializes datastore
     * @return boolean
     */
	public function create()
	{
		return true;
	}

    /** insert one metric into datastore
     * @param int $time         metric timestamp
     * @param string $metric    metric name
     * @param string $value     metric value
     * @return boolean
     */
	public function insertOne($time, $metric, $value)
	{
		return true;
	}

    /** insert many metrics at once
     * @param array $metrics {"metric-name":["timestamp", "value"], ...}
     * @return boolean
     */
	public function insertMany($metrics)
	{
		return true;
	}

    /** preloads $start_time and $period_times variables
     *
     * @param array $periods {"period-name":"strtotime-pattern"}
     * @return boolean
     */
	public function preload($periods)
	{
        $now = $_SERVER['REQUEST_TIME'];
        $errors = [];
        foreach ($periods as $name=>$pattern)
        {
            if ($start_tm = strtotime($pattern, $now))
            {
                $this->start_time[$name] = $start_tm;
                $delta = (int)(($now - $start_tm) / self::LOAD_BINS_CNT);
                $this->period_times[$name] = [];
                for ($i = $start_tm + $delta; $i < $now; $i += $delta)
                    $this->period_times[$name][] = $i;
                $this->period_times[$name][] = $now;
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
        if (empty($this->start_time))
        {
            $this->error = __METHOD__.': periods are not defined';
            return false;
        }

        return true;
	}

    /** loads metrics from the datastore and calculates the values for periods
     * @param array $items
     * @param array $periods
     * @return boolean
     */
	public function load($items, $periods)
	{
		return true;
	}
}
