<?php

namespace COREPOS\pos\parser\parse;

class Void
{
    public function __construct($session)
    {
    }

    public function voidid($id, $json)
    {
        return array();
    }
}
