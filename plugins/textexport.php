<?php

namespace Plugins;

use Lib;

Class TextExport extends Export
{
    public function export($items, $periods, Store $store)
    {
        foreach ($items as $block=>$bk_items)
        {
            if (! empty($bk_items) and is_array($bk_items))
            {
                echo $block, PHP_EOL, PHP_EOL;
                foreach ($bk_items as $item_name=>$item)
                {
                    $label = Lib::arrayExtract($item, self::VAR_LABEL);
                    $vert_label = Lib::arrayExtract($item, self::VAR_VERT_LABEL);
                    $base = Lib::arrayExtract($item, self::VAR_BASE);
                    $max_value = Lib::arrayExtract($item, self::VAR_MAX_VALUE);
                    $crit_value = Lib::arrayExtract($item, self::VAR_CRIT_VALUE);
                    $lower_limit = Lib::arrayExtract($item, self::VAR_LOWER_LIMIT);

                    $label_len = 0;
                    $m_labels = [];
                    $m_types = [];
                    foreach ($item as $metric_name=>$metric)
                    {
                        if (is_array($metric))
                        {
                            $m_labels[$metric_name] = Lib::arrayExtract($metric, self::METRIC_LABEL, $metric_name);
                            $m_types[$metric_name] = Lib::arrayExtract($metric, self::METRIC_TYPE, Store::TYPE_VALUE);
                            $label_len = max($label_len, mb_strlen($m_labels[$metric_name], Lib::CHARSET));
                        }
                    }
                    ++$label_len;

                    foreach ($periods as $period=>$format)
                    {
                        echo $label, ' - ', $period, PHP_EOL;
                        printf(
                            "%-{$label_len}s %6s %6s %6s %6s %6s%s",
                            '',
                            'first',
                            'min',
                            'avg',
                            'max',
                            'last',
                            PHP_EOL
                        );
                        foreach ($m_labels as $metric_name=>$m_label)
                        {
                            $stats = $store->getMetricStats($metric_name, $period, $m_types[$metric_name]);
                            printf(
                                "%-{$label_len}s %6s %6s %6s %6s %6s%s",
                                $m_label,
                                Lib::humanFloat($stats[Store::STAT_FIRST]),
                                Lib::humanFloat($stats[Store::STAT_MIN]),
                                Lib::humanFloat($stats[Store::STAT_AVG]),
                                Lib::humanFloat($stats[Store::STAT_MAX]),
                                Lib::humanFloat($stats[Store::STAT_LAST]),
                                PHP_EOL
                            );
                        }
                    }
                    echo PHP_EOL;
                }
            }
        }

        return true;
    }
}
