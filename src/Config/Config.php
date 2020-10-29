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
    
    private static $loaded = false;
    private static $data   = null;
    
    
    /**
     * Loads the Config Data
     * @return void
     */
    public static function load(): void {
        if (!self::$loaded) {
            $path    = Admin::getPath();
            $data    = Dotenv::createImmutable($path)->load();
            $replace = [];

            if (Server::isDevHost()) {
                if (File::exists($path, ".env.dev")) {
                    $replace = Dotenv::createMutable($path, ".env.dev")->load();
                }
            } elseif (Server::isStageHost()) {
                if (File::exists($path, ".env.stage")) {
                    $replace = Dotenv::createMutable($path, ".env.stage")->load();
                }
            } elseif (!Server::isLocalHost()) {
                if (File::exists($path, ".env.production")) {
                    $replace = Dotenv::createMutable($path, ".env.production")->load();
                }
            }

            self::$loaded = true;
            self::$data   = array_merge($data, $replace);

            foreach (self::$data as $key => $value) {
                if ($value === "true") {
                    self::$data[$key] = true;
                } elseif ($value === "false") {
                    self::$data[$key] = false;
                }
            }
        }
    }



    /**
     * Returns a Config Property or null
     * @param string $property
     * @return mixed
     */
    public static function get(string $property) {
        self::load();

        // Check if there is a property with the given value
        $upperkey = Strings::camelCaseToUpperCase($property);
        if (isset(self::$data[$upperkey])) {
            return self::$data[$upperkey];
        }

        // Try to get all the properties that start with the value as a prefix
        $found  = false;
        $result = new stdClass();
        foreach (self::$data as $envkey => $value) {
            $parts  = Strings::split($envkey, "_");
            $prefix = Strings::toLowerCase($parts[0]);
            if ($prefix == $property) {
                $suffix = Strings::replace($envkey, "{$parts[0]}_", "");
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
     * @return array
     */
    public static function getArray(string $property) {
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
    public static function getInternalUrl(string ...$urlParts) {
        $route = Admin::getInternalRoute();
        $url   = self::getUrl($route, Admin::PublicDir, ...$urlParts);
        return File::addLastSlash($url);
    }

    /**
     * Return the admin Url adding the url parts at the end
     * @return string
     */
    public static function getBaseUrl(): string {
        $url = self::getAdminUrl();
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
        return $result;
    }

    /**
     * Returns the Version split into the diferent parts
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
