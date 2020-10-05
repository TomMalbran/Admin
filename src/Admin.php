<?php
namespace Admin;

use Admin\File\File;
use Admin\Schema\Factory;
use Admin\Schema\Database;
use Admin\Utils\JSON;

/**
 * The Admin Service
 */
class Admin {

    // The Data
    const SchemaData    = "schemas";
    const KeyData       = "keys";

    // The Directories
    const SourceDir     = "src";
    const DataDir       = "data";
    
    const PublicDir     = "public";
    const TemplatesDir  = "templates";
    const PartialsDir   = "partials";
    const MigrationsDir = "migrations";

    const BaseDir       = "admin";
    const FilesDir      = "files";
    const TempDir       = "temp";

    // Config
    const Namespace     = "App\\Controller\\";

    // Variables
    private static $adminPath;
    private static $basePath;


    /**
     * Sets the Basic data
     * @param string $basePath
     * @return void
     */
    public static function create(string $basePath): void {
        self::$adminPath = dirname(__FILE__, 2);
        self::$basePath  = $basePath;
    }



    /**
     * Returns the BasePath with the given dir
     * @param string  $dir      Optional.
     * @param string  $file     Optional.
     * @param boolean $forAdmin Optional.
     * @return string
     */
    public static function getPath(string $dir = "", string $file = "", bool $forAdmin = false): string {
        $path = "";
        if ($forAdmin) {
            $path = File::getPath(self::$adminPath, $dir, $file);
        } else {
            $path = File::getPath(self::$basePath, self::BaseDir, $dir, $file);
        }
        return File::removeLastSlash($path);
    }

    /**
     * Returns the FilesPath with the given file
     * @param string $file Optional.
     * @return string
     */
    public static function getFilesPath(string $file = ""): string {
        $path = File::getPath(self::$basePath, self::FilesDir, $file);
        return File::removeLastSlash($path);
    }

    /**
     * Loads a File from the App or defaults to the Admin
     * @param string $dir
     * @param string $file
     * @return string
     */
    public static function loadFile(string $dir, string $file) {
        $path   = self::getPath($dir, $file, false);
        $result = "";
        if (File::exists($path)) {
            $result = file_get_contents($path);
        }
        if (empty($result)) {
            $path   = self::getPath($dir, $file, true);
            $result = file_get_contents($path);
        }
        return $result;
    }

    /**
     * Loads a JSON File
     * @param string  $dir
     * @param string  $file
     * @param boolean $forAdmin Optional.
     * @return array
     */
    public static function loadJSON(string $dir, string $file, bool $forAdmin = false): array {
        $path = self::getPath($dir, "$file.json", $forAdmin);
        return JSON::readFile($path, true);
    }

    /**
     * Loads a Data File
     * @param string $file
     * @return array
     */
    public static function loadData(string $file): array {
        return self::loadJSON(self::DataDir, $file);
    }

    /**
     * Saves a Data File
     * @param string $file
     * @param mixed  $contents
     * @return void
     */
    public function saveData(string $file, $contents): void {
        $path = self::getPath(self::DataDir, "$file.json");
        JSON::writeFile($path, $contents);
    }



    /**
     * Runs the Migrations for all the Admin
     * @param Database $db
     * @param boolean  $canDelete Optional.
     * @param boolean  $recreate  Optional.
     * @param boolean  $sandbox   Optional.
     * @return void
     */
    public static function migrate(Database $db, bool $canDelete = false, bool $recreate = false, bool $sandbox = false): void {
        Factory::migrate($db, $canDelete);
    }
}
