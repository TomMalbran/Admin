<?php
namespace Admin;

use Admin\IO\Request;
use Admin\IO\Response;
use Admin\Route\Router;
use Admin\Route\Output;
use Admin\Auth\Auth;
use Admin\Auth\Credential;
use Admin\Config\Config;
use Admin\Config\Settings;
use Admin\Log\ErrorLog;
use Admin\File\File;
use Admin\File\Path;
use Admin\Schema\Factory;
use Admin\Schema\Database;
use Admin\Utils\JSON;
use Admin\Utils\Server;
use Admin\Utils\Strings;
use Exception;

/**
 * The Admin
 */
class Admin {

    // The Data
    const SectionData     = "sections";
    const RouteData       = "routes";
    const SchemaData      = "schemas";
    const KeyData         = "keys";
    const PathData        = "paths";
    const TokenData       = "tokens";
    const SettingsData    = "settings";
    const ActionData      = "actions";
    const MenuData        = "menu";
    const HomeData        = "home";

    // Custom Data
    const SlideData       = "slides";
    const PersonalizeData = "personalize";
    const ContactData     = "contact";

    // The Directories
    const AdminDir        = "admin";
    const SourceDir       = "src";
    const DataDir         = "data";

    const LibDir          = "lib";
    const FilesDir        = "files";
    const PublicDir       = "public";
    const TemplatesDir    = "templates";
    const PartialsDir     = "partials";
    const MigrationsDir   = "data/migrations";

    // Config
    const Namespace       = "App\\Controller\\";

    // Variables
    private static $adminPath     = "";
    private static $internalPath  = "";
    private static $internalRoute = "";
    private static $sections      = [];


    /**
     * Sets the Basic data
     * @param boolean $logErrors Optional.
     * @param boolean $ensureUrl Optional.
     * @return void
     */
    public static function create(bool $logErrors = false, bool $ensureUrl = false): void {
        if ($ensureUrl) {
            $url = Server::getProperUrl();
            if (!empty($url)) {
                header("Location: $url");
                exit;
            }
        }

        self::$adminPath     = dirname(__DIR__, 5);
        self::$internalPath  = dirname(__DIR__, 1);
        self::$internalRoute = Strings::replace(self::$internalPath, self::$adminPath, "");

        if (self::dataExists(self::SectionData)) {
            self::$sections = self::loadData(self::SectionData, "admin");
        }
        if ($logErrors) {
            ErrorLog::init();
        }
    }



    /**
     * Returns the Section Options
     * @return array
     */
    public static function getSections(): array {
        return self::$sections;
    }

    /**
     * Returns true if there are Slide Options
     * @return boolean
     */
    public static function hasSlides(): bool {
        return self::dataExists(self::SlideData);
    }

    /**
     * Returns true if there are Personalize Options
     * @return boolean
     */
    public static function hasPersonalize(): bool {
        return self::dataExists(self::PersonalizeData);
    }

    /**
     * Returns true if there are Contact Options
     * @return boolean
     */
    public static function hasContact(): bool {
        return self::dataExists(self::ContactData);
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
            $path = File::getPath(self::$adminPath, self::AdminDir, $dir);
            break;
        case "site":
            $path = File::getPath(self::$adminPath, $dir);
            break;
        case "internal":
            $path = File::getPath(self::$internalPath, $dir);
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
        $path = File::getPath(self::$adminPath, self::FilesDir, $file);
        return File::removeLastSlash($path);
    }

    /**
     * Returns the Files Relative Path with the given file
     * @param string $file Optional.
     * @return string
     */
    public static function getFilesRelPath(string $file = ""): string {
        $path = File::getPath(self::FilesDir, $file);
        return File::removeLastSlash($path);
    }

    /**
     * Returns the Internal Route
     * @return string
     */
    public static function getInternalRoute() {
        return self::$internalRoute;
    }



    /**
     * Loads a File from the App or defaults to the Admin
     * @param string $dir
     * @param string $file
     * @return string
     */
    public static function loadFile(string $dir, string $file) {
        $path   = self::getPath("$dir/$file", "admin");
        $result = "";
        if (File::exists($path)) {
            $result = file_get_contents($path);
        }
        if (empty($result)) {
            $path   = self::getPath("$dir/$file", "internal");
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
        if (File::exists($path)) {
            return JSON::readFile($path, true);
        }
        return [];
    }

    /**
     * Returns true if the Data File exists
     * @param string $file
     * @param string $type Optional.
     * @return boolean
     */
    public static function dataExists(string $file, string $type = "admin"): bool {
        $path = self::getPath(self::DataDir . "/$file.json", $type);
        return File::exists($path);
    }

    /**
     * Loads a Data File
     * @param string  $file
     * @param string  $type    Optional.
     * @param boolean $asArray Optional.
     * @return object|array
     */
    public static function loadData(string $file, string $type = "admin", bool $asArray = true) {
        $path = self::getPath(self::DataDir . "/$file.json", $type);
        if (File::exists($path)) {
            return JSON::readFile($path, $asArray);
        }
        return [];
    }

    /**
     * Saves a Data File
     * @param string $file
     * @param mixed  $contents
     * @param string $type     Optional.
     * @return void
     */
    public static function saveData(string $file, $contents, string $type = "admin"): void {
        $path = self::getPath(self::DataDir . "/$file.json", $type);
        JSON::writeFile($path, $contents);
    }



    /**
     * Executes the Admin
     * @return void
     */
    public static function execute() {
        $params   = $_REQUEST;
        $route    = Config::getRoute($_SERVER["REQUEST_URI"]);
        $token    = !empty($params["token"]) ? $params["token"] : "";
        $jwt      = !empty($params["jwt"])   ? $params["jwt"]   : "";
        $isAjax   = !empty($params["ajax"]);
        $isReload = !empty($params["reload"]);
        $isFrame  = !empty($params["iframe"]);

        unset($params["token"]);
        unset($params["jwt"]);

        // Run the migrations
        if (!empty($params["migrate"])) {
            self::migrate();

        // For API
        } elseif (!empty($token)) {
            Auth::validateAPI($token);
            try {
                $response = self::request($route, $params);
                if (!empty($response->data)) {
                    print(json_encode($response->data));
                }
            } catch (Exception $e) {
                http_response_code(400);
                die($e->getMessage());
            }

        // For Credential
        } else {
            if (!empty($jwt)) {
                Auth::validateCredential($jwt);
            }
            $response = self::request($route, $params);
            Output::print($response, $isAjax, $isReload, $isFrame);
        }
    }

    /**
     * Returns the requested content
     * @param string $url
     * @param array  $params Optional.
     * @return Response
     */
    public static function request(string $url, array $params = []): Response {
        $accessLevel = Auth::getAccessLevel();
        $route       = Router::get($url, $accessLevel);
        $request     = new Request($params);

        if ($route != null && Auth::grant($route->access)) {
            return Router::call($route, $request);
        }
        if (!Auth::isLoggedIn()) {
            if ($request->has("ajax")) {
                return Response::reload()->withParam("redirectUrl", $url);
            }
            return Response::view("core/index");
        }
        return Response::view("core/error");
    }

    /**
     * Runs the Migrations for the Admin
     * @return void
     */
    public static function migrate(): void {
        $request   = new Request();
        $db        = new Database(Config::get("db"));
        $canDelete = $request->has("delete");

        Factory::migrate($db, $canDelete);
        Settings::migrate($db);
        Path::ensurePaths();
        Credential::seedOwner($db, "Tomas", "Malbran", "tomas@raqdedicados.com", "Cel627570");
    }
}
