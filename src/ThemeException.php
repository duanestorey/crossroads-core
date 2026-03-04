<?php

namespace CR;

class ThemeException extends Exception
{
    public function __construct(string $msg)
    {
        parent::__construct('THEME', $msg);
    }
}
