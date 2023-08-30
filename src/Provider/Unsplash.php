<?php
namespace Admin\Provider;

use Admin\Provider\Curl;

/**
 * The Unsplash Provider
 */
class Unsplash {

    const BaseUrl   = "https://api.unsplash.com/";
    const AccessKey = "12ede3b69ebd10e89dc42649cd3f69a8665c1570e701289b08540c8db1cbd957";


    /**
     * Returns an Image
     * @return array{}|null
     */
    public static function getImage(): ?array {
        $result = Curl::get(self::BaseUrl . "photos/random", [
            "client_id" => self::AccessKey,
            "query"     => [
                "featured" => "true",
                "query"    => "landscape",
            ],
        ], null, true);

        return [
            "location" => !empty($result["location"]) ? $result["location"]["city"] . ", " . $result["location"]["country"] : "",
            "author"   => $result["user"]["name"],
            "url"      => $result["urls"]["regular"],
        ];
    }
}
