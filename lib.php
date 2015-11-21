<?php

Class Lib
{
    const CHARSET = 'UTF-8';

    public static function arrayExtract(&$arr, $index, $default=null)
    {
        if (is_array($arr))
        {
            if (array_key_exists($index, $arr))
            {
                $res = $arr[$index];
                unset($arr[$index]);
                return $res;
            }
            else
                return $default;
        }
        else
            return false;
    }

    /** returns human-readable amount rounded to 3 meaningful numbers with appropriate ISO suffix
     * (K=kilo, M=mega, G=giga, T=tera, P=peta)
     * @param int $amount   amount to convert
     * @return string value like '150', '8.37K', '15M', '374G'
     * @assert(20) == '20'
     * @assert(999) == '999'
     * @assert(1000) == '1K'
     * @assert(2000) == '2K'
     * @assert(2100) == '2.1K'
     * @assert(2150) == '2.15K'
     * @assert(2157) == '2.16K'
     * @assert(21573) == '21.6K'
     * @assert(-21573) == '-21.6K'
     * @assert(2000, 'K') == '2'
     * @assert(2157, 'K') == '2.16'
     */
    public static function humanFloat($amount)
    {
        if (! isset($amount))
            return null;

        $amount_abs = \abs($amount);
        if ($amount_abs >= 1000000000000000)
            return ($amount_abs >= 100000000000000000
                ? \round($amount/ 1000000000000000)
                : ($amount_abs >= 10000000000000000
                    ? \round($amount/1000000000000000, 1)
                    : \round($amount/1000000000000000, 2)
                )
            ).'P';
        elseif ($amount_abs >= 1000000000000)
            return ($amount_abs >= 100000000000000
                ? \round($amount/1000000000000)
                : ($amount_abs >= 10000000000000
                    ? \round($amount/1000000000000, 1)
                    : \round($amount/1000000000000, 2)
                )
            ).'T';
        elseif ($amount_abs >= 1000000000)
            return ($amount_abs >= 100000000000
                ? \round($amount/1000000000)
                : ($amount_abs >= 10000000000
                    ? \round($amount/1000000000, 1)
                    : \round($amount/1000000000, 2)
                )
            ).'G';
        elseif ($amount_abs >= 1000000)
            return ($amount_abs >= 100000000
                ? \round($amount/1000000)
                : ($amount_abs >= 10000000
                    ? \round($amount/1000000, 1)
                    : \round($amount/1000000, 2)
                )
            ).'M';
        elseif ($amount_abs >= 1000)
            return ($amount_abs >= 100000
                ? \round($amount/1000)
                : ($amount_abs >= 10000
                    ? \round($amount/1000, 1)
                    : \round($amount/1000, 2)
                )
            ).'K';
        else
            return $amount_abs >= 100
                ? \round($amount)
                : ($amount_abs >= 10
                    ? \round($amount, 1)
                    : \round($amount, 2)
                );
    }

}
