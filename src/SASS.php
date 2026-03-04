<?php

namespace CR;

use ScssPhp\ScssPhp\Compiler;

class SASS
{
    public static function isSassFile(string $filename): bool
    {
        return ((strpos($filename, '.scss') !== false) || (strpos($filename, '.sass') !== false));
    }

    public static function parseFile(string $filename): string|false
    {
        // makes no assumptions about the file
        $sass = false;

        if (file_exists($filename)) {
            $contents = file_get_contents($filename);

            $compiler = new Compiler();
            $compiler->addImportPath(pathinfo($filename, PATHINFO_DIRNAME));

            $sass = $compiler->compileString($contents)->getCss();
        }

        return $sass;
    }
}
