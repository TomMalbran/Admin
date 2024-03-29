<?php
namespace Admin\File;

use Admin\File\FileType;

/**
 * The Media Types used by the System
 */
class MediaType {

    const Any   = "";
    const Media = "media";
    const Image = "image";
    const Video = "video";
    const Audio = "audio";
    const PDF   = "pdf";
    const SVG   = "svg";
    const File  = "file";


    /**
     * Returns true if the given file is a Directory
     * @param string $type
     * @param string $file
     * @param string $name
     * @return boolean
     */
    public static function isValid(string $type, string $file, string $name): bool {
        if ($type == self::Any && !FileType::isHidden($name)) {
            return true;
        }
        if ($type == self::Media && (FileType::isImage($name) || FileType::isVideo($name))) {
            return true;
        }
        if ($type == self::Image && FileType::isImage($name)) {
            return true;
        }
        if ($type == self::Video && FileType::isVideo($name)) {
            return true;
        }
        if ($type == self::Audio && FileType::isAudio($name)) {
            return true;
        }
        if ($type == self::PDF && FileType::isPDF($name)) {
            return true;
        }
        if ($type == self::SVG && FileType::isSVG($name)) {
            return true;
        }
        if ($type == self::File) {
            return true;
        }
        if (FileType::isDir($file)) {
            return true;
        }
        return false;
    }
}
