<?php
namespace Admin\Utils;

/**
 * Several String Utils
 */
class Strings {

    /**
     * Returns the length og the given String
     * @param string $string
     * @return integer
     */
    public static function length(string $string): int {
        return strlen($string);
    }

    /**
     * Returns true if the given String contains the given Needle
     * @param string $string
     * @param string $needle
     * @return boolean
     */
    public static function contains(string $string, string $needle): bool {
        return strstr($string, $needle) !== FALSE;
    }

    /**
     * Returns true if the given String matches the given Pattern
     * @param string $string
     * @param string $pattern
     * @return boolean
     */
    public static function match(string $string, string $pattern): bool {
        return preg_match($pattern, $string);
    }

    /**
     * Returns true if the given String starts with the given Needle
     * @param string $string
     * @param string $needle
     * @return boolean
     */
    public static function startsWith(string $string, string $needle): bool {
        $length = strlen($needle);
        return substr($string, 0, $length) === $needle;
    }

    /**
     * Returns true if the given String ends with the given Needle
     * @param string $string
     * @param string $needle
     * @return boolean
     */
    public static function endsWith(string $string, string $needle): bool {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        return substr($string, -$length) === $needle;
    }



    /**
     * Returns a random String with the given length
     * @param integer $length Optional.
     * @return string
     */
    public static function random(int $length = 50): string {
        return substr(md5(rand()), 0, $length);
    }

    /**
     * Returns a random char from the given String
     * @param string $string
     * @return string
     */
    public static function randomChar(string $string): string {
        $parts = str_split($string);
        $index = array_rand($parts);
        return $string[$index];
    }

    /**
     * Generates a random String with the given options
     * @param integer $length        Optional.
     * @param string  $availableSets Optional.
     * @return string
     */
    public static function randomCode(int $length = 8, string $availableSets = "lud"): string {
        $sets   = [];
        $all    = "";
        $result = "";

        if (self::contains($availableSets, "a")) {
            $sets[] = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        } else {
            if (self::contains($availableSets, "l")) {
                $sets[] = "abcdefghijklmnopqrstuvwxyz";
            }
            if (self::contains($availableSets, "u")) {
                $sets[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            }
        }
        if (self::contains($availableSets, "d")) {
            $sets[] = "0123456789";
        }
        if (self::contains($availableSets, "s")) {
            $sets[] = "!@#$%&*?";
        }

        foreach ($sets as $set) {
            $result .= self::randomChar($set);
            $all    .= $set;
        }

        $all = str_split($all);
        for ($i = 0; $i < $length - count($sets); $i++) {
            $result .= $all[array_rand($all)];
        }

        $result = str_shuffle($result);
        return $result;
    }



    /**
     * Format a string by replacing placeholder symbols with passed in arguments.
     * @param string $string
     * @param string ...$args
     * @return string
     */
    public static function format(string $string, string ...$args): string {
        $result = $string;
        for ($i = 0; $i < count($args); $i++) {
            $result = str_replace("{" . $i . "}", $args[$i], $result);
        }
        return $result;
    }

    /**
     * Replaces in the String the search with the replace
     * @param string          $string
     * @param string|string[] $search
     * @param string|string[] $replace
     * @return string
     */
    public static function replace(string $string, $search, $replace): string {
        return str_replace($search, $replace, $string);
    }

    /**
     * Removes the Needle from the start of the String
     * @param string $string
     * @param string $needle
     * @return string
     */
    public static function stripStart(string $string, string $needle): string {
        if (self::startsWith($string, $needle)) {
            $length = strlen($needle);
            return substr($string, $length, strlen($string) - $length);
        }
        return $string;
    }

    /**
     * Removes the Needle from the end of the String
     * @param string $string
     * @param string $needle
     * @return string
     */
    public static function stripEnd(string $string, string $needle): string {
        if (self::endsWith($string, $needle)) {
            $length = strlen($needle);
            return substr($string, 0, strlen($string) - $length);
        }
        return $string;
    }



    /**
     * Returns a Substring from the Start to the Length
     * @param string  $string
     * @param integer $start
     * @param integer $length Optional.
     * @return string
     */
    public static function substring(string $string, int $start, int $length = null): string {
        return substr($string, $start, $length);
    }

    /**
     * Returns a Substring from the Needle to the end
     * @param string $string
     * @param string $needle
     * @return string
     */
    public static function substringAfter(string $string, string $needle): string {
        if (self::contains($string, $needle)) {
            return substr($string, strrpos($string, $needle) + strlen($needle));
        }
        return $string;
    }

    /**
     * Returns a Substring from the start to the Needle
     * @param string $string
     * @param string $needle
     * @return string
     */
    public static function substringBefore(string $string, string $needle): string {
        if (self::contains($string, $needle)) {
            return substr($string, 0, strpos($string, $needle));
        }
        return $string;
    }



    /**
     * Splits the given String at the given Needle
     * @param string[]|string $string
     * @param string          $needle
     * @return string[]
     */
    public static function split($string, string $needle): array {
        if (!is_array($string)) {
            return !empty($string) ? explode($needle, $string) : [];
        }
        return $string;
    }

    /**
     * Jois the given Strings using the given glue
     * @param string[]|string $string
     * @param string          $glue
     * @return string
     */
    public static function join($string, string $glue): string {
        if (is_array($string)) {
            return implode($glue, $string);
        }
        return $string;
    }

    /**
     * Jois the given Strings keys using the given glue
     * @param string[]|string $string
     * @param string          $glue
     * @return string
     */
    public static function joinKeys($string, string $glue): string {
        if (is_array($string)) {
            return implode($glue, array_keys($string));
        }
        return $string;
    }



    /**
     * Returns true if the values are Equal
     * @param string  $string
     * @param string  $other
     * @param boolean $caseInsensitive Optional.
     * @return boolean
     */
    public static function isEqual(string $string, string $other, bool $caseInsensitive = true): bool {
        if ($caseInsensitive) {
            return strtolower($string) === strtolower($other);
        }
        return $string === $other;
    }

    /**
     * Transforms a String to Uppercase
     * @param string $string
     * @return string
     */
    public static function toLowerCase(string $string): string {
        return strtolower($string);
    }

    /**
     * Transforms a String to Lowercase
     * @param string $string
     * @return string
     */
    public static function toUpperCase(string $string): string {
        return strtoupper($string);
    }

    /**
     * Transforms an Uppercase string with underscores to Camelcase
     * @param string  $string
     * @param boolean $capitalizeFirst Optional.
     * @return string
     */
    public static function upperCaseToCamelCase(string $string, bool $capitalizeFirst = false): string {
        $result = ucwords(strtolower($string), "_");
        $result = str_replace("_", "", $result);
        if (!$capitalizeFirst) {
            $result = lcfirst($result);
        }
        return $result;
    }

    /**
     * Transforms an CamelCase string to UpperCase with underscores
     * @param string $string
     * @return string
     */
    public static function camelCaseToUpperCase(string $string): string {
        $parts  = preg_split('/(?=[A-Z])/', $string);
        $result = implode("_", $parts);
        $result = strtoupper($result);
        return $result;
    }

    /**
     * Replaces the slashes in an Url with double-dashes to create a String for a Url
     * @param string $value
     * @return string
     */
    public static function fromUrl(string $value): string {
        return str_replace("/", "--", $value);
    }

    /**
     * Replaces the double-dashes in a String with slashes to create a Url
     * @param string $value
     * @return string
     */
    public static function toUrl(string $value): string {
        $result = str_replace("--", "/", $value);
        $result = str_replace("%20", " ", $result);
        return $result;
    }



    /**
     * Returns the HTML version of the given string
     * @param string $string
     * @return string
     */
    public static function toHtml(string $string): string {
        return str_replace("\n", "<br>", $string);
    }

    /**
     * Return an escaped string that can be broken at certain separators
     * @param string $string
     * @return string
     */
    public static function makeBreakable(string $string): string {
        // Inject zero-width space character (U+200B) near (_ or - or @) to allow line breaking there
        return preg_replace('/(_|@|-)/', "$1" . "&#8203;", $string);
    }

    /**
     * Returns a short version of the given string
     * @param string  $string
     * @param integer $length Optional.
     * @return string
     */
    public static function makeShort(string $string, int $length = 30): string {
        $first = explode("\n", $string)[0];
        if (strlen($first) > $length) {
            return mb_substr($first, 0, $length, "utf-8") . "...";
        }
        return $first;
    }

    /**
     * Returns true if the short version is different from the string
     * @param string  $string
     * @param integer $length Optional.
     * @return string
     */
    public static function isShort(string $string, int $length = 30): string {
        return self::makeShort($string, $length) !== $string;
    }

    /**
     * Slugifies a String
     * @param string $string
     * @return string
     */
    public static function slugify(string $string): string {
        return self::sanitize($string, true, true);
    }

    /**
     * Sanitizes a String
     * @param string  $string
     * @param boolean $lowercase Optional.
     * @param boolean $anal      Optional.
     * @return string
     */
    public static function sanitize(string $string, bool $lowercase = true, bool $anal = false): string {
        $strip = [
            "~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
            "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
            "â€”", "â€“", ",", "<", ".", ">", "/", "?",
        ];
        $clean = trim(str_replace($strip, "", strip_tags($string)));
        $clean = preg_replace('/\s+/', "-", $clean);

        if ($anal) {
            $tilde = [ "á", "é", "í", "ó", "ú", "ü", "ñ", "Á", "É", "Í", "Ó", "Ú", "Ü", "Ñ" ];
            $with  = [ "a", "e", "i", "o", "u", "u", "n", "A", "E", "I", "O", "U", "U", "N" ];
            $clean = str_replace($tilde, $with, $clean);
            $clean = preg_replace("/[^a-zA-Z0-9\-]/", "", $clean);
            $clean = preg_replace("/---/", "-", $clean);
            $clean = preg_replace("/--/", "-", $clean);
        }
        if ($lowercase) {
            return function_exists("mb_strtolower") ? mb_strtolower($clean, "UTF-8") : strtolower($clean);
        }
        return $clean;
    }
}
