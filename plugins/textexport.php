<?php

namespace Plugins;

use Monitask;

Class TextExport extends Export
{
    public function __construct($params)
    {
    }

    public function export($items, $periods, Store $store)
    {
        foreach ($items as $block=>$bk_items)
        {
            if (! empty($bk_items) and is_array($bk_items))
            {
                echo $block, PHP_EOL;
                foreach ($bk_items as $item_name=>$item)
                {
                    $label = Monitask::arrayExtract($item, self::VAR_LABEL);
                    $vert_label = Monitask::arrayExtract($item, self::VAR_VERT_LABEL);
                    $base = Monitask::arrayExtract($item, self::VAR_BASE);
                    $max_value = Monitask::arrayExtract($item, self::VAR_MAX_VALUE);
                    $crit_value = Monitask::arrayExtract($item, self::VAR_CRIT_VALUE);

                    $label_len = 0;
                    $m_labels = [];
                    $m_types = [];
                    foreach ($item as $metric_name=>$metric)
                    {
                        if (is_array($metric))
                        {
                            $m_labels[$metric_name] = Monitask::arrayExtract($metric, self::METRIC_LABEL, $metric_name);
                            $m_types[$metric_name] = Monitask::arrayExtract($metric, self::METRIC_TYPE, self::TYPE_NORMAL);
                            $label_len = max($label_len, strlen($m_labels[$metric_name]));
                        }
                    }
                    ++$label_len;

                    foreach ($periods as $period=>$format)
                    {
                        echo $label, ' - ', $period, PHP_EOL;
                        foreach ($m_labels as $metric_name=>$m_label)
                        {
                            $stats = $store->getMetricStats($metric_name, $period);
                            switch ($m_types[$metric_name])
                            {
                            case self::TYPE_INCREMENT:
                                list($last, $min, $avg, $max) = [
                                    $stats[Store::BIN_LAST_VALUE] - $stats[Store::BIN_FIRST_VALUE],
                                    $stats[Store::BIN_MIN_VALUE],
                                    $stats[Store::BIN_AVG],
                                    $stats[Store::BIN_MAX_VALUE],
                                ];
                                break;
                            case self::TYPE_NORMAL:
                                list($last, $min, $avg, $max) = [
                                    $stats[Store::BIN_LAST_VALUE],
                                    $stats[Store::BIN_MIN_VALUE],
                                    $stats[Store::BIN_AVG],
                                    $stats[Store::BIN_MAX_VALUE],
                                ];
                                break;
                            }
                            printf("%-{$label_len}s %6s%6s%6s%6s%s", $m_label, $last, $min, $avg, $max, PHP_EOL);
                        }
                    }
                }
            }
        }

        return true;
    }
}
