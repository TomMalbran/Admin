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

    private static bool $loaded = false;


    /**
     * Creates the Unsplash instance
     * @return boolean
     */
    private static function load(): bool {
        if (self::$loaded) {
            return false;
        }
        self::$loaded = true;

        HttpClient::init([
            "utmSource"     => Config::get("name"),
            "applicationId" => "12ede3b69ebd10e89dc42649cd3f69a8665c1570e701289b08540c8db1cbd957",
            "secret"        => "de184adeef5a7bc30bcffc9077695b48a993c6643e817114fbba3dcb951d6d5a",
            "callbackUrl"   => "https://your-application.com/oauth/callback",
        ]);
        return true;
    }

    /**
     * Returns an Image
     * @return array{}|null
     */
    public static function getImage(): ?array {
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
