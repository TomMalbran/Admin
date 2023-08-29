<?php
namespace Admin\Schema;

use Admin\Admin;
use Admin\Config\Config;
use Admin\View\Contact;
use Admin\Schema\Database;
use Admin\Schema\Schema;
use Admin\Schema\Structure;
use Admin\Schema\SubRequest;

/**
 * The Schema Factory
 */
class Factory {

    private static bool      $loaded     = false;
    private static ?Database $db         = null;

    /** @var mixed[] */
    private static array     $data       = [];

    /** @var Structure[] */
    private static array     $structures = [];

    /** @var Schema[] */
    private static array     $schemas    = [];


    /**
     * Loads the Schemas Data
     * @return boolean
     */
    private static function load(): bool {
        if (self::$loaded) {
            return false;
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
            if ($key == "contacts") {
                if (!Admin::hasContact()) {
                    continue;
                }
                $data["fields"] = Contact::getFields($data["fields"]);
            }
            if (empty($adminData[$key])) {
                self::$data[$key] = $data;
            }
        }
        return true;
    }



    /**
     * Gets the Database
     * @return Database
     */
    public static function getDatabase(): Database {
        self::load();
        return self::$db;
    }

    /**
     * Gets the Schemas
     * @return mixed[]
     */
    public static function getSchemas(): array {
        self::load();
        return self::$data;
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
            $subRequest = self::getSubRequest($key);
            self::$schemas[$key] = new Schema(self::$db, $structure, $subRequest);
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
            self::$structures[$key] = new Structure(self::$data[$key]);
        }
        return self::$structures[$key];
    }

    /**
     * Creates and Returns the Subrequests for the given Key
     * @param string $key
     * @return SubRequest[]
     */
    public static function getSubRequest(string $key): array {
        $data   = self::$data[$key];
        $result = [];

        if (!empty($data["subrequests"])) {
            foreach ($data["subrequests"] as $subKey => $subData) {
                $structure    = self::getStructure($key);
                $subStructure = self::getStructure($subKey);
                $subSchema    = new Schema(self::$db, $subStructure);
                $result[]     = new SubRequest($subSchema, $structure, $subData);
            }
        }
        return $result;
    }
}
