<?php
namespace COREPOS\pos\lib;
use \SQLManager;

class Database
{
    public static function setglobalvalue($k, $v)
    {
    }

    public static function getsubtotals(){}

    public static function pDataConnect()
    {
        return new SQLManager('', '', '', '', '');
    }

    public static function tDataConnect()
    {
        return new SQLManager('', '', '', '', '');
    }

    public static function mDataConnect()
    {
        return new SQLManager('', '', '', '', '');
    }
}


