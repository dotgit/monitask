<?php

class Lib
{
    const CHARSET = 'UTF-8';

    /**
     * @param string $string
     * @return string
     */
    public static function sanitizeFilename($string)
    {
        return trim(
            preg_replace(
                '/\\W+/',
                '-',
                mb_strtolower(trim($string), self::CHARSET)
            ),
            '-'
        );
    }

    /**
     * @param mixed $arr
     * @param string $index
     * @param $default
     * @return false|mixed|null
     */
    public static function arrayExtract(&$arr, $index, $default = null)
    {
        if (is_array($arr)) {
            if (array_key_exists($index, $arr)) {
                $res = $arr[$index];
                unset($arr[$index]);
                return $res;
            } else {
                return $default;
            }
        } else {
            return false;
        }
    }

    /**
     * return human-readable amount rounded to 3 meaningful numbers with appropriate ISO suffix
     * (K=kilo, M=mega, G=giga, T=tera, P=peta)
     * @param int $amount amount to convert
     * @return string value like '150', '8.37K', '15M', '374G'
     * @assert(20) == '20'
     * @assert(999) == '999'
     * @assert(1000) == '0.98K'
     * @assert(1024) == '1K'
     * @assert(2000) == '1.95K'
     * @assert(2048) == '2K'
     * @assert(2100) == '2.05K'
     * @assert(2150) == '2.1K'
     * @assert(2157) == '2.11K'
     * @assert(21573) == '21.1K'
     * @assert(-21573) == '-21.1K'
     */
    public static function shortFloat($amount)
    {
        if (!isset($amount)) {
            return null;
        }

        $amount_abs = abs($amount);
        if ($amount_abs >= 1000000000000000) {
            return ($amount_abs >= 100000000000000000
                    ? round($amount / 1125899906842624)
                    : ($amount_abs >= 10000000000000000
                        ? round($amount / 1125899906842624, 1)
                        : round($amount / 1125899906842624, 2)
                    )
                ) . 'P';
        } elseif ($amount_abs >= 1000000000000) {
            return ($amount_abs >= 100000000000000
                    ? round($amount / 1099511627776)
                    : ($amount_abs >= 10000000000000
                        ? round($amount / 1099511627776, 1)
                        : round($amount / 1099511627776, 2)
                    )
                ) . 'T';
        } elseif ($amount_abs >= 1000000000) {
            return ($amount_abs >= 100000000000
                    ? round($amount / 1073741824)
                    : ($amount_abs >= 10000000000
                        ? round($amount / 1073741824, 1)
                        : round($amount / 1073741824, 2)
                    )
                ) . 'G';
        } elseif ($amount_abs >= 1000000) {
            return ($amount_abs >= 100000000
                    ? round($amount / 1048576)
                    : ($amount_abs >= 10000000
                        ? round($amount / 1048576, 1)
                        : round($amount / 1048576, 2)
                    )
                ) . 'M';
        } elseif ($amount_abs >= 1000) {
            return ($amount_abs >= 100000
                    ? round($amount / 1024)
                    : ($amount_abs >= 10000
                        ? round($amount / 1024, 1)
                        : round($amount / 1024, 2)
                    )
                ) . 'K';
        } elseif ($amount_abs >= 1) {
            return $amount_abs >= 100
                ? round($amount)
                : ($amount_abs >= 10
                    ? round($amount, 1)
                    : round($amount, 2)
                );
        } elseif ($amount_abs >= 0.001) {
            return strlen(round($amount_abs, 3)) < 5
                ? round($amount, 3)
                : str_replace('0.', '.', round($amount, 3));
        } elseif ($amount_abs > 0) {
            return $amount > 0
                ? '+0'
                : '-0';
        } else {
            return 0;
        }
    }

    /**
     * return human-readable amount rounded to 3 meaningful numbers with appropriate ISO suffix
     * (K=kilo, M=mega, G=giga, T=tera, P=peta)
     * @param int $amount amount to convert
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
        if (!isset($amount)) {
            return null;
        }

        $amount_abs = abs($amount);
        if ($amount_abs >= 1000000000000000) {
            return ($amount_abs >= 100000000000000000
                    ? round($amount / 1000000000000000)
                    : ($amount_abs >= 10000000000000000
                        ? round($amount / 1000000000000000, 1)
                        : round($amount / 1000000000000000, 2)
                    )
                ) . 'P';
        } elseif ($amount_abs >= 1000000000000) {
            return ($amount_abs >= 100000000000000
                    ? round($amount / 1000000000000)
                    : ($amount_abs >= 10000000000000
                        ? round($amount / 1000000000000, 1)
                        : round($amount / 1000000000000, 2)
                    )
                ) . 'T';
        } elseif ($amount_abs >= 1000000000) {
            return ($amount_abs >= 100000000000
                    ? round($amount / 1000000000)
                    : ($amount_abs >= 10000000000
                        ? round($amount / 1000000000, 1)
                        : round($amount / 1000000000, 2)
                    )
                ) . 'G';
        } elseif ($amount_abs >= 1000000) {
            return ($amount_abs >= 100000000
                    ? round($amount / 1000000)
                    : ($amount_abs >= 10000000
                        ? round($amount / 1000000, 1)
                        : round($amount / 1000000, 2)
                    )
                ) . 'M';
        } elseif ($amount_abs >= 1000) {
            return ($amount_abs >= 100000
                    ? round($amount / 1000)
                    : ($amount_abs >= 10000
                        ? round($amount / 1000, 1)
                        : round($amount / 1000, 2)
                    )
                ) . 'K';
        } elseif ($amount_abs >= 1) {
            return $amount_abs >= 100
                ? round($amount)
                : ($amount_abs >= 10
                    ? round($amount, 1)
                    : round($amount, 2)
                );
        } elseif ($amount_abs >= 0.001) {
            return strlen(round($amount_abs, 3)) < 5
                ? round($amount, 3)
                : str_replace('0.', '.', round($amount, 3));
        } elseif ($amount_abs > 0) {
            return $amount > 0
                ? '+0'
                : '-0';
        } else {
            return 0;
        }
    }
}
