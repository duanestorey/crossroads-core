<?php

namespace CR;

class FileWatcher
{
    protected $dirs = [];
    protected $extensions = [];
    protected $snapshot = [];

    public function __construct(array $dirs, array $extensions = [])
    {
        $this->dirs = $dirs;
        $this->extensions = $extensions ?: [ 'md', 'yaml', 'yml', 'latte', 'css', 'scss', 'js' ];
        $this->snapshot = $this->_scan();
    }

    public function check()
    {
        $current = $this->_scan();
        $changes = [];

        // Check for modified or added files
        foreach ($current as $path => $mtime) {
            if (!isset($this->snapshot[ $path ])) {
                $changes[] = [ 'type' => 'added', 'path' => $path ];
            } elseif ($this->snapshot[ $path ] !== $mtime) {
                $changes[] = [ 'type' => 'modified', 'path' => $path ];
            }
        }

        // Check for deleted files
        foreach ($this->snapshot as $path => $mtime) {
            if (!isset($current[ $path ])) {
                $changes[] = [ 'type' => 'deleted', 'path' => $path ];
            }
        }

        if (count($changes)) {
            $this->snapshot = $current;
        }

        return $changes;
    }

    protected function _scan()
    {
        $files = [];

        foreach ($this->dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->_scanDir($dir, $files);
        }

        return $files;
    }

    protected function _scanDir($dir, &$files)
    {
        $entries = @scandir($dir);
        if (!$entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry[0] === '.') {
                continue;
            }

            $path = $dir . '/' . $entry;

            if (is_dir($path)) {
                if ($entry === '_public' || $entry === 'vendor' || $entry === 'node_modules') {
                    continue;
                }
                $this->_scanDir($path, $files);
            } elseif (is_file($path)) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                if (in_array($ext, $this->extensions)) {
                    $files[ $path ] = filemtime($path);
                }
            }
        }
    }
}
