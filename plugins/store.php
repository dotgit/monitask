<?php

namespace Plugins;

Class Store
{
	public $error;

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
