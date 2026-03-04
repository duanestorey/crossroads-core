<?php

namespace CR;

class LogListenerFile extends LogListener
{
    protected int $currentLevel = Log::INFO;
    protected float $startTime = 0;

    private string $fileName;
    /** @var resource|null */
    private mixed $fileHandle = null;

    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
        $this->startTime = microtime(true);
    }

    public function __destruct()
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }

    public function setLevel(int $level): void
    {
        $this->currentLevel = $level;
    }

    public function log(string $message, int $tabs, int $level): void
    {
        if ($level < $this->currentLevel) {
            return;
        }

        if (!$this->fileHandle) {
            $this->fileHandle = fopen($this->fileName, 'a+');
            fprintf($this->fileHandle, "\n");
        }

        $message = $this->getTabsAsSpaces($tabs) . $message;

        $elapsed = microtime(true) - $this->startTime;

        switch ($level) {
            case Log::DEBUG:
                fprintf($this->fileHandle, "%0.4fs - [DEBUG] %s\n", $elapsed, $message);
                break;
            case Log::INFO:
                fprintf($this->fileHandle, "%0.4fs - [INFO ] %s\n", $elapsed, $message);
                break;
            case Log::WARNING:
                fprintf($this->fileHandle, "%0.4fs - [WARN ] %s\n", $elapsed, $message);
                break;
            case Log::ERROR:
                fprintf($this->fileHandle, "%0.4fs - [ERROR] %s\n", $elapsed, $message);
                break;
        }

        fflush($this->fileHandle);
    }

    private function getTabsAsSpaces(int $tabs): string
    {
        $spaces = '';
        for ($i = 0; $i < $tabs; $i++) {
            $spaces = $spaces . '  ';
        }

        return $spaces;
    }
}
