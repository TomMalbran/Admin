<?php
namespace Admin\Route;

use Admin\IO\View;
use Admin\IO\Request;
use Admin\IO\Response;
use Admin\IO\Url;
use Admin\File\Path;
use Admin\File\File;
use Admin\File\FileType;
use Admin\File\Media as FileMedia;
use Admin\File\MediaType;
use Admin\Utils\Strings;

/**
 * The Media Route
 */
class Media {
    
    /**
     * Creates and returns the View
     * @param string $url Optional.
     * @return View
     */
    private static function getView(string $url = "media"): View {
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
        return self::getView($url)->redirect($request, $message, $result)->withUrl($query);
    }
    
    
    
    /**
     * Returns the Media list view
     * @param Request $request
     * @return Response
     */
    public static function getAll(Request $request): Response {
        if ($request->has("path")) {
            return self::getOne($request->path, $request);
        }
        return self::getOne("", $request);
    }

    /**
     * Returns the Media list view
     * @param string  $path
     * @param Request $request
     * @return Response
     */
    public static function getOne(string $path, Request $request): Response {
        $path     = Strings::toUrl($path);
        $type     = $request->get("type", "file");
        $selected = "";

        if (empty($path) && $request->has("selected")) {
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
            $name  = Strings::replace($file, $source, "");
            $isDir = FileType::isDir($file);
            if (MediaType::isValid($type, $file, $name)) {
                $result[] = [
                    "name"          => $name,
                    "route"         => "/" . Strings::fromUrl(!empty($path) ? "{$path}/{$name}" : $name),
                    "value"         => !empty($path) ? "{$path}/{$name}" : $name,
                    "canSelect"     => !$isDir,
                    "isSelected"    => $selected == $name,
                    "isDir"         => $isDir,
                    "icon"          => FileType::getIcon($name),
                    "isImage"       => FileType::isImage($name),
                    "isTransparent" => FileType::isPNG($name),
                    "isFile"        => !$isDir && (FileType::isFile($name) || FileType::isVideo($name)),
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
                "name"      => "...",
                "route"     => $dir != "." ? "/" . Strings::fromUrl($dir) : "",
                "canSelect" => false,
                "isBack"    => true,
            ]);
        }
        
        return self::getView()->create("main", $request, [
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
            if (Path::exists(Path::Source, $request->path, $newName)) {
                $error = "renameNewExists";
            } elseif (!FileMedia::rename($request->path, $request->oldName, $newName)) {
                $error = "rename";
            }
        }
        return self::redirect($request, $error, "rename");
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
        return self::redirect($request, $error, "delete");
    }


    
    /**
     * Returns the Media list view
     * @param Request $request
     * @return Response
     */
    public static function resize(Request $request): Response {
        $files = FileMedia::getAllToResize();
        return self::getView()->create("resize", $request, [
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
