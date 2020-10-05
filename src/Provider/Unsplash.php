<?php
namespace Admin\Provider;

use Admin\Config\Config;

use Crew\Unsplash\HttpClient;
use Crew\Unsplash\Photo;
use Crew\Unsplash\Exception;

/**
 * The Unsplash Provider
 */
class Unsplash {

    private static $loaded = false;


    /**
     * Creates the Unsplash instance
     * @return void
     */
    public static function load(): void {
        if (!self::$loaded) {
            self::$loaded = true;

            HttpClient::init([
                "applicationId" => Config::get("unsplashClient"),
                "secret"        => Config::get("unsplashSecret"),
                "utmSource"     => Config::get("name"),
                "callbackUrl"   => "https://your-application.com/oauth/callback",
            ]);
        }
    }

    /**
     * Returns an Image
     * @return array|null
     */
    public static function getImage() {
        self::load();

        try {
            $result = Photo::random([
                "featured" => true,
                "query"    => "landscape",
            ]);
            return [
                "location" => !empty($result->location) ? $result->location["city"] . ", " . $result->location["country"] : "",
                "author"   => $result->user["name"],
                "url"      => $result->urls["regular"],
            ];
        } catch (Exception $e) {
            return null;
        }
    }
}
