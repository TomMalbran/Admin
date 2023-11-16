<?php
namespace Admin\Config;

use Admin\Admin;
use Admin\Config\SettingType;
use Admin\Schema\Factory;
use Admin\Schema\Schema;
use Admin\Schema\Query;
use Admin\File\Path;
use Admin\File\FileType;
use Admin\Utils\Arrays;
use Admin\Utils\CSV;
use Admin\Utils\JSON;
use Admin\Utils\Numbers;
use Admin\Utils\Strings;

/**
 * The Settings Data
 */
class Settings {

    /**
     * Loads the Settings Schemas
     * @return Schema
     */
    private static function schema(): Schema {
        return Factory::getSchema("settings");
    }



    /**
     * Returns a single Setting
     * @param string $section
     * @param string $variable
     * @return mixed|null
     */
    public static function get(string $section, string $variable): mixed {
        $query = Query::create("section", "=", $section);
        $query->add("variable", "=", $variable);
        $model = self::schema()->getOne($query);
        if (!$model->isEmpty()) {
            return SettingType::parseValue($model);
        }
        return null;
    }

    /**
     * Returns a single Setting as an Integer
     * @param string $section
     * @param string $variable
     * @return integer
     */
    public static function getInt(string $section, string $variable): int {
        $result = self::get($section, $variable);
        if ($result !== null) {
            return (int)$result;
        }
        return 0;
    }



    /**
     * Returns the Settings
     * @param string $section Optional.
     * @return mixed[]
     */
    private static function getSettings(string $section = ""): array {
        $query = Query::createIf("section", "=", $section);
        return self::schema()->getAll($query);
    }

    /**
     * Returns all the Settings
     * @param string $section Optional.
     * @return mixed[]
     */
    public static function getAll(string $section = ""): array {
        $request = self::getSettings($section);
        $result  = [];

        foreach ($request as $row) {
            if (empty($result[$row["section"]])) {
                $result[$row["section"]] = [];
            }

            $value = $row["value"];
            if ($row["type"] == SettingType::JSON) {
                $value = CSV::encode($row["value"]);
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
     * @return mixed[]
     */
    public static function getAllParsed(string $section = ""): array {
        $request = self::getSettings($section);
        $result  = [];

        foreach ($request as $row) {
            $section  = $row["section"];
            $variable = $row["variable"];
            $value    = $row["value"];

            if (empty($result[$section])) {
                $result[$section] = [];
            }
            if ($row["type"] == SettingType::General) {
                $result[$section][$variable] = $value;
                if (!empty($value) && FileType::isImage($value)) {
                    $result[$section]["{$variable}Url"]    = Path::getUrl(Path::Source, $value);
                    $result[$section]["{$variable}Medium"] = Path::getUrl(Path::Medium, $value);
                    $result[$section]["{$variable}Large"]  = Path::getUrl(Path::Large,  $value);
                } elseif (!empty($value) && FileType::isVideo($value)) {
                    $result[$section]["{$variable}Url"]    = Path::getUrl(Path::Source, $value);
                } elseif (!Numbers::isValid($value)) {
                    $result[$section]["{$variable}Html"]   = Strings::toHtml($value);
                }
            } else {
                $result[$section][$variable] = SettingType::parseValue($row);
            }
        }

        if (!empty($section) && !empty($result[$section])) {
            return $result[$section];
        }
        return $result;
    }

    /**
     * Returns all the Settings as a flat array
     * @param string $section Optional.
     * @return mixed[]
     */
    public static function getAllFlat(string $section = ""): array {
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
     * Sets the given Settings
     * @param string $section
     * @param string $variable
     * @param mixed  $value
     * @return boolean
     */
    public static function set(string $section, string $variable, mixed $value): bool {
        $query = Query::create("section", "=", $section);
        $query->add("variable", "=", $variable);
        return self::schema()->edit($query, [
            "value" => $value,
        ]);
    }

    /**
     * Saves the given Settings if those are already on the DB
     * @param array{} $data
     * @return boolean
     */
    public static function save(array $data): bool {
        $settings = self::getSettings();
        $batch    = [];

        foreach ($settings as $setting) {
            $variable = $setting["section"] . "-" . $setting["variable"];
            if (isset($data[$variable])) {
                $value = $data[$variable];
                if ($setting["type"] == SettingType::JSON) {
                    $value = JSON::fromCSV($value);
                }

                $batch[] = [
                    "section"        => $setting["section"],
                    "variable"       => $setting["variable"],
                    "value"          => $value,
                    "type"           => $setting["type"],
                    "forPersonalize" => 0,
                    "modifiedTime"   => time(),
                ];
            }
        }

        if (!empty($batch)) {
            self::schema()->batch($batch);
            return true;
        }
        return false;
    }

    /**
     * Saves the given Settings of the given Section if those are already on the DB
     * @param string  $section
     * @param array{} $data
     * @return boolean
     */
    public static function saveSection(string $section, array $data): bool {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields["$section-$key"] = $value;
        }
        return self::save($fields);
    }

    /**
     * Saves the given Settings if those are already on the DB
     * @param array{} $settings
     * @param array{} $data
     * @return boolean
     */
    public static function savePersonalize(array $settings, array $data): bool {
        $query = Query::create("forPersonalize", "=", 1);
        self::schema()->remove($query);

        $batch = [];
        foreach ($settings as $key => $defaultValue) {
            [ $section, $variable ] = Strings::split($key, "-");

            $type  = SettingType::get($defaultValue);
            $value = $data[$key] ?? $defaultValue;
            if ($type == SettingType::JSON) {
                $value = JSON::fromCSV($value);
            }

            $batch[] = [
                "section"        => $section,
                "variable"       => $variable,
                "value"          => $value,
                "type"           => $type,
                "forPersonalize" => 1,
                "modifiedTime"   => time(),
            ];
        }

        if (!empty($batch)) {
            self::schema()->batch($batch);
            return true;
        }
        return false;
    }



    /**
     * Migrates the Settings
     * @return boolean
     */
    public static function migrate(): bool {
        $db           = Factory::getDatabase();
        $adminData    = Admin::loadData(Admin::SettingsData, "admin");
        $internalData = Admin::loadData(Admin::SettingsData, "internal");
        $settings     = Arrays::extend($internalData, $adminData);
        $query        = Query::create("forPersonalize", "=", 0);
        $request      = $db->getAll("settings", "*", $query);

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
                        "section"        => $section,
                        "variable"       => $variable,
                        "value"          => $type == SettingType::JSON ? JSON::encode($value) : $value,
                        "type"           => $type,
                        "forPersonalize" => 0,
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
        return true;
    }
}
