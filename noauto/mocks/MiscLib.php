<?php

namespace COREPOS\pos\lib;

class MiscLib
{
    public static function baseURL()
    {
        return '';
    }

    public static function win32()
    {
        return false;
    }

    static public function pingport($host, $dbms)
    {
        return 1;
    }
}

