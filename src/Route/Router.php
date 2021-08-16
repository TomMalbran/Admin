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

    const DefaultAction = "getAll";
    const OneAction     = "getOne";
    const Param         = "{0}";

    private static $loaded    = false;
    private static $namespace = "";
    private static $defaults  = [];
    private static $modules   = [];
    private static $routes    = [];
    private static $redirects = [];


    /**
     * Loads the Routes Data
     * @return void
     */
    private static function load(): void {
        if (self::$loaded) {
            return;
        }
        $adminData    = Admin::loadData(Admin::RouteData, "admin");
        $internalData = Admin::loadData(Admin::RouteData, "internal");

        self::$loaded    = true;
        self::$namespace = Admin::Namespace;
        self::$defaults  = $internalData["defaults"];
        self::$modules   = $internalData["modules"];
        self::$routes    = $internalData["routes"];
        self::$redirects = $internalData["redirects"];

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
    }



    /**
     * Parses the url
     * @param string  $route
     * @param integer $accessLevel
     * @return string
     */
    public static function get(string $route, int $accessLevel) {
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
    public static function has(string ...$routeParts) {
        $parts = Arrays::toArray($routeParts);
        $route = implode("/", $parts);
        $route = $route[0] !== "/" ? "/$route" : $route;
        return isset(self::$routes[$route]);
    }

    /**
     * Returns the Access Level for the given Route, if it exists
     * @param string $route
     * @return integer|null
     */
    public static function getAccessLevel(string $route) {
        if (self::has($route)) {
            return Access::getID(self::$routes[$route]);
        }
        return null;
    }

    /**
     * Returns the Module for the given Route, if it exists
     * @param string $route
     * @return string|null
     */
    public static function getModule(string $route) {
        if (self::has($route)) {
            $route = str_replace("/{0}", "", $route);
            $name  = substr($route, 0, strripos($route, "/"));
            if (isset(self::$modules[$name])) {
                return self::$modules[$name];
            }
        }
        return null;
    }

    /**
     * Returns the Method for the given Route, if it exists
     * @param string $route
     * @return string|null
     */
    public static function getMethod(string $route) {
        if (self::has($route)) {
            $route = str_replace("/{0}", "", $route);
            return substr($route, strripos($route, "/") + 1);
        }
        return null;
    }



    /**
     * Calls the given Route with the given params, if it exists
     * @param mixed   $route
     * @param Request $request Optional.
     * @return Response
     */
    public static function call($route, Request $request): Response {
        $route->params[] = $request;
        if (Strings::startsWith($route->module, "\\")) {
            return call_user_func_array("{$route->module}::{$route->method}", $route->params);
        }
        $instance = Container::bind(self::$namespace . $route->module);
        return call_user_func_array([ $instance, $route->method ], $route->params);
    }
}
