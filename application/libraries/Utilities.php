<?php

class Utilities
{
    /**
     * @param $ip
     * @param $ranges
     * @return bool
     */
    function find_cidr($ip, $ranges)
    {

        if (!is_array($ranges)) {
            return false;
        }
        foreach ($ranges as $range) {
            if ($this->cidr_match($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $ip
     * @param $range
     * @return bool
     */
    function cidr_match($ip, $range)
    {
        list ($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask; // in case the supplied subnet was not correctly aligned
        return ($ip & $mask) == $subnet;
    }

}