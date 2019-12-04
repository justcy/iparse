<?php
/**
 * ip2region php seacher client class
 *
 * @author  justcy<justxcy@gmail.com>
 * @date    2015-10-29
 */

namespace iparse;

class Tools
{
    /**
     * @param $ip
     *
     * @return false|int|string
     */
    public static function safeIp2long($ip)
    {
        $ip = ip2long($ip);
        
        // convert signed int to unsigned int if on 32 bit operating system
        if ($ip < 0 && PHP_INT_SIZE == 4) {
            $ip = sprintf("%u", $ip);
        }
        
        return $ip;
    }
    
    /**
     * read a long from a byte buffer
     *
     * @param $b
     * @param $offset
     *
     * @return int|string
     */
    public static function getLong($b, $offset)
    {
        $val = (
            (ord($b[$offset++])) |
            (ord($b[$offset++]) << 8) |
            (ord($b[$offset++]) << 16) |
            (ord($b[$offset]) << 24)
        );
        
        // convert signed int to unsigned int if on 32 bit operating system
        if ($val < 0 && PHP_INT_SIZE == 4) {
            $val = sprintf("%u", $val);
        }
        
        return $val;
    }
}
