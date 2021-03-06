<?php
namespace Admin\Config;

use Admin\Utils\JSON;

/**
 * The Setting Types used by the System
 */
class SettingType {

    const General = 0;
    const Binary  = 1;
    const JSON    = 2;



    /**
     * Returns the Setting Type based on the value
     * @param mixed $value
     * @return integer
     */
    public static function get($value): int {
        if (is_array($value)) {
            return self::JSON;
        }
        if (gettype($value) == "boolean") {
            return self::Binary;
        }
        return self::General;
    }

    /**
     * Parses a Settings Value
     * @param Model|array $data
     * @return mixed
     */
    public static function parseValue($data) {
        switch ($data["type"]) {
        case self::Binary:
            return !empty($data["value"]);
        case self::JSON:
            return JSON::decode($data["value"]);
        default:
            return $data["value"];
        }
    }
}
