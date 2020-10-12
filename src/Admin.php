<?php
namespace Admin;

use Admin\Log\ErrorLog;
use Admin\File\File;
use Admin\File\Path;
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
    const PathData      = "paths";
    const ActionData    = "actions";

    // The Directories
    const AdminDir      = "admin";
    const SourceDir     = "src";
    const DataDir       = "data";
    const FilesDir      = "files";
    
    const PublicDir     = "public";
    const TemplatesDir  = "templates";
    const PartialsDir   = "partials";
    const MigrationsDir = "migrations";

    // Config
    const Namespace     = "App\\Controller\\";

    // Variables
    private static $adminPath;
    private static $basePath;


    /**
     * Sets the Basic data
     * @param string  $basePath
     * @param boolean $logErrors Optional.
     * @return void
     */
    public static function create(string $basePath, bool $logErrors = false): void {
        self::$adminPath = dirname(__FILE__, 2);
        self::$basePath  = $basePath;

        if ($logErrors) {
            ErrorLog::init();
        }
    }



    /**
     * Returns the Base Path with the given dir
     * @param string $dir  Optional.
     * @param string $type Optional.
     * @return string
     */
    public static function getPath(string $dir = "", string $type = "admin"): string {
        $path = "";
        switch ($type) {
        case "admin":
            $path = File::getPath(self::$basePath, self::AdminDir, $dir);
            break;
        case "site":
            $path = File::getPath(self::$basePath, $dir);
            break;
        case "internal":
            $path = File::getPath(self::$adminPath, $dir);
            break;
        }
        return File::removeLastSlash($path);
    }

    /**
     * Returns the Files Path with the given file
     * @param string $file Optional.
     * @return string
     */
    public static function getFilesPath(string $file = ""): string {
        $path = File::getPath(self::$basePath, self::AdminDir, self::FilesDir, $file);
        return File::removeLastSlash($path);
    }

    /**
     * Returns the Files Relative Path with the given file
     * @param string $file Optional.
     * @return string
     */
    public static function getFilesRelPath(string $file = ""): string {
        $path = File::getPath(self::AdminDir, self::FilesDir, $file);
        return File::removeLastSlash($path);
    }

    /**
     * Loads a File from the App or defaults to the Admin
     * @param string $dir
     * @param string $file
     * @return string
     */
    public static function loadFile(string $dir, string $file) {
        $path   = self::getPath($dir, $file, "admin");
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
     * @param boolean $forSite Optional.
     * @return array
     */
    public static function loadJSON(string $dir, string $file, bool $forSite = false): array {
        $path = self::getPath("$dir/$file.json", $forSite ? "site" : "admin");
        return JSON::readFile($path, true);
    }

    /**
     * Loads a Data File
     * @param string $file
     * @param string $type Optional.
     * @return array
     */
    public static function loadData(string $file, string $type = "admin"): array {
        $path = self::getPath(self::DataDir . "/$file.json", $type);
        return JSON::readFile($path, true);
    }

    /**
     * Saves a Data File
     * @param string $file
     * @param mixed  $contents
     * @param string $type     Optional.
     * @return void
     */
    public function saveData(string $file, $contents, string $type = "admin"): void {
        $path = self::getPath(self::DataDir . "/$file.json", $type);
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
        Path::ensurePaths();
    }
}
