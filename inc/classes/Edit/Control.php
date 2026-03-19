<?php

namespace Core2\Classes\Edit;

class Control
{
    protected $readonly = false;

    protected function setReadonly()
    {
        $this->readonly = true;
    }
}