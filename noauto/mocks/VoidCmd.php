<?php

namespace COREPOS\pos\parser\parse;

class VoidCmd
{
    public function __construct($session)
    {
    }

    public function voidid($id, $json)
    {
        return array();
    }
}
