<?php

namespace CR;

class Exception extends \Exception
{
    public string $name;
    public string $msg;

    public function __construct(string $name, string $msg)
    {
        parent::__construct($msg);
        $this->name = $name;
        $this->msg = $msg;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function msg(): string
    {
        return $this->msg;
    }
}
