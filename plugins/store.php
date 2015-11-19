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
    const BIN_AVG           = 'avg';

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

    public function getMetricStats($metric, $period)
    {
        $stats = [
            self::BIN_FIRST_TIME=>null,
            self::BIN_FIRST_VALUE=>null,
            self::BIN_LAST_TIME=>null,
            self::BIN_LAST_VALUE=>null,
            self::BIN_MIN_VALUE=>null,
            self::BIN_MAX_VALUE=>null,
            self::BIN_SUM=>null,
            self::BIN_COUNT=>null,
            self::BIN_AVG=>null,
        ];

        if (isset($this->metric_period_bins[$metric][$period]))
        {
            $metric_period = $this->metric_period_bins[$metric][$period];
            if ($bins = array_keys($metric_period))
            {
                // set first and last values
                $first_bin = $bins[0];
                $last_bin = $bins[count($bins) - 1];
                $stats[self::BIN_FIRST_TIME] = $metric_period[$first_bin][self::BIN_FIRST_TIME];
                $stats[self::BIN_FIRST_VALUE] = $metric_period[$first_bin][self::BIN_FIRST_VALUE];
                $stats[self::BIN_LAST_TIME] = $metric_period[$last_bin][self::BIN_LAST_TIME];
                $stats[self::BIN_LAST_VALUE] = $metric_period[$last_bin][self::BIN_LAST_VALUE];

                // compute min, max and avg values
                $avgs = [];
                $cnt = 0;
                foreach ($bins as $bin)
                {
                    // set min value
                    if (! isset($stats[self::BIN_MIN_VALUE])
                        or $metric_period[$bin][self::BIN_MIN_VALUE] < $stats[self::BIN_MIN_VALUE]
                    )
                        $stats[self::BIN_MIN_VALUE] = $metric_period[$bin][self::BIN_MIN_VALUE];
                    // set max value
                    if (! isset($stats[self::BIN_MAX_VALUE])
                        or $stats[self::BIN_MAX_VALUE] < $metric_period[$bin][self::BIN_MAX_VALUE]
                    )
                        $stats[self::BIN_MAX_VALUE] = $metric_period[$bin][self::BIN_MAX_VALUE];
                    // increment count value
                    $cnt += $metric_period[$bin][self::BIN_COUNT];
                    // collect avg for bin
                    $avgs[] = $metric_period[$bin][self::BIN_SUM]/$metric_period[$bin][self::BIN_COUNT];
                }
                if ($cnt)
                {
                    $stats[self::BIN_COUNT] = $cnt;
                    $stats[self::BIN_AVG] = array_sum($avgs) / count($avgs);
                }
            }
        }

        return $stats;
    }
}
