<?php
namespace Admin\File;

use Admin\Utils\Arrays;
use Admin\Utils\Strings;

use ZipArchive;

/**
 * The File Utils
 */
class File {

    /**
     * Returns the path used to store the files
     * @param string ...$pathParts
     * @return string
     */
    public static function getPath(string ...$pathParts): string {
        $result = Strings::join($pathParts, "/");
        $result = Strings::replace($result, "//", "/");
        return $result;
    }

    /**
     * Returns true if given file exists
     * @param string ...$pathParts
     * @return boolean
     */
    public static function exists(string ...$pathParts): bool {
        $path = self::getPath(...$pathParts);
        return !empty($path) && file_exists($path);
    }



    /**
     * Uploads the given file to the given path
     * @param string $path
     * @param string $fileName
     * @param string $tmpFile
     * @return string
     */
    public static function upload(string $path, string $fileName, string $tmpFile): string {
        $path = self::getPath($path, $fileName);
        if (!empty($path)) {
            move_uploaded_file($tmpFile, $path);
        }
        return $path;
    }

    /**
     * Creates a file with the given content
     * @param string          $path
     * @param string          $fileName
     * @param string[]|string $content
     * @return string
     */
    public static function create(string $path, string $fileName, array|string $content): string {
        $path = self::getPath($path, $fileName);
        if (!empty($path)) {
            file_put_contents($path, Strings::join($content, "\n"));
        }
        return $path;
    }

    /**
     * Moves a file from one path to another
     * @param string $fromPath
     * @param string $toPath
     * @return boolean
     */
    public static function move(string $fromPath, string $toPath): bool {
        if (!empty($fromPath) && !empty($toPath)) {
            rename($fromPath, $toPath);
            return true;
        }
        return false;
    }

    /**
     * Copies a file from one path to another
     * @param string $fromPath
     * @param string $toPath
     * @return boolean
     */
    public static function copy(string $fromPath, string $toPath): bool {
        if (!empty($fromPath) && !empty($toPath)) {
            copy($fromPath, $toPath);
            return true;
        }
        return false;
    }

    /**
     * Deletes the given file/directory
     * @param string ...$pathParts
     * @return boolean
     */
    public static function delete(string ...$pathParts): bool {
        $path = self::getPath(...$pathParts);
        if (!empty($path) && file_exists($path)) {
            unlink($path);
            return true;
        }
        return false;
    }

    /**
     * Deletes the given file/directory
     * @param string ...$pathParts
     * @return string
     */
    public static function read(string ...$pathParts): string {
        $path = self::getPath(...$pathParts);
        if (!empty($path) && file_exists($path)) {
            return file_get_contents($path);
        }
        return "";
    }



    /**
     * Adds the last slash for dir processing functions
     * @param string $path
     * @return string
     */
    public static function addLastSlash(string $path): string {
        if (!Strings::endsWith($path, "/")) {
            return "$path/";
        }
        return $path;
    }

    /**
     * Adds the first slash for dir processing functions
     * @param string $path
     * @return string
     */
    public static function addFirstSlash(string $path): string {
        if (!Strings::startsWith($path, "/")) {
            return "/$path";
        }
        return $path;
    }

    /**
     * Removes the last slash for dir processing functions
     * @param string $path
     * @return string
     */
    public static function removeLastSlash(string $path): string {
        return Strings::stripEnd($path, "/");
    }

    /**
     * Removes the first slash for dir processing functions
     * @param string $path
     * @return string
     */
    public static function removeFirstSlash(string $path): string {
        return Strings::stripStart($path, "/");
    }



    /**
     * Returns the directory component of the path
     * @param string $path
     * @return string
     */
    public static function getDirName(string $path): string {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    /**
     * Returns the file name component of the path
     * @param string $path
     * @return string
     */
    public static function getBaseName(string $path): string {
        return basename($path);
    }

    /**
     * Returns the name without the extension
     * @param string $name
     * @return string
     */
    public static function getName(string $name): string {
        return pathinfo($name, PATHINFO_FILENAME);
    }

    /**
     * Returns the extension of the given name
     * @param string $name
     * @return string
     */
    public static function getExtension(string $name): string {
        return pathinfo($name, PATHINFO_EXTENSION);
    }

    /**
     * Returns true if the file has the given extension
     * @param string          $name
     * @param string[]|string $extensions
     * @return boolean
     */
    public static function hasExtension(string $name, array|string $extensions): bool {
        $extension = self::getExtension($name);
        $extension = Strings::toLowerCase($extension);
        return Arrays::contains($extensions, $extension);
    }

    /**
     * Returns the new name of the given file using the old extension
     * @param string $newName
     * @param string $oldName
     * @return string
     */
    public static function parseName(string $newName, string $oldName): string {
        $newExt = self::getExtension($newName);
        $oldExt = self::getExtension($oldName);
        if (empty($newExt) && !empty($oldExt)) {
            return "{$newName}.{$oldExt}";
        }
        return $newName;
    }



    /**
     * Returns all the Files and Directories inside the given path
     * @param string $path
     * @return string[]
     */
    public static function getAllInDir(string $path): array {
        $result = [];
        if (!file_exists($path) || !is_dir($path)) {
            return $result;
        }

        $files = scandir($path);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $result[] = self::getPath($path, $file);
            }
        }
        return $result;
    }

    /**
     * Returns all the Files inside the given path
     * @param string $path
     * @return string[]
     */
    public static function getFilesInDir(string $path): array {
        $result = [];
        if (empty($path)) {
            return $result;
        }
        if (is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    $response = self::getFilesInDir("$path/$file");
                    $result   = array_merge($result, $response);
                }
            }
        } else {
            $result[] = $path;
        }
        return $result;
    }

    /**
     * Creates a directory at the given path if it doesn't exists
     * @param string ...$pathParts
     * @return boolean
     */
    public static function createDir(string ...$pathParts): bool {
        $path = self::getPath(...$pathParts);
        if (!self::exists($path)) {
            mkdir($path, 0777, true);
            return true;
        }
        return false;
    }

    /**
     * Ensures that all the directories are created
     * @param string $basePath
     * @param string $filePath
     * @return boolean
     */
    public static function ensureFileDir(string $basePath, string $filePath): bool {
        $path = self::getDirName($filePath);
        if (self::exists($path)) {
            return false;
        }
        $fileDir = Strings::replace($path, $basePath, "");
        return self::ensureDir($basePath, $fileDir);
    }

    /**
     * Ensures that all the directories are created
     * @param string $basePath
     * @param string ...$pathParts
     * @return boolean
     */
    public static function ensureDir(string $basePath, string ...$pathParts): bool {
        $path        = self::getPath(...$pathParts);
        $pathElems   = Strings::split($path, "/");
        $partialPath = [];
        $created     = false;

        for ($i = 0; $i < count($pathElems); $i++) {
            $partialPath[] = $pathElems[$i];
            if (self::createDir($basePath, ...$partialPath)) {
                $created = true;
            }
        }
        return $created;
    }

    /**
     * Deletes a directory and it's content
     * @param string ...$pathParts
     * @return boolean
     */
    public static function deleteDir(string ...$pathParts): bool {
        $path = self::getPath(...$pathParts);
        if (is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    self::deleteDir("$path/$file");
                }
            }
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
        return !file_exists($path);
    }

    /**
     * Deletes all the content from a directory
     * @param string ...$pathParts
     * @return boolean
     */
    public static function emptyDir(string ...$pathParts): bool {
        $path = self::getPath(...$pathParts);
        if (!self::exists($path)) {
            return false;
        }
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                self::deleteDir("$path/$file");
            }
        }
        return true;
    }



    /**
     * Creates a new zip archive and adds the given files/directories
     * @param string          $name
     * @param string[]|string $files
     * @return ZipArchive|null
     */
    public static function createZip(string $name, array|string $files): ?ZipArchive {
        $zip   = new ZipArchive();
        $files = Arrays::toArray($files);

        if ($zip->open($name, ZIPARCHIVE::CREATE)) {
            foreach ($files as $file) {
                self::addDirToZip($zip, $file, pathinfo($file, PATHINFO_BASENAME));
            }
            $zip->close();
            return $zip;
        }
        return null;
    }

    /**
     * Adds a directory and all the files/directories inside or just a single file
     * @param ZipArchive $zip
     * @param string     $src
     * @param string     $dst
     * @return ZipArchive
     */
    private static function addDirToZip(ZipArchive $zip, string $src, string $dst): ZipArchive {
        if (is_dir($src)) {
            $zip->addEmptyDir($dst);
            $files = scandir($src);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    self::addDirToZip($zip, "$src/$file", "$dst/$file");
                }
            }
        } elseif (file_exists($src)) {
            $zip->addFile($src, $dst);
        }
        return $zip;
    }

    /**
     * Extracts the given zip to the given path
     * @param string $zipPath
     * @param string $extractPath
     * @return boolean
     */
    public function extractZip(string $zipPath, string $extractPath): bool {
        $zip = new ZipArchive();
        if ($zip->open($zipPath)) {
            $zip->extractTo($extractPath);
            $zip->close();
            return true;
        }
        return false;
    }
}
