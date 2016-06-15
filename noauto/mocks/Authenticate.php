<?php

namespace COREPOS\pos\lib;

class Authenticate
{
    public static function checkPassword($p)
    {
        return true;
    }
    public static function getPermission($emp)
    {
        return 0;
    }

    public static function checkPermission($emp, $level)
    {
        return true;
    }
}

