<?php

namespace CR;

class Config
{
    protected $config = null;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function get($key, $default = false)
    {
        if ($this->config && isset($this->config[ $key ])) {
            return $this->config[ $key ];
        } else {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            LOG(sprintf('Setting not found [%s] in [%s:%d]', $key, $trace['file'], $trace['line']), 1, Log::WARNING);
            return $default;
        }
    }

    public function set($key, $value)
    {
        $this->config[ $key ] = $value;
    }
}
