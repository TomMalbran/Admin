<?php
namespace Admin\Utils;

use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * Several Utils functions
 */
class Utils {

    /**
     * Returns true if the given value is alpha-numeric
     * @param string       $value
     * @param boolean      $withDashes Optional.
     * @param integer|null $length     Optional.
     * @return boolean
     */
    public static function isAlphaNum(string $value, bool $withDashes = false, ?int $length = null): bool {
        if ($length !== null && strlen($value) != $length) {
            return false;
        }
        if ($withDashes) {
            $value = str_replace([ "-", "_" ], "", $value);
        }
        return ctype_alnum($value);
    }

    /**
     * Returns true if the given email is valid
     * @param string $email
     * @return boolean
     */
    public static function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Returns true if the given password is valid
     * @param string  $password
     * @param string  $checkSets Optional.
     * @param integer $minLength Optional.
     * @return boolean
     */
    public static function isValidPassword(string $password, string $checkSets = "ad", int $minLength = 6): bool {
        if (Strings::length($password) < $minLength) {
            return false;
        }
        if (Strings::contains($checkSets, "a") && !Strings::match($password, "#[a-zA-Z]+#")) {
            return false;
        }
        if (Strings::contains($checkSets, "l") && !Strings::match($password, "#[a-z]+#")) {
            return false;
        }
        if (Strings::contains($checkSets, "u") && !Strings::match($password, "#[A-Z]+#")) {
            return false;
        }
        if (Strings::contains($checkSets, "d") && !Strings::match($password, "#[0-9]+#")) {
            return false;
        }
        return true;
    }



    /**
     * Returns the extension of the given domain (without the dot)
     * @param string $domain
     * @return string
     */
    public static function getDomainExtension(string $domain): string {
        return Strings::substringAfter($domain, ".");
    }

    /**
     * Returns the Youtube Embed Url
     * @param string  $source
     * @param boolean $autoplay Optional.
     * @param boolean $loop     Optional.
     * @return string
     */
    public static function getYoutubeEmbed(string $source, bool $autoplay = false, bool $loop = false): string {
        $videoID = "";
        $list    = "";
        if (Strings::startsWith($source, "https://youtu.be/")) {
            $videoID = Strings::replace($source, "https://youtu.be/", "");
        } elseif (Strings::startsWith($source, "https://www.youtube.com/watch?v=")) {
            $videoID = Strings::replace($source, "https://www.youtube.com/watch?v=", "");
        }
        if (Strings::contains($videoID, "&")) {
            $parts   = Strings::split($videoID, "&");
            $videoID = $parts[0];
            if (Strings::startsWith($parts[1], "list")) {
                $list = Strings::replace($parts[1], "list=", "");
                if (strstr($list, "&") !== FALSE) {
                    $list = Strings::split($list, "&")[0];
                }
            }
        }
        if (empty($videoID)) {
            return "";
        }

        $result = "https://www.youtube-nocookie.com/embed/{$videoID}?version=3&modestbranding=1&rel=0&showinfo=0&color=white";
        if (!empty($list)) {
            $result .= "&list=$list";
        }
        if ($autoplay) {
            $result .= "&autoplay=1";
        }
        if ($loop) {
            $result .= "&loop=1";
        }
        return $result;
    }
}
