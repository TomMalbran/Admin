<?php
namespace Admin\Utils;

use Admin\File\File;
use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * The CSV Utils
 */
class CSV {

    /**
     * Converts an array or string to a CSV string
     * @param string[]|string $value
     * @param string          $separator Optional.
     * @return string
     */
    public static function encode(array|string $value, string $separator = ","): string {
        if (is_string($value)) {
            $parts = Strings::split($value, $separator);
            $parts = Arrays::removeEmpty($parts);
            return Strings::join($parts, $separator);
        }
        if (Arrays::isArray($value)) {
            $parts = Arrays::removeEmpty($value);
            return Strings::join($parts, $separator);
        }
        return "";
    }

    /**
     * Converts an array or string to a CSV array
     * @param string[]|string $value
     * @param string          $separator Optional.
     * @return mixed[]
     */
    public static function decode(array|string $value, string $separator = ","): array {
        if (is_string($value)) {
            return Strings::split($value, $separator, true);
        }
        if (Arrays::isArray($value)) {
            return $value;
        }
        return [];
    }



    /**
     * Reads a CSV file
     * @param string  $path
     * @param string  $separator  Optional.
     * @param boolean $skipHeader Optional.
     * @return mixed[]
     */
    public static function readFile(string $path, string $separator = ",", bool $skipHeader = false): array {
        if (!File::exists($path)) {
            return [];
        }
        $content = file_get_contents($path);
        $lines   = Strings::split($content, "\n", true);
        $result  = [];
        foreach ($lines as $index => $line) {
            if (!empty($line) && ($index > 0 || !$skipHeader)) {
                $result[] = self::decode($line, $separator);
            }
        }
        return $result;
    }

    /**
     * Writes a CSV File
     * @param string   $path
     * @param string[] $contents
     * @param string   $separator Optional.
     * @return boolean
     */
    public static function writeFile(string $path, array $contents, string $separator = ","): bool {
        $lines = [];
        foreach ($contents as $row) {
            $lines[] = self::encode($row, $separator);
        }
        $result = file_put_contents($path, Strings::join($lines, "\n"));
        return $result != false;
    }
}
