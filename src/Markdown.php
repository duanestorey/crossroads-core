<?php

namespace CR;

class Markdown
{
    public array|false $frontMatter = false;
    public string|false $markdown = false;

    public function __construct()
    {
    }

    public function loadFile(string $filename): bool
    {
        $contents = file_get_contents($filename);

        if ($contents) {
            // find front matter
            $front = $this->_getfrontMatter($contents);
            if ($front) {
                // Strip front matter from markdown
                $this->markdown = trim(str_replace($front[ 0 ], '', $contents));
                $this->frontMatter = YAML::parse(trim($front[ 1 ]));
            } else {
                $this->markdown = $contents;
            }
        }

        return ($contents !== false);
    }

    public function frontMatter(): array|false
    {
        return $this->frontMatter;
    }

    public function rawMarkdown(): string|false
    {
        return $this->markdown;
    }

    public function strippedMarkdown(): string
    {
        return strip_tags($this->markdown);
    }

    public function html(): string
    {
        $parsedown = new \Parsedown();
        return $parsedown->text($this->markdown);
    }

    /** @return string[]|false */
    private function _getfrontMatter(string &$contents): array|false
    {
        if (preg_match('/---(.*)---/iUs', $contents, $matches)) {
            return $matches;
        } else {
            return false;
        }
    }
}
