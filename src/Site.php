<?php
namespace Admin;

use Admin\Admin;
use Admin\Config\Config;
use Admin\Config\Settings;
use Admin\Auth\Auth;
use Admin\File\File;
use Admin\File\Path;
use Admin\Provider\Mustache;
use Admin\Utils\Server;
use Admin\Utils\Strings;

/**
 * The Site
 */
class Site {

    /**
     * Creates the Admin
     * @param string  $adminPath
     * @param boolean $ensureUrl Optional.
     * @return void
     */
    public static function create(string $adminPath, bool $ensureUrl = false): void {
        if ($ensureUrl) {
            $url = Server::getPropperUrl();
            if (!empty($url)) {
                header("Location: $url");
                exit;
            }
        }
        Admin::create($adminPath, false);
        Auth::validateInternal();
    }

    /**
     * Returns the Config
     * @return object
     */
    public static function getConfig() {
        $url       = Config::getUrl();
        $baseUrl   = Config::getBaseUrl(true);
        $slugUrl   = Strings::stripStart($_SERVER["REQUEST_URI"], $baseUrl);
        $slugParts = Strings::split($slugUrl, "/");

        return (object)[
            "url"     => $url,
            "params"  => $_REQUEST,
            "section" => !empty($slugParts[0]) ? $slugParts[0] : "inicio",
            "page"    => !empty($slugParts[1]) ? $slugParts[1] : "",
        ];
    }

    /**
     * Returns a Setting
     * @param string $section
     * @param string $variable Optional.
     * @return mixed
     */
    public static function getSettings(string $section, string $variable = null) {
        if (empty($variable)) {
            return Settings::getAll($section);
        }
        return Settings::get($section, $variable);
    }

    /**
     * Returns just the data for the requested content
     * @param string $url
     * @param array  $params Optional.
     * @return array
     */
    public static function getData(string $url, array $params = []) {
        $response = Admin::request($url, $params);
        if (!empty($response) && !empty($response->data)) {
            return $response->data;
        }
        return [];
    }

    /**
     * Returns the requested content
     * @param string $url
     * @param array  $params Optional.
     * @return array
     */
    public static function request(string $url, array $params = []) {
        return Admin::request($url, $params);
    }

    /**
     * Prints the Template
     * @param string $template
     * @param array  $data
     * @return void
     */
    public static function print(string $template, array $data) {
        $path    = Admin::getPath(Admin::PublicDir, "site");
        $content = array_merge([
            "url"      => Config::getUrl(),
            "filesUrl" => Path::getUrl(Path::Source),
            "styles"   => Server::isLocalHost() ? "main.css" : "build.min.css",
        ], $data);

        if (File::exists($path, Admin::TemplatesDir, "{$template}.html")) {
            Mustache::print($template, $content, true);
        } else {
            Mustache::print("error", $content, true);
        }
    }
}
