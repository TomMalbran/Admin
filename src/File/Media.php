<?php
namespace Admin\File;

use Admin\File\Path;
use Admin\File\File;
use Admin\File\FileType;
use Admin\Utils\Strings;

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
        switch ($dir) {
        case Path::Thumb:
            return [ 150, 150 ];
        case Path::Small:
            return [ 300, 300 ];
        case Path::Medium:
            return [ 700, 700 ];
        case Path::Large:
            return [ 1200, 1200 ];
        }
        return [];
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
