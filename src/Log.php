<?php

namespace CR;

class Log
{
    public const int DEBUG = 0;
    public const int INFO = 1;
    public const int WARNING = 2;
    public const int ERROR = 3;
    public const int FATAL = 10;

    private static ?Log $instance = null;

    /** @var LogListener[] */
    public array $listeners = [];

    protected function __construct()
    {
    }

    public function log(string $message, int $tabs = 0, int $level = Log::INFO): void
    {
        foreach ($this->listeners as $listener) {
            $listener->log($message, $tabs, $level);
        }
    }

    public function installListener(LogListener $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function clearListeners(): void
    {
        $this->listeners = [];
    }

    public static function instance(): Log
    {
        if (self::$instance === null) {
            self::$instance = new Log();
        }

        return self::$instance;
    }
}

function LOG(string $message, int $tabs = 0, int $level = Log::INFO): void
{
    Log::instance()->log($message, $tabs, $level);
}
