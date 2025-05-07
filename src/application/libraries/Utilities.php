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
            if ($this->ip_in_cidr($ip, $range)) {
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
    function ip_in_cidr($ip, $cidr)
    {
        if (strpos($cidr, '/') === false) $cidr .= '/32';
        [$subnet, $mask] = explode('/', $cidr);
        return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet);
    }

}