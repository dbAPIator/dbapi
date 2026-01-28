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
     * Check if an IP address is within a CIDR range
     * @param string $ip IP address to check
     * @param string $cidr CIDR notation (e.g., "192.168.1.0/24")
     * @return bool
     */
    function ip_in_cidr($ip, $cidr)
    {
        error_log("ip_in_cidr: $ip, $cidr\n");
        // Validate input
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            error_log("invalid IP: $ip, $cidr\n");
            return false;
        }
        
        // Handle single IP addresses (add /32 if no mask specified)
        if (strpos($cidr, '/') === false) {
            error_log("no mask: $ip, $cidr\n");
            $cidr .= '/32';
        }
        
        // Parse CIDR notation
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) {
            error_log("invalid CIDR: $ip, $cidr\n");
            return false;
        }
        
        $subnet = $parts[0];
        $mask = (int)$parts[1];
        
        // Validate subnet and mask
        if (!filter_var($subnet, FILTER_VALIDATE_IP) || $mask < 0 || $mask > 32) {
            error_log("invalid subnet: $ip, $cidr\n");
            return false;
        }
        
        // Convert IPs to long integers (handles both IPv4 and IPv6)
        $ipLong = $this->ip2long_safe($ip);
        $subnetLong = $this->ip2long_safe($subnet);
        error_log("ipLong: $ipLong, $subnetLong\n");
        if ($ipLong === false || $subnetLong === false) {
            error_log("invalid ip: $ip, $cidr\n");
            return false;
        }
        
        // Calculate network mask
        $networkMask = (0xFFFFFFFF << (32 - $mask)) & 0xFFFFFFFF;
        error_log("networkMask: $networkMask\n");
        
        // Check if IP is in the same network
        $res = ($ipLong & $networkMask) === ($subnetLong & $networkMask);
        error_log("IP is in range: $res\n");
        return $res;
    }
    
    /**
     * Safe IP to long conversion that handles 32-bit systems
     * @param string $ip
     * @return int|false
     */
    private function ip2long_safe($ip)
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }
        
        // Convert to unsigned 32-bit integer
        return $long & 0xFFFFFFFF;
    }

    function IP_is_allowed($acls) {
        if(!is_array($acls)) {
            $acls = [$acls];
        }
        error_log("IP_is_allowed 1: ".json_encode($acls)."\n");
        // check if IP is allowed
        $allowed = false;
        foreach ($acls as $rule) {
            error_log("IP_is_allowed 2: ".json_encode($rule)."\n");
            if($this->ip_in_cidr($_SERVER["REMOTE_ADDR"],$rule["ip"])) {
                $allowed = $rule["allow"];
                break;
            }
        }
        error_log("IP_is_allowed 2: $allowed\n");
        return $allowed;
    }

    
}