<?php
namespace Admin;

use Admin\Admin;
use Admin\Config\Config;
use Admin\Config\Settings;
use Admin\IO\Request;
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
     * @param boolean $ensureUrl Optional.
     * @return boolean
     */
    public static function create(bool $ensureUrl = true): bool {
        if ($ensureUrl) {
            $url = Server::getProperUrl();
            if (!empty($url)) {
                header("Location: $url");
                exit;
            }
        }

        Admin::create();
        return true;
    }

    /**
     * Returns the Config
     * @return object
     */
    public static function getConfig(): object {
        $url       = Config::getUrl();
        $baseUrl   = Config::getBaseUrl(true);
        $slugUrl   = Strings::stripStart($_SERVER["REQUEST_URI"], $baseUrl);
        $slugParts = Strings::split($slugUrl, "/");

        return (object)[
            "url"     => $url,
            "params"  => $_REQUEST,
            "request" => new Request(),
            "section" => !empty($slugParts[0]) ? $slugParts[0] : "inicio",
            "page"    => !empty($slugParts[1]) ? $slugParts[1] : "",
        ];
    }

    /**
     * Returns a Setting
     * @param string      $section
     * @param string|null $variable Optional.
     * @return mixed
     */
    public static function getSettings(string $section, ?string $variable = null): mixed {
        if (empty($variable)) {
            return Settings::getAllParsed($section);
        }
        return Settings::get($section, $variable);
    }

    /**
     * Prints the Template
     * @param string  $template
     * @param array{} $data     Optional.
     * @return boolean
     */
    public static function print(string $template, array $data = []): bool {
        $path    = Admin::getPath(Admin::PublicDir, "site");
        $content = array_merge([
            "title"     => Config::get("name"),
            "url"       => Config::getUrl(),
            "libUrl"    => Config::getLibUrl(),
            "filesUrl"  => Path::getUrl(Path::Source),
            "styles"    => Server::isLocalHost() ? "main.css" : "build.min.css",
            "recaptcha" => Config::get("recaptchaKey"),
            "year"      => date("Y"),
        ], $data);

        if (File::exists($path, Admin::TemplatesDir, "{$template}.html")) {
            return Mustache::print($template, $content, true);
        }
        return Mustache::print("error", $content, true);
    }
}
