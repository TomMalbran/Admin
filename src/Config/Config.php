<?php
namespace Admin\Config;

use Admin\Admin;
use Admin\File\File;
use Admin\Utils\Server;
use Admin\Utils\Strings;

use Dotenv\Dotenv;
use stdClass;

/**
 * The Config Data
 */
class Config {

    private static bool  $loaded = false;

    /** @var array{} */
    private static array $data;



    /**
     * Loads the Config Data
     * @return boolean
     */
    private static function load(): bool {
        if (self::$loaded) {
            return false;
        }

        $path    = Admin::getPath();
        $data    = self::loadENV($path, ".env");
        $replace = [];

        if (Server::isLocalHost()) {
            $data["URL"] = Server::updateLocalUrl($data["URL"]);
        } else {
            if (File::exists($path, ".env.dev")) {
                $config = self::loadENV($path, ".env.dev");
                if (!empty($config["URL"]) && Server::urlStartsWith($config["URL"])) {
                    $replace = $config;
                }
            }
            if (empty($replace) && File::exists($path, ".env.stage")) {
                $config = self::loadENV($path, ".env.stage");
                if (!empty($config["URL"]) && Server::urlStartsWith($config["URL"])) {
                    $replace = $config;
                }
            }
            if (empty($replace) && File::exists($path, ".env.production")) {
                $replace = self::loadENV($path, ".env.production");
            }
        }

        self::$loaded = true;
        self::$data   = array_merge($data, $replace);
        return true;
    }

    /**
     * Parses the Contents of the env files
     * @param string $path
     * @param string $fileName
     * @return array{}
     */
    private static function loadENV(string $path, string $fileName): array {
        $contents = File::read($path, $fileName);
        $lines    = Strings::split($contents, "\n");
        $result   = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $parts = Strings::split($line, " = ");
            if (count($parts) != 2) {
                continue;
            }

            $key   = trim($parts[0]);
            $value = trim($parts[1]);

            if ($value === "true") {
                $value = true;
            } elseif ($value === "false") {
                $value = false;
            } elseif (Strings::startsWith($value, "\"")) {
                $value = Strings::replace($value, "\"", "");
            } else {
                $value = (int)$value;
            }
            $result[$key] = $value;
        }
        return $result;
    }



    /**
     * Returns a Config Property or null
     * @param string $property
     * @return mixed
     */
    public static function get(string $property): mixed {
        self::load();

        // Check if there is a property with the given value
        $upperKey = Strings::camelCaseToUpperCase($property);
        if (isset(self::$data[$upperKey])) {
            return self::$data[$upperKey];
        }

        // Try to get all the properties that start with the value as a prefix
        $found  = false;
        $result = new stdClass();
        foreach (self::$data as $envKey => $value) {
            $parts  = Strings::split($envKey, "_");
            $prefix = Strings::toLowerCase($parts[0]);
            if ($prefix == $property) {
                $suffix = Strings::replace($envKey, "{$parts[0]}_", "");
                $key    = Strings::upperCaseToCamelCase($suffix);
                $found  = true;
                $result->{$key} = $value;
            }
        }
        if ($found) {
            return $result;
        }

        // We got nothing
        return null;
    }

    /**
     * Returns a Config Property or null
     * @param string $property
     * @return string[]
     */
    public static function getArray(string $property): array {
        $value = Config::get($property);
        if (!empty($value)) {
            return Strings::split($value, ",");
        }
        return [];
    }

    /**
     * Returns true if a Property exists
     * @param string $property
     * @return boolean
     */
    public static function has(string $property): bool {
        $value = self::get($property);
        return isset($value);
    }



    /**
     * Returns the Url adding the url parts at the end
     * @param string ...$urlParts
     * @return string
     */
    public static function getUrl(string ...$urlParts): string {
        $url  = self::get("url");
        $path = File::getPath(...$urlParts);
        $path = File::removeFirstSlash($path);
        return $url . $path;
    }

    /**
     * Return the admin Url adding the url parts at the end
     * @param string ...$urlParts
     * @return string
     */
    public static function getAdminUrl(string ...$urlParts): string {
        $url = self::getUrl(Admin::AdminDir, ...$urlParts);
        return File::addLastSlash($url);
    }

    /**
     * Return the public Url adding the url parts at the end
     * @param string ...$urlParts
     * @return string
     */
    public static function getPublicUrl(string ...$urlParts): string {
        $url = self::getUrl(Admin::AdminDir, Admin::PublicDir, ...$urlParts);
        return File::addLastSlash($url);
    }

    /**
     * Return the files Url adding the url parts at the end
     * @param string ...$urlParts
     * @return string
     */
    public static function getFilesUrl(string ...$urlParts): string {
        $url = self::getUrl(Admin::FilesDir, ...$urlParts);
        return File::addLastSlash($url);
    }

    /**
     * Return the internal public Url adding the url parts at the end
     * @param string ...$urlParts
     * @return string
     */
    public static function getInternalUrl(string ...$urlParts): string {
        $route = Admin::getInternalRoute();
        $url   = self::getUrl($route, Admin::PublicDir, ...$urlParts);
        return File::addLastSlash($url);
    }

    /**
     * Return the internal lib Url adding the url parts at the end
     * @return string
     */
    public static function getLibUrl(): string {
        $route = Admin::getInternalRoute();
        $url   = self::getUrl($route, Admin::LibDir);
        return File::addLastSlash($url);
    }

    /**
     * Return the admin Url adding the url parts at the end
     * @param boolean $forSite Optional.
     * @return string
     */
    public static function getBaseUrl(bool $forSite = false): string {
        $url = $forSite ? self::getUrl() : self::getAdminUrl();
        return parse_url($url, PHP_URL_PATH);
    }

    /**
     * Returns the Route
     * @param string $url
     * @return string
     */
    public static function getRoute(string $url): string {
        if (Strings::startsWith($url, "http")) {
            $baseUrl = self::getAdminUrl();
        } else {
            $baseUrl = self::getBaseUrl();
        }
        $result = Strings::replace($url, $baseUrl, "/");
        $result = Strings::replace($result, "//", "/");
        $result = Strings::substringBefore($result, "?");
        return $result;
    }

    /**
     * Returns the Version split into the different parts
     * @return object
     */
    public static function getVersion(): object {
        $version = self::get("version");
        if (empty($version)) {
            return (object)[
                "version" => "",
                "build"   => "",
                "full"    => "",
            ];
        }
        $parts = Strings::split($version, "-");
        return (object)[
            "version" => $parts[0],
            "build"   => $parts[1],
            "full"    => $version,
        ];
    }
}
