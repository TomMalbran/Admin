<?php
namespace Admin\File;

use Admin\Admin;
use Admin\Config\Config;
use Admin\File\File;
use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * The Files Paths
 */
class Path {

    const Source = "source";
    const Large  = "large";
    const Medium = "medium";
    const Small  = "small";
    const Thumb  = "thumb";
    const Temp   = "temp";

    private static bool   $loaded   = false;
    private static string $basePath = "";
    private static string $relPath  = "";

    /** @var mixed[] */
    private static array  $paths    = [];


    /**
     * Loads the Path Data
     * @return boolean
     */
    private static function load(): bool {
        if (self::$loaded) {
            return false;
        }

        self::$loaded   = true;
        self::$basePath = Admin::getFilesPath();
        self::$relPath  = Admin::getFilesRelPath();
        self::$paths    = Admin::loadData(Admin::PathData);

        $sections = Admin::getSections();
        foreach ($sections as $section) {
            if (!empty($section["path"])) {
                self::$paths[] = $section["path"];
            }
        }
        if (Admin::hasSlides()) {
            self::$paths[] = "banners";
        }
        return true;
    }

    /**
     * Returns a list with the Base Media Directories
     * @return string[]
     */
    public static function getBaseDirs(): array {
        return [
            self::Source,
            self::Large,
            self::Medium,
            self::Small,
            self::Thumb,
        ];
    }

    /**
     * Returns and loads the Directories
     * @return mixed[]
     */
    public static function getDirectories(): array {
        self::load();
        if (!empty(self::$paths)) {
            return self::$paths;
        }
        return [];
    }



    /**
     * Returns the path used to store the files
     * @param string ...$pathParts
     * @return string
     */
    public static function getPath(string ...$pathParts): string {
        self::load();
        return File::getPath(self::$basePath, ...$pathParts);
    }

    /**
     * Returns the Relative Path from Source
     * @param string $path
     * @return string
     */
    public static function getRelPath(string $path): string {
        $basePath = self::getPath(self::Source);
        $relPath  = Strings::replace($path, $basePath, "");
        return File::removeFirstSlash($relPath);
    }

    /**
     * Returns the path to be used in urls
     * @param string ...$pathParts
     * @return string
     */
    public static function getUrl(string ...$pathParts): string {
        self::load();
        return Config::getUrl(self::$relPath, ...$pathParts);
    }

    /**
     * Returns true if rhe path exists
     * @param string ...$pathParts
     * @return boolean
     */
    public static function exists(string ...$pathParts): bool {
        self::load();
        return File::exists(self::$basePath, ...$pathParts);
    }



    /**
     * Returns the path used to store the temp files
     * @param integer $credentialID
     * @param boolean $create
     * @return string
     */
    public static function getTempPath(int $credentialID, bool $create = true): string {
        $path   = self::getPath(self::Temp, $credentialID);
        $exists = File::exists($path);

        if (!$exists && $create) {
            File::createDir($path);
            return $path;
        }
        return $exists ? $path : "";
    }

    /**
     * Creates an url to the files temp directory
     * @param integer $credentialID
     * @return string
     */
    public static function getTempUrl(int $credentialID): string {
        return self::getPath(self::Temp, $credentialID) . "/";
    }



    /**
     * Ensures that the Paths are created
     * @return boolean
     */
    public static function ensurePaths(): bool {
        $baseDirs    = self::getBaseDirs();
        $directories = self::getDirectories();
        $basePath    = self::getPath();
        $paths       = [];

        foreach ($baseDirs as $baseDir) {
            foreach ($directories as $directory) {
                if (File::ensureDir($basePath, $baseDir, $directory)) {
                    if (!Arrays::contains($paths, $directory)) {
                        $paths[] = $directory;
                    }
                }
            }
        }

        $tempPath = self::getPath(self::Temp);
        if (!File::exists($tempPath)) {
            File::createDir($tempPath);
        }

        if (!empty($paths)) {
            print("<br>Added <i>" . count($paths) . " paths</i><br>");
            print(Strings::join($paths, ", ") . "<br>");
        } else {
            print("<br>No <i>paths</i> added<br>");
        }
        return true;
    }
}
