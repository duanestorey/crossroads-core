<?php

namespace CR;

class Utils
{
    public static function fixPath(string $dir): string
    {
        return rtrim($dir, '\\/');
    }

    public static function copyFile(string $source, string $dest): void
    {
        copy($source, $dest);
    }

    public static function mkdir(string $dirname): void
    {
        if (!file_exists($dirname)) {
            @mkdir($dirname);
        }
    }

    public static function titleToSlug(string $title): string
    {
        return preg_replace('/[^a-zA-Z0-9-]/', '', str_replace([ ' ', '_', '-' ], '-', strtolower($title)));
    }

    public static function recursiveRmdir(string $directory): void
    {
        $files = array_diff(scandir($directory), [ '.', '..' ]);
        if (count($files)) {
            foreach ($files as $file) {
                $cur_location = $directory . '/' . $file;

                if (is_dir($cur_location)) {
                    Utils::recursiveRmdir($cur_location);
                } else {
                    unlink($cur_location);
                }
            }
        }

        rmdir($directory);
    }

    public static function curlDownloadFile(string $url): string|false
    {
        $result = false;

        $ch = curl_init($url);
        if ($ch) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10) ;
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $result = curl_exec($ch);

            if ($result !== false) {
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($status != 200) {
                    $result = false;
                }
            }

            curl_close($ch);
        }

        return $result;
    }

    public static function cleanTerm(string $term): string
    {
        return strtolower(str_replace(' ', '-', $term));
    }

    /**
     * Detect optimal worker count for parallel operations.
     * Reads options.build_workers from config (0 = auto, 1 = sequential).
     */
    public static function getWorkerCount(Config $config): int
    {
        $configured = (int) $config->get('options.build_workers', 0);

        if ($configured === 1) {
            return 1;
        }

        if ($configured > 1) {
            return min($configured, 16);
        }

        // Auto-detect CPU count
        $cpuCount = 0;
        if (PHP_OS_FAMILY === 'Darwin') {
            $result = shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            if ($result !== null) {
                $cpuCount = (int) trim($result);
            }
        } else {
            $result = shell_exec('nproc 2>/dev/null');
            if ($result !== null) {
                $cpuCount = (int) trim($result);
            }
        }

        return max(2, min($cpuCount ?: 4, 16));
    }

    /** @return string[] */
    public static function findAllFilesWithExtension(string $directory, string|array $ext): array
    {
        $allFiles = [];
        if (!is_array($ext)) {
            $ext = [ $ext ];
        }

        if (!file_exists($directory)) {
            return $allFiles;
        }

        $filenames = array_diff(scandir($directory), [ '.', '..' ]);
        foreach ($filenames as $one_file) {
            $full_path = $directory . '/' . $one_file;
            if (is_dir($full_path)) {
                $allFiles = array_merge($allFiles, Utils::findAllFilesWithExtension($full_path, $ext));
            } elseif (in_array(pathinfo($full_path, PATHINFO_EXTENSION), $ext)) {
                $allFiles[] = $full_path;
            }
        }

        return $allFiles;
    }
}
