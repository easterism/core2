<?php

namespace Core2\Classes\Edit;

require_once "Control.php";

class Text extends Control
{
    private static $data;


    public function __construct(array $data)
    {
        self::$data = $data;
    }



    public function render()
    {

    }
}