<?php
namespace Admin\Config;

use Admin\Config\SettingType;
use Admin\Schema\Database;
use Admin\Schema\Query;

/**
 * The Core Settings used by the System
 */
class CoreSetting {

    const Migration = "lastMigration";



    /**
     * Creates a Settings Query
     * @param string $section
     * @param string $variable
     * @return Query
     */
    public static function createQuery(string $section, string $variable): Query {
        $query = Query::create("section", "=", $section);
        $query->add("variable", "=", $variable);
        return $query;
    }

    /**
     * Inits a Core Settings
     * @param Database $db
     * @param string   $variable
     * @param integer  $value
     * @return void
     */
    public static function init(Database $db, string $variable, int $value): void {
        $db->insert("settings", [
            "section"      => "core",
            "variable"     => $variable,
            "value"        => $value,
            "type"         => SettingType::General,
            "modifiedTime" => time(),
        ], "REPLACE");
    }

    /**
     * Sets a Core Preference
     * @param Database $db
     * @param string   $variable
     * @param integer  $value
     * @return void
     */
    public static function set(Database $db, string $variable, int $value): void {
        $query = self::createQuery("core", $variable);
        $db->update("settings", [ "value" => $value ], $query);
    }

    /**
     * Returns a Core Preference
     * @param Database $db
     * @param string   $variable
     * @return integer
     */
    public static function get(Database $db, string $variable): int {
        $query  = self::createQuery("core", $variable);
        $result = $db->getValue("settings", "value", $query);
        return !empty($result) ? $result : 0;
    }
}
