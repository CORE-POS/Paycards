<?php

namespace COREPOS\pos\lib\gui;

class BasicCorePage 
{
    protected $page_url = '';
    protected $body_class = '';
    public function __construct(){}
    public function change_page($url){}
    public function addOnloadCommand($str){}
    public function input_header($action='')
    {
        return '';
    }
    public function hide_input($hide){}
    public function head_content(){}
    protected function scale_box()
    {
        return '';
    }
    protected function scanner_scale_polling()
    {
        return '';
    }
}

