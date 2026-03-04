<?php

namespace CR;

abstract class LogListener
{
    abstract public function setLevel(int $level): void;
    abstract public function log(string $message, int $tabs, int $level): void;
}
