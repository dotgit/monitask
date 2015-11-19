<?php

namespace Plugins;

Class Store
{
    // bin parameters
    const BIN_FIRST_TIME    = 0;
    const BIN_FIRST_VALUE   = 1;
    const BIN_LAST_TIME     = 2;
    const BIN_LAST_VALUE    = 3;
    const BIN_MIN_VALUE     = 4;
    const BIN_MAX_VALUE     = 5;
    const BIN_SUM           = 6;
    const BIN_COUNT         = 7;

    public $periods             = [];
    public $metric_period_bins  = [];
    public $error;

    /** creates and initializes datastore
     * @return boolean
     */
	public function create()
	{
		return true;
	}

    /** insert all metrics at once
     * @param int $time         time when metrics were collected
     * @param array $metrics    {"metric-name":"value", ...}
     * @return boolean
     */
	public function insertMetrics($time, $metrics)
	{
		return true;
	}

    /** loads metrics from the datastore and calculates the values per period per bin
     * @return array|boolean    {"metric":{"by day":{"bin1time":["last time", "last", "min", "max", "sum", "cnt"],...},...},...}
     */
	public function load()
	{
		return true;
	}

    public function getMetricStats($metric, $period, $stat)
    {
        if (isset($this->metric_period_bins[$metric][$period]))
        {
            $stats = [
                self::BIN_LAST_TIME=>null,
                self::BIN_LAST_VALUE=>null,
                self::BIN_MIN_VALUE=>null,
                self::BIN_MAX_VALUE=>null,
                self::BIN_SUM=>null,
                self::BIN_COUNT=>null,
            ];
            foreach ($this->metric_period_bins[$metric][$period] as $bin_tm=>$values)
            {
                switch ($stat)
                {
                    case self::STAT_LAST:
                }
            }
        }
    }
}
