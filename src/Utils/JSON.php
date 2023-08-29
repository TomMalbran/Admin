<?php
namespace Admin\Utils;

use Admin\File\File;
use Admin\Utils\Arrays;
use Admin\Utils\Strings;

use stdClass;

/**
 * The JSON Utils
 */
class JSON {

    /**
     * Returns true if the given value is a JSON object
     * @param array|string $value
     * @return boolean
     */
    public static function isValid($value): bool {
        if (is_string($value)) {
            json_decode($value);
            return json_last_error() == JSON_ERROR_NONE;
        }
        return false;
    }

    /**
     * Encodes an Object as a string if it is not already encoded
     * @param array|string $value
     * @param boolean      $asPretty Optional.
     * @return string
     */
    public static function encode($value, bool $asPretty = false): string {
        if (self::isValid($value)) {
            return $value;
        }
        return json_encode($value, $asPretty ? JSON_PRETTY_PRINT : 0);
    }

    /**
     * Decodes a String if it is not already decoded
     * @param array|string $value
     * @param boolean      $asArray Optional.
     * @return mixed
     */
    public static function decode($value, bool $asArray = false): mixed {
        if (!self::isValid($value)) {
            return $value;
        }
        return json_decode($value, $asArray);
    }



    /**
     * Converts a Coma Separated Value into an encoded JSON
     * @param string $value
     * @return string
     */
    public static function fromCSV(string $value): string {
        $parts  = Strings::split($value, ",");
        $result = [];
        foreach ($parts as $part) {
            if (!empty($part)) {
                $result[] = trim($part);
            }
        }
        return self::encode($result);
    }



    /**
     * Reads a JSON file
     * @param string  $path
     * @param boolean $asArray Optional.
     * @return object|array
     */
    public static function readFile(string $path, $asArray = false) {
        if (File::exists($path)) {
            return self::decode(file_get_contents($path), $asArray);
        }
        return $asArray ? [] : new stdClass();
    }

    /**
     * Reads a JSON url
     * @param string  $url
     * @param boolean $asArray Optional.
     * @return object|array
     */
    public static function readUrl(string $url, $asArray = false) {
        return self::decode(file_get_contents($url), $asArray);
    }

    /**
     * Writes a JSON File
     * @param string          $path
     * @param string[]|string $contents
     * @return boolean
     */
    public static function writeFile(string $path, $contents): bool {
        $value  = Arrays::toArray($contents);
        $result = file_put_contents($path, self::encode($value, true));
        return $result !== null;
    }
}
