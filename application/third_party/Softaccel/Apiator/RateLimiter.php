<?php

namespace Softaccel\Apiator;

class RateLimiter {
    /**
     * @var CI_Controller
     */
    private $ci;
    /**
     * @var CI_Cache
     */
    private $cache;
    private $window = 60; // Time window in seconds
    private $limit = 100; // Maximum requests per window

    /**
     * Constructor for RateLimiter class
     * Initializes the CodeIgniter instance and cache driver
     */
    public function __construct() {
        $this->ci =& get_instance();
        $this->ci->load->driver('cache', array('adapter' => 'file'));
        $this->cache = $this->ci->cache;
    }

    /**
     * Check if request should be rate limited
     * @param string $identifier Client identifier (e.g. IP address)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public function check($identifier) {
        $key = "rate_limit:" . $identifier;
        $current_time = time();
        
        // Get current count and window start time
        $data = $this->cache->get($key);
        if (!$data) {
            $data = ['count' => 0, 'window_start' => $current_time];
        }

        // Reset counter if window has expired
        if ($current_time - $data['window_start'] >= $this->window) {
            $data = ['count' => 0, 'window_start' => $current_time];
        }

        // Increment counter
        $data['count']++;
        
        // Calculate remaining requests and reset time
        $remaining = max(0, $this->limit - $data['count']);
        $reset = $data['window_start'] + $this->window;

        // Store updated data
        $this->cache->save($key, $data, $this->window);

        return [
            'allowed' => $data['count'] <= $this->limit,
            'remaining' => $remaining,
            'reset' => $reset
        ];
    }

    /**
     * Set rate limit configuration
     * @param int $requests Maximum requests per window
     * @param int $window Time window in seconds
     */
    public function setLimit($requests, $window) {
        $this->limit = $requests;
        $this->window = $window;
    }
} 