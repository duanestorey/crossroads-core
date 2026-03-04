<?php

namespace CR;

class SassException extends Exception
{
    public function __construct(string $msg)
    {
        parent::__construct('SASS', $msg);
    }
}
