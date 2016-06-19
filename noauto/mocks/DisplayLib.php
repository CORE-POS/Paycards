<?php

namespace COREPOS\pos\lib;

class DisplayLib
{
    public static function printfooter()
    {
        return '';
    }

    public static function boxMsg($msg, $header, $noBeep, $buttons=array())
    {
        return $msg . $header;
    }

    public static function xboxMsg($msg, $buttons=array())
    {
        return $msg;
    }

    public static function standardClearButton()
    {
        return array();
    }
    
    public static function lastpage()
    {
        return '';
    }
}

