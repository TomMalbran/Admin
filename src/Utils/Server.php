<?php
namespace Admin\Utils;

use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * The Server Utils
 */
class Server {

    /**
     * Returns true if running on Localhost
     * @param string[] $whitelist
     * @return boolean
     */
    public static function isLocalHost(array $whitelist = [ "127.0.0.1", "::1" ]): bool {
        return Arrays::contains($whitelist, $_SERVER["REMOTE_ADDR"]);
    }

    /**
     * Updates the Localhost Url
     * @param string $url
     * @return string
     */
    public static function updateLocalUrl(string $url): string {
        if (Strings::contains($url, "localhost") && $_SERVER["REMOTE_ADDR"] === "127.0.0.1") {
            return Strings::replace($url, "localhost", "127.0.0.1");
        }
        if (Strings::contains($url, "127.0.0.1") && $_SERVER["REMOTE_ADDR"] === "::1") {
            return Strings::replace($url, "127.0.0.1", "localhost");
        }
        return $url;
    }



    /**
     * Returns the Origin Url
     * @param boolean $useForwarded Optional.
     * @return string
     */
    public static function getUrl(bool $useForwarded = false): string {
        $ssl      = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on";
        $sp       = Strings::toLowerCase($_SERVER["SERVER_PROTOCOL"]);
        $protocol = substr($sp, 0, strpos($sp, "/")) . ($ssl ? "s" : "");
        $port     = $_SERVER["SERVER_PORT"];
        $port     = (!$ssl && $port == "80") || ($ssl && $port == "443") ? "" : ":$port";
        $host     = $useForwarded && isset($_SERVER["HTTP_X_FORWARDED_HOST"]) ? $_SERVER["HTTP_X_FORWARDED_HOST"] : ($_SERVER["HTTP_HOST"] ?: null);
        $host     = $host ?: $_SERVER["SERVER_NAME"] . $port;
        return "$protocol://$host";
    }

    /**
     * Returns the Full Url
     * @param boolean $useForwarded
     * @return string
     */
    public static function getFullUrl(bool $useForwarded = false): string {
        return self::getUrl($useForwarded) . $_SERVER["REQUEST_URI"];
    }

    /**
     * Returns a proper url using www and ssl
     * @return string
     */
    public static function getProperUrl(): string {
        if (self::isLocalHost()) {
            return "";
        }
        if ($_SERVER["HTTPS"] !== "on" && substr($_SERVER["HTTP_HOST"], 0, 4) !== "www.") {
            return "https://www.{$_SERVER["HTTP_HOST"]}{$_SERVER["REQUEST_URI"]}";
        }
        if ($_SERVER["HTTPS"] !== "on" && substr($_SERVER["HTTP_HOST"], 0, 4) === "www.") {
            return "https://{$_SERVER["HTTP_HOST"]}{$_SERVER["REQUEST_URI"]}";
        }
        if (substr($_SERVER["HTTP_HOST"], 0, 4) !== "www.") {
            $protocol = $_SERVER["HTTPS"] == "on" ? "https://" : "http://";
            return "{$protocol}www.{$_SERVER["HTTP_HOST"]}{$_SERVER["REQUEST_URI"]}";
        }
        return "";
    }

    /**
     * Returns true if the given Url is at the start
     * @param string $url
     * @return boolean
     */
    public static function urlStartsWith(string $url): bool {
        $currentUrl = self::getProperUrl();
        if (empty($currentUrl)) {
			$currentUrl = self::getFullUrl();
		}
        return Strings::startsWith($currentUrl, $url);
    }



    /**
     * Returns the user IP
     * @return string
     */
    public static function getIP(): string {
        if ($_SERVER) {
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
                $ip = $_SERVER["HTTP_CLIENT_IP"];
            } else {
                $ip = $_SERVER["REMOTE_ADDR"];
            }
        } else {
            if (getenv("HTTP_X_FORWARDED_FOR")) {
                $ip = getenv("HTTP_X_FORWARDED_FOR");
            } elseif (getenv("HTTP_CLIENT_IP")) {
                $ip = getenv("HTTP_CLIENT_IP");
            } else {
                $ip = getenv("REMOTE_ADDR");
            }
        }
        return $ip;
    }

    /**
     * Returns the User Agent
     * @return string
     */
    public static function getUserAgent(): string {
        return $_SERVER["HTTP_USER_AGENT"];
    }
}
