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

    const STAT_FIRST    = 'f';
    const STAT_LAST     = 'l';
    const STAT_MIN      = 'mi';
    const STAT_MAX      = 'ma';
    const STAT_AVG      = 'a';

    const TYPE_VALUE    = 'value';
    const TYPE_RATE     = 'rate';
    const TYPE_INC      = 'increment';

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

    public function getMetricData($metric, $period, $type=self::TYPE_VALUE)
    {
        $data = [];

        if (isset($this->metric_period_bins[$metric][$period]))
        {
            switch ($type)
            {
            case self::TYPE_VALUE:
                foreach ($this->metric_period_bins[$metric][$period] as $bin_tm=>$bin)
                    $data[$bin_tm] = $bin[self::BIN_SUM_VALUE]/($bin[self::BIN_COUNT]);
                break;

            case self::TYPE_RATE:
                $prev_bin = null;
                foreach ($this->metric_period_bins[$metric][$period] as $bin_tm=>$bin)
                {
                    $data[$bin_tm] = $bin[self::BIN_COUNT] > 1
                        ? $bin[self::BIN_SUM_INC]/($bin[self::BIN_LAST_TIME] - $bin[self::BIN_FIRST_TIME])
                        : $bin[self::BIN_SUM_INC]/($bin[self::BIN_FIRST_TIME] - $prev_bin[self::BIN_LAST_TIME]);
                    $prev_bin = $bin;
                }
                break;

            case self::TYPE_INC:
                foreach ($this->metric_period_bins[$metric][$period] as $bin_tm=>$bin)
                    $data[$bin_tm] = $bin[self::BIN_SUM_INC]/($bin[self::BIN_COUNT]);
                break;
            }
        }

        return $data;
    }

    public function getMetricStats($metric, $period, $type=self::TYPE_VALUE)
    {
        $stats = [
            self::STAT_FIRST=>null,
            self::STAT_LAST=>null,
            self::STAT_MIN=>null,
            self::STAT_MAX=>null,
            self::STAT_AVG=>null,
        ];

        if (isset($this->metric_period_bins[$metric][$period]))
        {
            $metric_period = $this->metric_period_bins[$metric][$period];
            if ($bins = array_keys($metric_period))
            {
                // set first and last values
                $first_bin = $bins[0];
                $last_bin = $bins[count($bins) - 1];
                switch ($type)
                {
                case self::TYPE_VALUE:
                    $stats[self::STAT_FIRST] = $metric_period[$first_bin][self::BIN_FIRST_VALUE];
                    $stats[self::STAT_LAST] = $metric_period[$last_bin][self::BIN_LAST_VALUE];
                    break;

                case self::TYPE_RATE:
                    $stats[self::STAT_FIRST] = null;
                    $stats[self::STAT_LAST] = null;
                    break;

                case self::TYPE_INC:
                    $stats[self::STAT_FIRST] = $metric_period[$first_bin][self::BIN_FIRST_INC];
                    $stats[self::STAT_LAST] = $metric_period[$last_bin][self::BIN_LAST_INC];
                    break;
                }

                // compute min, max, count and avg values
                $sum = 0;
                $cnt = 0;
                $prev_bin = null;
                foreach ($bins as $bin)
                {
                    $this_bin = $metric_period[$bin];
                    switch ($type)
                    {
                    case self::TYPE_VALUE:
                        // set min value
                        if (! isset($stats[self::STAT_MIN])
                            or $this_bin[self::BIN_MIN_VALUE] < $stats[self::STAT_MIN]
                        )
                            $stats[self::STAT_MIN] = $this_bin[self::BIN_MIN_VALUE];
                        // set max value
                        if (! isset($stats[self::STAT_MAX])
                            or $stats[self::STAT_MAX] < $this_bin[self::BIN_MAX_VALUE]
                        )
                            $stats[self::STAT_MAX] = $this_bin[self::BIN_MAX_VALUE];
                        // collect sum
                        $sum += $this_bin[self::BIN_SUM_VALUE];
                        break;

                    case self::TYPE_RATE:
                        $rate = $this_bin[self::BIN_COUNT] > 1
                            ? $this_bin[self::BIN_SUM_INC]/($this_bin[self::BIN_LAST_TIME] - $this_bin[self::BIN_FIRST_TIME])
                            : (isset($prev_bin)
                                ? $this_bin[self::BIN_SUM_INC]/($this_bin[self::BIN_FIRST_TIME] - $prev_bin[self::BIN_LAST_TIME])
                                : null
                            );
                        // set min value
                        if (! isset($stats[self::STAT_MIN])
                            or $rate < $stats[self::STAT_MIN]
                        )
                            $stats[self::STAT_MIN] = $rate;
                        // set max value
                        if (! isset($stats[self::STAT_MAX])
                            or $stats[self::STAT_MAX] < $rate
                        )
                            $stats[self::STAT_MAX] = $rate;
                        // collect sum
                        $sum += $rate;
                        break;

                    case self::TYPE_INC:
                        // set min value
                        if (! isset($stats[self::STAT_MIN])
                            or $this_bin[self::BIN_MIN_INC] < $stats[self::STAT_MIN]
                        )
                            $stats[self::STAT_MIN] = $this_bin[self::BIN_MIN_INC];
                        // set max value
                        if (! isset($stats[self::STAT_MAX])
                            or $stats[self::STAT_MAX] < $this_bin[self::BIN_MAX_INC]
                        )
                            $stats[self::STAT_MAX] = $this_bin[self::BIN_MAX_INC];
                        // collect sum
                        $sum += $this_bin[self::BIN_SUM_INC];
                        break;
                    }
                    // increment count value
                    $cnt += $this_bin[self::BIN_COUNT];
                    $prev_bin = $bin;
                }
                // compute average, $cnt is > 0
                $stats[self::STAT_AVG] = $sum/$cnt;
            }
        }

        return $stats;
    }
}
