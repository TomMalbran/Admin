<?php
namespace Admin\Config;

use Admin\Admin;
use Admin\Config\SettingType;
use Admin\Schema\Factory;
use Admin\Schema\Schema;
use Admin\Schema\Database;
use Admin\Schema\Query;
use Admin\Utils\Arrays;
use Admin\Utils\JSON;
use Admin\Utils\Strings;

/**
 * The Settings Data
 */
class Settings {
    
    private static $loaded = false;
    private static $schema = null;
    
    
    /**
     * Loads the Settings Schemas
     * @return Schema
     */
    public static function getSchema(): Schema {
        if (!self::$loaded) {
            self::$loaded = true;
            self::$schema = Factory::getSchema("settings");
        }
        return self::$schema;
    }
    
    

    /**
     * Returns a single Setting
     * @param string $section
     * @param string $variable
     * @return mixed|null
     */
    public static function get(string $section, string $variable) {
        $query = Query::create("section", "=", $section);
        $query->add("variable", "=", $variable);
        $model = self::getSchema()->getOne($query);
        if (!$model->isEmpty()) {
            return SettingType::parseValue($model);
        }
        return null;
    }

    /**
     * Returns a single Setting as an Integer
     * @param string $variable
     * @param string $section  Optional.
     * @return integer
     */
    public static function getInt(string $variable, string $section = "general"): int {
        $result = self::get($variable, $section);
        if ($result !== null) {
            return (int)$result;
        }
        return 0;
    }



    /**
     * Returns the Settings
     * @param string $section Optional.
     * @return array
     */
    private static function getSettings(string $section = null): array {
        $query = Query::createIf("section", "=", $section);
        return self::getSchema()->getAll($query);
    }

    /**
     * Returns all the Settings
     * @param string $section Optional.
     * @return array
     */
    public static function getAll(string $section = null): array {
        $request = self::getSettings($section);
        $result  = [];

        foreach ($request as $row) {
            if (empty($result[$row["section"]])) {
                $result[$row["section"]] = [];
            }

            $value = $row["value"];
            if ($row["type"] == SettingType::JSON) {
                $value = JSON::toCSV($row["value"]);
            }
            $result[$row["section"]][$row["variable"]] = $value;
        }

        if (!empty($section) && !empty($result[$section])) {
            return $result[$section];
        }
        return $result;
    }

    /**
     * Returns all the Settings Parsed
     * @param string $section Optional.
     * @return array
     */
    public static function getAllParsed(string $section = null): array {
        $request = self::getSettings($section);
        $result  = [];

        foreach ($request as $row) {
            if (empty($result[$row["section"]])) {
                $result[$row["section"]] = [];
            }
            $value = SettingType::parseValue($row);
            $result[$row["section"]][$row["variable"]] = $value;
        }

        if (!empty($section)) {
            return $result[$section];
        }
        return $result;
    }

    /**
     * Returns all the Settings as a flat array
     * @param string $section Optional.
     * @return array
     */
    public static function getAllFlat(string $section = null): array {
        $request = self::getAll($section);
        if (!empty($section)) {
            return $request;
        }
        
        $result  = [];
        foreach ($request as $section => $row) {
            foreach ($row as $variable => $value) {
                $result["$section-$variable"] = $value;
            }
        }
        return $result;
    }
    
    
    
    /**
     * Saves the given Settings if those are already on the DB
     * @param array $data
     * @return void
     */
    public static function save(array $data): void {
        $request = self::getSettings();
        $batch   = [];
        
        foreach ($request as $row) {
            $variable = $row["section"] . "-" . $row["variable"];
            if (isset($data[$variable])) {
                $value = $data[$variable];
                if ($row["type"] == SettingType::JSON) {
                    $value = JSON::fromCSV($value);
                }
                $batch[] = [
                    "section"      => $row["section"],
                    "variable"     => $row["variable"],
                    "value"        => $value,
                    "type"         => $row["type"],
                    "modifiedTime" => time(),
                ];
            }
        }
        
        if (!empty($batch)) {
            self::$schema->batch($batch);
        }
    }

    /**
     * Saves the given Settings of the gien Section if those are already on the DB
     * @param string $section
     * @param array  $data
     * @return void
     */
    public static function saveSection(string $section, array $data): void {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields["$section-$key"] = $value;
        }
        self::save($fields);
    }



    /**
     * Migrates the Settings
     * @param Database $db
     * @return void
     */
    public static function migrate(Database $db): void {
        if (!$db->hasTable("settings")) {
            return;
        }
        $adminData    = Admin::loadData(Admin::SettingsData, "admin");
        $internalData = Admin::loadData(Admin::SettingsData, "internal");
        $settings     = Arrays::extend($internalData, $adminData);
        $request      = $db->getAll("settings");

        $variables    = [];
        $adds         = [];
        $deletes      = [];

        // Adds Settings
        foreach ($settings as $section => $data) {
            foreach ($data as $variable => $value) {
                $found = false;
                foreach ($request as $row) {
                    if ($row["section"] == $section && $row["variable"] == $variable) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $type        = SettingType::get($value);
                    $variables[] = "{$section}_{$variable}";
                    $fields      = [
                        "section"  => $section,
                        "variable" => $variable,
                        "value"    => $type == SettingType::JSON ? JSON::encode($value) : $value,
                        "type"     => $type,
                    ];
                    $adds[]      = $fields;
                    $request[]   = $fields;
                }
            }
        }

        // Removes Settings
        foreach ($request as $row) {
            $found = false;
            foreach ($settings as $section => $data) {
                foreach ($data as $variable => $value) {
                    if ($row["section"] == $section && $row["variable"] == $variable) {
                        $found = true;
                        break 2;
                    }
                }
            }
            if (!$found) {
                $deletes[] = [ $row["section"], $row["variable"] ];
            }
        }

        // Process the SQL
        if (!empty($adds)) {
            print("<br>Added <i>" . count($adds) . " settings</i><br>");
            print(Strings::join($variables, ", ") . "<br>");
            $db->batch("settings", $adds);
        }
        if (!empty($deletes)) {
            print("<br>Deleted <i>" . count($deletes) . " settings</i><br>");
            $variables = [];
            foreach ($deletes as $row) {
                $query = Query::create("section", "=", $row[0])->add("variable", "=", $row[1]);
                $db->delete("settings", $query);
                $variables[] = $row[0] . "_" . $row[1];
            }
            print(Strings::join($variables, ", ") . "<br>");
        }
        if (empty($adds) && empty($deletes)) {
            print("<br>No <i>settings</i> added or deleted<br>");
        }
    }
}
