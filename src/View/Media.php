<?php
namespace Admin\View;

use Admin\Admin;
use Admin\IO\View;
use Admin\IO\Request;
use Admin\IO\Response;
use Admin\IO\Url;
use Admin\File\Path;
use Admin\File\File;
use Admin\File\FileType;
use Admin\File\Media as FileMedia;
use Admin\File\MediaType;
use Admin\Schema\Field;
use Admin\Schema\Query;
use Admin\Schema\Factory;
use Admin\Utils\Strings;

/**
 * The Media View
 */
class Media {

    /**
     * Updates the Paths in the Database
     * @param string $oldPath
     * @param string $oldName
     * @param string $newPath Optional.
     * @param string $newName Optional.
     * @return boolean
     */
    private static function update(string $oldPath, string $oldName, string $newPath = "", string $newName = ""): bool {
        $db         = Factory::getDatabase();
        $schemas    = Admin::loadData(Admin::SchemaData, "admin");

        $oldRelPath = File::removeFirstSlash(File::getPath($oldPath, $oldName));
        $newRelPath = !empty($newName) ? File::removeFirstSlash(File::getPath($newPath, $newName)) : "";

        foreach ($schemas as $schema) {
            foreach ($schema["fields"] as $key => $field) {
                if ($field["type"] == Field::File || $field["type"] == Field::Image) {
                    $query = Query::create($key, "=", $oldRelPath);
                    $db->update($schema["table"], [ $key => $newRelPath ], $query);
                }
            }
        }

        $query = Query::create("value", "=", $oldRelPath);
        $db->update("settings", [ "value" => $newRelPath ], $query);
        return true;
    }



    /**
     * Creates and returns the View
     * @param string $url Optional.
     * @return View
     */
    private static function view(string $url = "media"): View {
        return new View("media", $url, "media");
    }

    /**
     * Returns a Query
     * @param Request $request
     * @return Url
     */
    private static function getQuery(Request $request): Url {
        $url = new Url();
        if ($request->has("type")) {
            $url->set("type", $request->type);
        }
        if ($request->has("selected")) {
            $url->set("selected", $request->selected);
        }
        if ($request->has("select")) {
            $url->set("select", $request->select);
        }
        return $url;
    }

    /**
     * Redirects after an Error
     * @param Request $request
     * @param string  $error
     * @param string  $success
     * @return Response
     */
    private static function redirect(Request $request, string $error, string $success): Response {
        $message = !empty($error) ? $error : $success;
        $result  = empty($error);
        $query   = self::getQuery($request);
        $url     = "media";

        if ($request->has("path")) {
            $route = Strings::fromUrl($request->path);
            $url   = "media/$route";
        }
        return self::view($url)->redirect($request, $message, $result)->withUrl($query);
    }



    /**
     * Returns the Media list view
     * @param Request $request
     * @return Response
     */
    public static function getAll(Request $request): Response {
        if ($request->has("path")) {
            return self::getOne($request->path, $request, true);
        }
        return self::getOne("", $request, true);
    }

    /**
     * Returns the Media list view
     * @param string  $path
     * @param Request $request
     * @param boolean $useSelected Optional.
     * @return Response
     */
    public static function getOne(string $path, Request $request, bool $useSelected = false): Response {
        $path     = Strings::toUrl($path);
        $type     = $request->get("type", "file");
        $selected = "";

        if ($request->has("selected") && $useSelected) {
            $selected = $request->selected;
            $pos      = strrpos($request->selected, "/");
            if ($pos !== false) {
                $path     = substr($request->selected, 0, $pos);
                $selected = substr($request->selected, $pos + 1);
            }
        }

        $path   = !empty($path) && Path::exists(Path::Source, $path) ? $path : "";
        $source = Path::getPath(Path::Source, $path);
        $source = File::addLastSlash($source);
        $files  = File::getAllInDir($source);
        $result = [];

        foreach ($files as $file) {
            $name = Strings::replace($file, $source, "");
            if (MediaType::isValid($type, $file, $name)) {
                $isDir    = FileType::isDir($file);
                $isImage  = FileType::isImage($name);
                $isFile   = !$isDir && (FileType::isFile($name) || FileType::isVideo($name));
                $result[] = [
                    "name"          => $name,
                    "route"         => "/" . Strings::fromUrl(!empty($path) ? "{$path}/{$name}" : $name),
                    "value"         => !empty($path) ? "{$path}/{$name}" : $name,
                    "canSelect"     => !$isDir,
                    "isSelected"    => $selected == $name,
                    "isBack"        => false,
                    "isDir"         => $isDir,
                    "isDirOrBack"   => $isDir,
                    "icon"          => FileType::getIcon($name),
                    "isImage"       => $isImage,
                    "isTransparent" => FileType::isPNG($name),
                    "isFile"        => $isFile,
                    "isFileOrImage" => $isFile || $isImage,
                    "source"        => Path::getUrl(Path::Source, $path, $name),
                    "thumb"         => Path::getUrl(Path::Thumb,  $path, $name),
                ];
            }
        }

        usort($result, function ($a, $b) {
            if ($a["isDir"] && $b["isDir"]) {
                return strnatcasecmp($a["name"], $b["name"]);
            }
            if ($a["isDir"] && !$b["isDir"]) {
                return -1;
            }
            if (!$a["isDir"] && $b["isDir"]) {
                return 1;
            }
            return strnatcasecmp($a["name"], $b["name"]);
        });

        if (!empty($path)) {
            $dir = dirname($path);
            array_unshift($result, [
                "name"        => "...",
                "value"       => $dir != "." ? $dir : "",
                "route"       => $dir != "." ? "/" . Strings::fromUrl($dir) : "",
                "canSelect"   => false,
                "isBack"      => true,
                "isDirOrBack" => true,
            ]);
        }

        return self::view()->create("main", $request, [
            "files"     => $result,
            "path"      => Strings::toUrl($path),
            "isSelect"  => $request->has("select"),
            "canResize" => !$request->has("select"),
            "query"     => self::getQuery($request)->toString(),
        ]);
    }



    /**
     * Creates a Directory
     * @param Request $request
     * @return Response
     */
    public static function create(Request $request):Response {
        $error = "";
        if (!$request->has("name")) {
            $error = "createName";
        } elseif (Path::exists(Path::Source, $request->path, $request->name)) {
            $error = "createExists";
        } elseif (!FileMedia::create($request->path, $request->name)) {
            $error = "create";
        }
        return self::redirect($request, $error, "create");
    }

    /**
     * Uploads a Media Element
     * @param Request $request
     * @return Response
     */
    public static function upload(Request $request): Response {
        $error = "";
        if (!$request->hasFile("file")) {
            $error = "uploadEmpty";
        } elseif ($request->hasSizeError("file")) {
            $error = "uploadSize";
        } elseif (Path::exists(Path::Source, $request->path, $request->getFileName("file"))) {
            $error = "uploadExists";
        } else {
            $fileName = $request->getFileName("file");
            $tmpFile  = $request->getTmpName("file");
            $relPath  = FileMedia::Upload($request->path, $fileName, $tmpFile);
            if (empty($relPath)) {
                $error = "upload";
            }
        }
        return self::redirect($request, $error, "upload");
    }

    /**
     * Renames a Media Element
     * @param Request $request
     * @return Response
     */
    public static function rename(Request $request): Response {
        $error = "";
        if (!$request->has("oldName")) {
            $error = "renameOldName";
        } elseif (!$request->has("newName")) {
            $error = "renameNewName";
        } elseif (!Path::exists(Path::Source, $request->path, $request->oldName)) {
            $error = "renameOldExists";
        } else {
            $newName = File::parseName($request->newName, $request->oldName);
            if (!Strings::isEqual($request->oldName, $request->oldName) && Path::exists(Path::Source, $request->path, $newName)) {
                $error = "renameNewExists";
            } elseif (!FileMedia::rename($request->path, $request->oldName, $newName)) {
                $error = "rename";
            }
        }

        if (empty($error)) {
            $fullPath = Path::getPath(Path::Source, $request->path, $newName);
            if (FileType::isDir($fullPath)) {
                $files   = File::getFilesInDir($fullPath);
                $oldPath = File::getPath($request->path, $request->oldName);
                $newPath = File::getPath($request->path, $newName);
                foreach ($files as $file) {
                    $relPath = File::removeFirstSlash(Strings::substringAfter($file, $fullPath));
                    self::update($oldPath, $relPath, $newPath, $relPath);
                }
            } else {
                self::update($request->path, $request->oldName, $request->path, $newName);
            }
        }
        return self::redirect($request, $error, "rename");
    }

    /**
     * Moves a Media Element
     * @param Request $request
     * @return Response
     */
    public static function move(Request $request): Response {
        $error = "";
        if (!$request->has("name")) {
            $error = "moveOldPath";
        } elseif (!$request->exists("newPath")) {
            $error = "moveNewPath";
        } elseif (!Path::exists(Path::Source, $request->oldPath, $request->name)) {
            $error = "moveOldExists";
        } elseif (Path::exists(Path::Source, $request->newPath, $request->name)) {
            $error = "moveNewExists";
        } elseif (!FileMedia::move($request->oldPath, $request->newPath, $request->name)) {
            $error = "move";
        }

        if (empty($error)) {
            self::update($request->oldPath, $request->name, $request->newPath, $request->name);
        }
        $request->path = $request->oldPath;
        return self::redirect($request, $error, "move");
    }

    /**
     * Deletes a Media Element
     * @param Request $request
     * @return Response
     */
    public static function delete(Request $request): Response {
        $error = "";
        if (!$request->has("confirmed")) {
            $error = "deleteConfirm";
        } elseif (!$request->has("name")) {
            $error = "deleteName";
        } elseif (!Path::exists(Path::Source, $request->path, $request->name)) {
            $error = "deleteExists";
        } elseif (!FileMedia::delete($request->path, $request->name)) {
            $error = "delete";
        }

        if (empty($error)) {
            self::update($request->path, $request->name);
        }
        return self::redirect($request, $error, "delete");
    }



    /**
     * Returns the Media list view
     * @param Request $request
     * @return Response
     */
    public static function resize(Request $request): Response {
        $files = FileMedia::getAllToResize();
        return self::view()->create("resize", $request, [
            "files" => $files,
            "total" => count($files),
            "query" => self::getQuery($request)->toString(),
        ]);
    }

    /**
     * Resizes a single Media Image
     * @param Request $request
     * @return Response
     */
    public static function resizeOne(Request $request): Response {
        if ($request->has("path") && FileMedia::resizeImage($request->path, false)) {
            return Response::json([ "success" => 1 ]);
        }
        return Response::json([ "success" => 0 ]);
    }
}
