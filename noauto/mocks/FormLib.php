<?php    
namespace COREPOS\pos\lib;

class FormLib
{
    private static $mock = array();
    public static function clear()
    {
        self::$mock = array();
    }

    public static function get($k, $default='')
    {
        return isset(self::$mock[$k]) ? self::$mock[$k] : $default;
    }

    public static function set($k, $v)
    {
        self::$mock[$k] = $v;
    }

    public static function tokenField()
    {
        return '';
    }

    public static function validateToken()
    {
        return true;
    }
}

