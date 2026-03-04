<?php

namespace CR;

class Plugin
{
    protected string $name = 'base';

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function contentFilter(mixed $content): mixed
    {
        return $content;
    }

    public function templateParamFilter(mixed $params): mixed
    {
        return $params;
    }

    public function processOne(mixed $entry): mixed
    {
        return $entry;
    }

    /**
     * @param array $entries
     * @return array
     */
    public function processAll(array $entries): array
    {
        $processed = [];

        foreach ($entries as $entry) {
            $processed[] = $this->processOne($entry);
        }

        return $processed;
    }
}
