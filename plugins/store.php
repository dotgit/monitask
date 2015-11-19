<?php

namespace Plugins;

Class Store
{
    // bin parameters
    const BIN_LAST_TIME     = 0;
    const BIN_LAST_VALUE    = 1;
    const BIN_MIN_VALUE     = 2;
    const BIN_MAX_VALUE     = 3;
    const BIN_SUM           = 4;
    const BIN_COUNT         = 5;

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
}
