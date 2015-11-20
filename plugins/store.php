<?php

namespace Plugins;

Class Store
{
    // bin parameters
    const BIN_FIRST_TIME    = 0;
    const BIN_FIRST_VALUE   = 1;
    const BIN_FIRST_INC     = 2;
    const BIN_LAST_TIME     = 3;
    const BIN_LAST_VALUE    = 4;
    const BIN_LAST_INC      = 5;
    const BIN_MIN_VALUE     = 6;
    const BIN_MIN_INC       = 7;
    const BIN_MAX_VALUE     = 8;
    const BIN_MAX_INC       = 9;
    const BIN_SUM_VALUE     = 10;
    const BIN_SUM_INC       = 11;
    const BIN_COUNT         = 12;
    const BIN_AVG_VALUE     = 'a';
    const BIN_AVG_INC       = 'ai';

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
            self::BIN_FIRST_INC=>null,
            self::BIN_LAST_TIME=>null,
            self::BIN_LAST_VALUE=>null,
            self::BIN_LAST_INC=>null,
            self::BIN_MIN_VALUE=>null,
            self::BIN_MIN_INC=>null,
            self::BIN_MAX_VALUE=>null,
            self::BIN_MAX_INC=>null,
            self::BIN_SUM_VALUE=>null,
            self::BIN_SUM_INC=>null,
            self::BIN_COUNT=>null,
            self::BIN_AVG_VALUE=>null,
            self::BIN_AVG_INC=>null,
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
                $stats[self::BIN_FIRST_INC] = $metric_period[$first_bin][self::BIN_FIRST_INC];
                $stats[self::BIN_LAST_TIME] = $metric_period[$last_bin][self::BIN_LAST_TIME];
                $stats[self::BIN_LAST_VALUE] = $metric_period[$last_bin][self::BIN_LAST_VALUE];
                $stats[self::BIN_LAST_INC] = $metric_period[$last_bin][self::BIN_LAST_INC];

                // compute min, max, count and avg values
                $avgs = [];
                $cnt = 0;
                foreach ($bins as $bin)
                {
                    $this_bin = $metric_period[$bin];
                    // set min value
                    if (! isset($stats[self::BIN_MIN_VALUE])
                        or $this_bin[self::BIN_MIN_VALUE] < $stats[self::BIN_MIN_VALUE]
                    )
                        $stats[self::BIN_MIN_VALUE] = $this_bin[self::BIN_MIN_VALUE];
                    if (! isset($stats[self::BIN_MIN_INC])
                        or $this_bin[self::BIN_MIN_INC] < $stats[self::BIN_MIN_INC]
                    )
                        $stats[self::BIN_MIN_INC] = $this_bin[self::BIN_MIN_INC];
                    // set max value
                    if (! isset($stats[self::BIN_MAX_VALUE])
                        or $stats[self::BIN_MAX_VALUE] < $this_bin[self::BIN_MAX_VALUE]
                    )
                        $stats[self::BIN_MAX_VALUE] = $this_bin[self::BIN_MAX_VALUE];
                    if (! isset($stats[self::BIN_MAX_INC])
                        or $stats[self::BIN_MAX_INC] < $this_bin[self::BIN_MAX_INC]
                    )
                        $stats[self::BIN_MAX_INC] = $this_bin[self::BIN_MAX_INC];
                    // increment count value
                    $cnt += $this_bin[self::BIN_COUNT];
                    // collect avg for bin
                    $avgs[] = $this_bin[self::BIN_SUM_VALUE]/$this_bin[self::BIN_COUNT];
                    $avgs_i[] = $this_bin[self::BIN_SUM_INC]/$this_bin[self::BIN_COUNT];
                }
                // compute averages
                $stats[self::BIN_COUNT] = $cnt;
                $stats[self::BIN_AVG_VALUE] = array_sum($avgs) / count($avgs);
                $stats[self::BIN_AVG_INC] = array_sum($avgs) / count($avgs);
            }
        }

        return $stats;
    }
}
