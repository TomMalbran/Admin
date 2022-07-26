<?php
namespace Admin\Schema;

use Admin\Admin;
use Admin\Config\Config;
use Admin\Schema\Database;
use Admin\Schema\Schema;
use Admin\Schema\Structure;
use Admin\Schema\Subrequest;
use Admin\Schema\Migration;

/**
 * The Schema Factory
 */
class Factory {

    private static $loaded     = false;
    private static $db         = null;
    private static $data       = [];
    private static $structures = [];
    private static $schemas    = [];


    /**
     * Loads the Schemas Data
     * @return void
     */
    private static function load(): void {
        if (self::$loaded) {
            return;
        }
        $config       = Config::get("db");
        $adminData    = Admin::loadData(Admin::SchemaData, "admin");
        $internalData = Admin::loadData(Admin::SchemaData, "internal");

        self::$loaded = true;
        self::$db     = new Database($config);

        foreach ($adminData as $key => $data) {
            self::$data[$key] = $data;
        }
        foreach ($internalData as $key => $data) {
            if ($key == "slides" && !Admin::hasSlides()) {
                continue;
            }
            if ($key == "contacts" && !Admin::hasContact()) {
                continue;
            }
            if (empty($adminData[$key])) {
                self::$data[$key] = $data;
            }
        }
    }



    /**
     * Gets the Schema
     * @param string $key
     * @return Schema
     */
    public static function getSchema(string $key): Schema {
        self::load();
        if (empty(self::$data[$key])) {
            return null;
        }
        if (empty(self::$schemas[$key])) {
            $structure  = self::getStructure($key);
            $subrequest = self::getSubrequest($key);
            self::$schemas[$key] = new Schema(self::$db, $structure, $subrequest);
        }
        return self::$schemas[$key];
    }

    /**
     * Creates and Returns the Structure for the given Key
     * @param string $key
     * @return Structure
     */
    public static function getStructure(string $key): Structure {
        self::load();
        if (empty(self::$structures[$key])) {
            self::$structures[$key] = new Structure($key, self::$data[$key]);
        }
        return self::$structures[$key];
    }

    /**
     * Creates and Returns the Subrequests for the given Key
     * @param string $key
     * @return array
     */
    public static function getSubrequest(string $key): array {
        $data   = self::$data[$key];
        $result = [];

        if (!empty($data["subrequests"])) {
            foreach ($data["subrequests"] as $subKey => $subData) {
                $structure    = self::getStructure($key);
                $subStructure = self::getStructure($subKey);
                $subSchema    = new Schema(self::$db, $subStructure);
                $result[]     = new Subrequest($subSchema, $structure, $subData);
            }
        }
        return $result;
    }



    /**
     * Performs a Migration on the Schema
     * @param Database $db        Optional.
     * @param boolean  $canDelete Optional.
     * @return void
     */
    public static function migrate(Database $db = null, bool $canDelete = false): void {
        self::load();
        $database = $db !== null ? $db : self::$db;
        Migration::migrate($database, self::$data, $canDelete);
    }
}
