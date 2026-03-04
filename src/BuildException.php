<?php

namespace CR;

class BuildException extends Exception
{
    public function __construct(string $msg)
    {
        parent::__construct('BUILD', $msg);
    }
}
