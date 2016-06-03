<?php

class PaycardConf
{
    public function get($key)
    {
        return CoreLocal::get($key);
    }

    public function set($key, $val)
    {
        return CoreLocal::set($key, $val);
    }
}
