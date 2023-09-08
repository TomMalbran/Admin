<?php
namespace Admin\File;

use Admin\File\Path;
use Admin\File\File;
use Admin\File\FileType;

/**
 * The Media Utils
 */
class Media {

    /**
     * Returns the Image Size depending on the given Directory
     * @param string $dir
     * @return integer[]
     */
    public static function getImageSize(string $dir): array {
        return match ($dir) {
            Path::Thumb  => [  150,  150 ],
            Path::Small  => [  300,  300 ],
            Path::Medium => [  700,  700 ],
            Path::Large  => [ 1200, 1200 ],
            default      => [],
        };
    }



    /**
     * Creates a Directory
     * @param string $path
     * @param string $name
     * @return boolean
     */
    public static function create(string $path, string $name): bool {
        $baseDirs = Path::getBaseDirs();
        foreach ($baseDirs as $baseDir) {
            $dirPath = Path::getPath($baseDir, $path, $name);
            if (!File::createDir($dirPath)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Uploads a File
     * @param string $directory
     * @param string $fileName
     * @param string $tmpFile
     * @return string
     */
    public static function upload(string $directory, string $fileName, string $tmpFile): string {
        $source  = Path::getPath(Path::Source, $directory);
        $path    = File::upload($source, $fileName, $tmpFile);
        $relPath = Path::getRelPath($path);

        if (!File::exists($path)) {
            return null;
        }
        if (!FileType::isImage($fileName)) {
            return $relPath;
        }
        if (self::resizeImage($relPath, true)) {
            return $relPath;
        }
        return null;
    }

    /**
     * Renames a Media Element
     * @param string $path
     * @param string $oldName
     * @param string $newName
     * @return boolean
     */
    public static function rename(string $path, string $oldName, string $newName): bool {
        $baseDirs = Path::getBaseDirs();
        foreach ($baseDirs as $baseDir) {
            $oldDir = Path::getPath($baseDir, $path, $oldName);
            $newDir = Path::getPath($baseDir, $path, $newName);
            if (!File::move($oldDir, $newDir)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Moves a Media Element
     * @param string $oldPath
     * @param string $newPath
     * @param string $name
     * @return boolean
     */
    public static function move(string $oldPath, string $newPath, string $name): bool {
        $baseDirs = Path::getBaseDirs();
        foreach ($baseDirs as $baseDir) {
            $oldDir = Path::getPath($baseDir, $oldPath, $name);
            $newDir = Path::getPath($baseDir, $newPath, $name);
            if (!File::move($oldDir, $newDir)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Deletes a Media Element
     * @param string $path
     * @param string $name
     * @return boolean
     */
    public static function delete(string $path, string $name): bool {
        $baseDirs = Path::getBaseDirs();
        foreach ($baseDirs as $baseDir) {
            $delPath = Path::getPath($baseDir, $path, $name);
            if (!File::deleteDir($delPath)) {
                return false;
            }
        }
        return true;
    }



    /**
     * Returns all the Images to resize
     * @return mixed[]
     */
    public static function getAllToResize(): array {
        $baseDirs = Path::getBaseDirs();
        $basePath = Path::getPath(Path::Source);
        $files    = File::getFilesInDir($basePath, true);
        $result   = [];

        foreach ($files as $file) {
            if (!FileType::isImage($file)) {
                continue;
            }
            $name = str_replace($basePath, "", $file);
            $path = str_replace($basePath, "", $file);
            foreach ($baseDirs as $baseDir) {
                if (FileType::isImage($name)) {
                    $dest = Path::getPath($baseDir, $name);
                    $size = self::getImageSize($baseDir);
                    if (!empty($size) && !File::exists($dest)) {
                        $result[] = [
                            "path" => $path,
                            "name" => $name,
                        ];
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Resizes a single Media Image
     * @param string  $path
     * @param boolean $forceResize Optional.
     * @return boolean
     */
    public static function resizeImage(string $path, bool $forceResize = true): bool {
        $baseDirs = Path::getBaseDirs();
        $source   = Path::getPath(Path::Source, $path);

        foreach ($baseDirs as $baseDir) {
            $size = self::getImageSize($baseDir);
            $dest = Path::getPath($baseDir, $path);
            if (!empty($size) && ($forceResize || !File::exists($dest))) {
                $basePath = Path::getPath($baseDir);
                File::ensureFileDir($basePath, $dest);

                if (FileType::isICO($path)) {
                    if (!File::copy($source, $dest)) {
                        return false;
                    }
                } else {
                    if (!Image::resize($source, $dest, $size[0], $size[1], Image::Maximum)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}
