<?php

namespace CR;

class YAML
{
    public static function parse_file(string $path_to_file, bool $flatten = false): array|false
    {
        $value = false;

        try {
            $value = \Symfony\Component\Yaml\Yaml::parseFile($path_to_file);
        } catch (\Symfony\Component\Yaml\Exception\ParseException $exception) {
            LOG('YAML parse error: ' . $exception->getMessage(), 1, Log::ERROR);
        }

        if ($flatten) {
            return YAML::flatten($value);
        }

        return $value;
    }

    public static function flatten(array|false $yaml_data, string $base_string = ''): array
    {
        $flattened = [];

        if ($yaml_data) {
            foreach ($yaml_data as $key => $data) {
                if ($base_string) {
                    $new_string = $base_string . '.' . $key;
                } else {
                    $new_string = $key;
                }

                if (is_array($data)) {
                    $flattened[ $new_string ] = $data;
                    $flattened = array_merge($flattened, YAML::flatten($data, $new_string));
                } else {
                    $flattened[ $new_string ] = $data;
                }
            }
        }

        ksort($flattened);
        return $flattened;
    }

    public static function parse(string $data): array|false
    {
        $value = false;

        try {
            $value = \Symfony\Component\Yaml\Yaml::parse($data);
        } catch (\Symfony\Component\Yaml\Exception\ParseException $exception) {
            LOG('YAML parse error: ' . $exception->getMessage(), 1, Log::ERROR);
        }

        return $value;
    }
}
