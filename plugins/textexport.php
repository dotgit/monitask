<?php

namespace Plugins;

use Lib;

Class TextExport extends Export
{
    // export type
    const TYPE_TEXT = 'text';

    public function export(array $items, array $periods, Store $store)
    {
        $info = rtrim(`uname -n`).'   '.date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']);

        echo str_repeat('+', mb_strlen($info, Lib::CHARSET)),PHP_EOL,
            $info,PHP_EOL,
            str_repeat('+', mb_strlen($info, Lib::CHARSET)),PHP_EOL,
            PHP_EOL;

        foreach ($items as $block=>$bk_items)
        {
            if (! empty($bk_items) and is_array($bk_items))
            {
                echo $block,PHP_EOL,
                    str_repeat('=', mb_strlen($block, Lib::CHARSET)), PHP_EOL;

                foreach ($bk_items as $item_name=>$item)
                {
                    $title = Lib::arrayExtract($item, self::VAR_TITLE, $item_name);
                    $options = Lib::arrayExtract($item, self::VAR_OPTIONS);

                    $title_len = 0;
                    $m_titles = [];
                    $m_types = [];
                    foreach ($item as $metric_name=>$metric)
                    {
                        if (is_array($metric))
                        {
                            $m_titles[$metric_name] = Lib::arrayExtract($metric, self::METRIC_TITLE, $metric_name);
                            $m_types[$metric_name] = Lib::arrayExtract($metric, self::METRIC_TYPE, Store::TYPE_VALUE);
                            $m_evals[$metric_name] = Lib::arrayExtract($metric, self::METRIC_EVAL);
                            $title_len = max($title_len, mb_strlen($m_titles[$metric_name], Lib::CHARSET));
                        }
                    }
                    ++$title_len;

                    echo str_repeat('-', $title_len + 5*7),PHP_EOL;

                    foreach ($periods as $period=>$format)
                    {
                        echo $title, ' - ', $period, PHP_EOL;
                        printf(
                            "%-{$title_len}s %6s %6s %6s %6s %6s%s",
                            '',
                            'first',
                            'min',
                            'avg',
                            'max',
                            'last',
                            PHP_EOL
                        );
                        foreach ($m_titles as $metric_name=>$m_title)
                        {
                            $stats = $store->getMetricStats($metric_name, $period, $m_types[$metric_name]);
                            printf(
                                "%-{$title_len}s %6s %6s %6s %6s %6s%s",
                                $m_title,
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
