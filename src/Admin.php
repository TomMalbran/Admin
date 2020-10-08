<?php
namespace Admin;

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
     * @param string $basePath
     * @return void
     */
    public static function create(string $basePath): void {
        self::$adminPath = dirname(__FILE__, 2);
        self::$basePath  = $basePath;
    }



    /**
     * Returns the Base Path with the given dir
     * @param string  $dir     Optional.
     * @param boolean $forSite Optional.
     * @return string
     */
    public static function getPath(string $dir = "", bool $forSite = false): string {
        $path = "";
        if ($forSite) {
            $path = File::getPath(self::$basePath, $dir);
        } else {
            $path = File::getPath(self::$basePath, self::AdminDir, $dir);
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
     * @param boolean $forSite Optional.
     * @return array
     */
    public static function loadJSON(string $dir, string $file, bool $forSite = false): array {
        $path = self::getPath("$dir/$file.json", $forSite);
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
        Path::ensurePaths();
    }
}
