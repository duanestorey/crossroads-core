<?php

namespace CR;

class LatteFileLoader extends \Latte\Loaders\FileLoader
{
    /** @var string[] */
    protected array $templateDirs = [];

    /** @param string[] $templateDirs */
    public function setDirectories(array $templateDirs): void
    {
        $this->templateDirs = $templateDirs;
    }

    public function getContent(string $fileName): string
    {
        foreach ($this->templateDirs as $dir) {
            $pathToFile = $dir . '/' . $fileName;

            if (file_exists($pathToFile)) {
                return file_get_contents($pathToFile);
            }
        }

        // not found
        throw new \Latte\RuntimeException("Missing template file '$fileName'.");
    }
}
