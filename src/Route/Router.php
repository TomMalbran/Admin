<?php
namespace Admin\Route;

use Admin\Admin;
use Admin\IO\Request;
use Admin\IO\Response;
use Admin\Route\Container;
use Admin\Auth\Access;
use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * The Router Service
 */
class Router {

    const Namespace     = "App\\Controller\\";
    const DefaultAction = "getAll";
    const OneAction     = "getOne";
    const Param         = "{0}";

    private static bool $loaded = false;

    /** @var mixed[] */
    private static array $defaults  = [];

    /** @var mixed[] */
    private static array $modules   = [];

    /** @var mixed[] */
    private static array $routes    = [];

    /** @var mixed[] */
    private static array $redirects = [];


    /**
     * Loads the Routes Data
     * @return boolean
     */
    private static function load(): bool {
        if (self::$loaded) {
            return false;
        }
        $internalData = Admin::loadData(Admin::RouteData, "internal");
        $adminData    = Admin::loadData(Admin::RouteData, "admin");
        $sections     = Admin::getSections();

        self::$loaded    = true;
        self::$defaults  = $internalData["defaults"];
        self::$modules   = $internalData["modules"];
        self::$routes    = $internalData["routes"];
        self::$redirects = $internalData["redirects"];

        if (!Admin::hasSlides()) {
            self::removeRoute("/slides");
        }
        if (!Admin::hasPersonalize()) {
            self::removeRoute("/personalize");
        }
        if (!Admin::hasContact()) {
            self::removeRoute("/contacts");
        }

        if (!empty($adminData["defaults"])) {
            self::$defaults = Arrays::extend(self::$defaults, $adminData["defaults"]);
        }
        if (!empty($adminData["modules"])) {
            self::$modules = Arrays::extend(self::$modules, $adminData["modules"]);
        }
        if (!empty($adminData["routes"])) {
            self::$routes = Arrays::extend(self::$routes, $adminData["routes"]);
        }
        if (!empty($adminData["redirects"])) {
            self::$redirects = Arrays::extend(self::$redirects, $adminData["redirects"]);
        }

        foreach ($sections as $section) {
            if (!empty($section["routes"])) {
                self::$modules["/{$section["url"]}"] = $section["module"];
                foreach ($section["routes"] as $route => $accessLevel) {
                    $path = "/{$section["url"]}/$route";
                    self::$routes[$path] = $accessLevel;
                }
            }
        }
        return true;
    }

    /**
     * Removes a Route if it is not used
     * @param string $route
     * @return boolean
     */
    private static function removeRoute(string $route): bool {
        unset(self::$modules[$route]);
        foreach (self::$routes as $path => $accessLevel) {
            if (Strings::startsWith($path, $route)) {
                unset(self::$routes[$path]);
            }
        }
        return true;
    }



    /**
     * Parses the url
     * @param string  $route
     * @param integer $accessLevel
     * @return mixed
     */
    public static function get(string $route, int $accessLevel): mixed {
        self::load();
        if (empty($route)) {
            return null;
        }

        $access = Access::getValue($accessLevel);

        if (empty($route) || $route == "/") {
            $route = self::$defaults[$access];
        }
        if ($route[0] != "/") {
            $route = "/$route";
        }

        $params      = [];
        $routeParsed = parse_url($route);
        $routeParts  = explode("/", $routeParsed["path"]);

        // 2 parts
        if (empty($routeParts[2])) {
            $routeParts[2] = self::DefaultAction;
        } elseif (self::has($routeParts[1], self::OneAction, self::Param) &&
            !self::has($routeParts[1], $routeParts[2]) &&
            !self::has($routeParts[1], $routeParts[2], self::Param) &&
            !isset(self::$modules["/{$routeParts[1]}/{$routeParts[2]}"]) &&
            !self::has($routeParts[1], $routeParts[2], self::DefaultAction)
        ) {
            array_unshift($params, $routeParts[2]);
            $routeParts[2] = self::OneAction;
            $routeParts[3] = self::Param;

        // 3 parts
        } elseif (empty($routeParts[3]) && self::has($routeParts[1], $routeParts[2], self::DefaultAction)) {
            $routeParts[3] = self::DefaultAction;
        } elseif (self::has($routeParts[1], $routeParts[2], self::OneAction, self::Param) &&
            !self::has($routeParts[1], $routeParts[2], $routeParts[3]) &&
            !self::has($routeParts[1], $routeParts[2], $routeParts[3], self::Param)
        ) {
            array_unshift($params, $routeParts[3]);
            $routeParts[3] = self::OneAction;
            $routeParts[4] = self::Param;
        } elseif (!empty($routeParts[3]) && self::has($routeParts[1], $routeParts[2], self::Param)) {
            array_unshift($params, $routeParts[3]);
            $routeParts[3] = self::Param;
        } elseif (!empty($routeParts[3]) && self::has($routeParts[1], $routeParts[2], $routeParts[3], self::Param)) {
            array_unshift($params, $routeParts[4]);
            $routeParts[4] = self::Param;
        }

        $route = implode("/", $routeParts);
        if (isset(self::$redirects[$access]) && isset(self::$redirects[$access][$route])) {
            $route = self::$redirects[$access][$route];
        }

        if (!self::has($route)) {
            return null;
        }
        return (object)[
            "module" => self::getModule($route),
            "method" => self::getMethod($route),
            "access" => self::getAccessLevel($route),
            "params" => $params,
        ];
    }



    /**
     * Returns true if the give Route exists
     * @param string ...$routeParts
     * @return boolean
     */
    public static function has(string ...$routeParts): bool {
        $parts = Arrays::toArray($routeParts);
        $route = implode("/", $parts);
        $route = $route[0] !== "/" ? "/$route" : $route;
        return isset(self::$routes[$route]);
    }

    /**
     * Returns the Access Level for the given Route, if it exists
     * @param string $route
     * @return integer
     */
    public static function getAccessLevel(string $route): int {
        if (self::has($route)) {
            return Access::getID(self::$routes[$route]);
        }
        return 0;
    }

    /**
     * Returns the Module for the given Route, if it exists
     * @param string $route
     * @return string
     */
    public static function getModule(string $route): string {
        if (self::has($route)) {
            $route = str_replace("/{0}", "", $route);
            $name  = substr($route, 0, strripos($route, "/"));
            if (isset(self::$modules[$name])) {
                return self::$modules[$name];
            }
        }
        return "";
    }

    /**
     * Returns the Method for the given Route, if it exists
     * @param string $route
     * @return string
     */
    public static function getMethod(string $route): string {
        if (self::has($route)) {
            $route = str_replace("/{0}", "", $route);
            return substr($route, strripos($route, "/") + 1);
        }
        return "";
    }



    /**
     * Calls the given Route with the given params, if it exists
     * @param mixed   $route
     * @param Request $request Optional.
     * @return Response
     */
    public static function call(mixed $route, Request $request): Response {
        $route->params[] = $request;
        if (Strings::startsWith($route->module, "Admin")) {
            return call_user_func_array("{$route->module}::{$route->method}", $route->params);
        }

        if (!Strings::endsWith($route->module, "Controller")) {
            return call_user_func_array("App\\{$route->module}::{$route->method}", $route->params);
        }

        $instance = Container::bind(self::Namespace . $route->module);
        return call_user_func_array([ $instance, $route->method ], $route->params);
    }
}
