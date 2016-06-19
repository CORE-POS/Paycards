<?php

namespace COREPOS\pos\lib\PrintHandlers;

class PrintHandler
{
    public static function factory($class)
    {
        return new self();
    }
    public function writeLine($msg){}
}

