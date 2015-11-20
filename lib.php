<?php

Class Lib
{
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
}
