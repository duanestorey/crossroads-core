<?php

namespace CR;

class Config
{
    private const NO_DEFAULT = "\0__NO_DEFAULT__\0";

    protected $config = null;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function get($key, $default = self::NO_DEFAULT)
    {
        if ($this->config && isset($this->config[ $key ])) {
            return $this->config[ $key ];
        } else {
            if ($default === self::NO_DEFAULT) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
                LOG(sprintf('Setting not found [%s] in [%s:%d]', $key, $trace['file'], $trace['line']), 1, Log::WARNING);
                return false;
            }

            return $default;
        }
    }

    public function set($key, $value)
    {
        $this->config[ $key ] = $value;
    }
}
