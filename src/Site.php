<?php
namespace Admin;

use Admin\Admin;
use Admin\Config\Config;
use Admin\Config\Settings;
use Admin\Auth\Auth;
use Admin\Provider\Mustache;
use Admin\Utils\Server;

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
        $isLocal   = Server::isLocalHost();
        $baseUrl   = parse_url($url, PHP_URL_PATH);
        $slugUrl   = str_replace($baseUrl, "", $_SERVER["REQUEST_URI"]);
        $slugParts = explode("/", $slugUrl);
        
        return (object)[
            "url"     => $url,
            "params"  => $_REQUEST,
            "styles"  => $isLocal ? "main.css" : "build.min.css",
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
     * @param array  $request Optional.
     * @return array
     */
    public static function getData(string $url, array $request = []) {
        $response = Admin::request($url, $request);
        if (!empty($response) && !empty($response->data)) {
            return $response->data;
        }
        return [];
    }

    /**
     * Prints the Template
     * @param string $template
     * @param array  $data
     * @return void
     */
    public static function print(string $template, array $data) {
        if (file_exists("public/templates/{$template}.html")) {
            Mustache::print($template, $data, true);
        } else {
            Mustache::print("error", $data, true);
        }
    }
}
