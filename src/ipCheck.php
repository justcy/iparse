<?php
/**
 * ip2region php seacher client class
 *
 * @author  justcy<justxcy@gmail.com>
 * @date    2015-10-29
 */

namespace iparse;

class ipCheck
{
    /**
     * @var string 普通模式（192.168.5.5）
     */
    private static $_IP_TYPE_SINGLE = 'single';
    
    /**
     * @var string  *号模式（192.168.5.*）
     */
    private static $_IP_TYPE_WILDCARD = 'wildcard';
    
    /**
     * @var string  / 模式（192.168.1.5/192.168.1.100）
     */
    private static $_IP_TYPE_MASK = 'mask';
    
    /**
     * @var string / 模式（192.168.1.1/16）
     */
    private static $_IP_TYPE_CIDR = 'CIDR';
    
    /**
     * @var string - 模式（192.168.1.5-192.168.1.100）
     */
    private static $_IP_TYPE_SECTION = 'section';
    
    /**
     * @var array
     */
    private $_allowed_ips = array();
    
    /**
     * 构造函数
     *
     * @param $allowed_ips
     */
    public function __construct($allowed_ips = [])
    {
        $this->_allowed_ips = $allowed_ips;
    }
    
    /**
     * 过滤IP
     *
     * @param      $ip
     * @param null $allowed_ips
     *
     * @return bool
     */
    public function check($ip, $allowed_ips = [])
    {
        $allowed_ips = $allowed_ips ? $allowed_ips : $this->_allowed_ips;
        if (!Tools::safeIp2long($ip) || !$allowed_ips) {
            return [false, ''];
        }
        foreach ($allowed_ips as $allowed_ip) {
            $type = $this->_judge_ip_type($allowed_ip);
            $sub_rst = call_user_func(array($this, '_sub_checker_' . $type), $allowed_ip, $ip);
            
            if ($sub_rst) {
                return [true, $allowed_ip];
            }
        }
        
        return [false, ''];
    }
    
    /**
     * 检测模式
     *
     * @param $ip
     *
     * @return bool|string
     */
    private function _judge_ip_type($ip)
    {
        if (strpos($ip, '*')) {
            return self:: $_IP_TYPE_WILDCARD;
        }
        
        if (strpos($ip, '/')) {
            $tmp = explode('/', $ip);
            if (strpos($tmp[1], '.')) {
                return self:: $_IP_TYPE_MASK;
            } else {
                return self:: $_IP_TYPE_CIDR;
            }
        }
        
        if (strpos($ip, '-')) {
            return self:: $_IP_TYPE_SECTION;
        }
        
        if (Tools::safeIp2long($ip)) {
            return self:: $_IP_TYPE_SINGLE;
        }
        
        return false;
    }
    
    /**
     * 普通模式
     *
     * @param $allowed_ip
     * @param $ip
     *
     * @return bool
     */
    private function _sub_checker_single($allowed_ip, $ip)
    {
        return (Tools::safeIp2long($allowed_ip) == Tools::safeIp2long($ip));
    }
    
    /**
     * *号模式（192.168.5.*）
     *
     * @param $allowed_ip
     * @param $ip
     *
     * @return bool
     */
    private function _sub_checker_wildcard($allowed_ip, $ip)
    {
        $allowed_ip_arr = explode('.', $allowed_ip);
        $ip_arr = explode('.', $ip);
        for ($i = 0; $i < count($allowed_ip_arr); $i++) {
            if ($allowed_ip_arr[$i] == '*') {
                return true;
            } else {
                if (false == ($allowed_ip_arr[$i] == $ip_arr[$i])) {
                    return false;
                }
            }
        }
    }
    
    /**
     * / 模式（192.168.1.5/192.168.1.100）
     *
     * @param $allowed_ip
     * @param $ip
     *
     * @return bool
     */
    private function _sub_checker_mask($allowed_ip, $ip)
    {
        list($begin, $end) = explode('/', $allowed_ip);
        $begin = Tools::safeIp2long($begin);
        $end = Tools::safeIp2long($end);
        $ip = Tools::safeIp2long($ip);
        
        return ($ip >= $begin && $ip <= $end);
    }
    
    /**
     * - 模式（192.168.1.5-192.168.1.100）
     *
     * @param $allowed_ip
     * @param $ip
     *
     * @return bool
     */
    private function _sub_checker_section($allowed_ip, $ip)
    {
        list($begin, $end) = explode('-', $allowed_ip);
        $begin = Tools::safeIp2long($begin);
        $end = Tools::safeIp2long($end);
        $ip = Tools::safeIp2long($ip);
        
        return ($ip >= $begin && $ip <= $end);
    }
    
    /**
     * / 模式（192.168.1.1/16）
     *
     * @param $CIDR
     * @param $IP
     *
     * @return bool
     */
    private function _sub_checker_CIDR($CIDR, $IP)
    {
        list ($net, $mask) = explode('/', $CIDR);
        
        return (Tools::safeIp2long($IP) & ~((1 << (32 - $mask)) - 1)) == Tools::safeIp2long($net);
    }
}
