<?php
namespace Admin\File;

use Admin\File\Image;
use Admin\File\FileType;

/**
 * The FileList wrapper
 */
class FileList {

    /** @var mixed[] */
    private array $list = [];


    /**
     * Adds a File/Directory
     * @param string  $name
     * @param string  $path
     * @param boolean $isDir
     * @param string  $source
     * @param string  $thumb
     * @return FileList
     */
    public function add(string $name, string $path, bool $isDir, string $source, string $thumb): FileList {
        $isImage   = !$isDir && FileType::isImage($name);
        $imgWidth  = 0;
        $imgHeight = 0;
        if ($isImage) {
            [ $imgWidth, $imgHeight ] = Image::getSize($source);
        }
        $this->list[] = [
            "name"          => $name,
            "path"          => $path,
            "canSelect"     => !$isDir,
            "isBack"        => false,
            "isDir"         => $isDir,
            "isImage"       => $isImage,
            "isTransparent" => $isImage && FileType::isPNG($name),
            "isFile"        => !$isImage,
            "icon"          => $isDir ? "directory" : FileType::getIcon($name),
            "source"        => $source,
            "url"           => $source,
            "thumb"         => $thumb,
            "width"         => $imgWidth,
            "height"        => $imgHeight,
        ];
        return $this;
    }

    /**
     * Adds the Go Back element
     * @param string $path
     * @return FileList
     */
    public function addBack(string $path): FileList {
        $dir = dirname($path);
        $this->list[] = [
            "name"      => "...",
            "path"      => $dir != "." ? $dir : "",
            "canSelect" => false,
            "isBack"    => true,
            "isFile"    => true,
            "icon"      => "back",
        ];
        return $this;
    }



    /**
     * Returns the List
     * @return mixed[]
     */
    public function get(): array {
        return $this->list;
    }

    /**
     * Sorts and returns the List
     * @return mixed[]
     */
    public function getSorted(): array {
        usort($this->list, function ($a, $b) {
            // Back goes first
            if ($a["isBack"] && !$b["isBack"]) {
                return -1;
            }
            if (!$a["isBack"] && $b["isBack"]) {
                return 1;
            }

            // Directories go on top
            if ($a["isDir"] && !$b["isDir"]) {
                return -1;
            }
            if (!$a["isDir"] && $b["isDir"]) {
                return 1;
            }

            // If the type is the same sort by name
            return strnatcasecmp($a["name"], $b["name"]);
        });

        return $this->list;
    }
}
