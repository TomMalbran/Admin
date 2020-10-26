<?php
namespace Admin\IO;

use Admin\IO\Request;
use Admin\IO\Errors;
use Admin\IO\Url;

/**
 * The Response wrapper
 */
class Response {
    
    public $isRedirect = false;
    public $isJSON     = false;
    public $isScript   = false;
    public $isView     = false;
    public $isAPI      = false;
    
    private $info;
    private $url;
    private $storage;
    
    
    
    /**
     * Returns a Redirect Response
     * @param string  $url     Optional.
     * @param Request $request Optional.
     * @param string  $error   Optional.
     * @return Response
     */
    public static function redirect(string $url = "", Request $request = null, string $error = ""): Response {
        $response = new Response("redirect", [], $url);
        if (!empty($request)) {
            $response->withQuery($request);
        }
        if (!empty($error)) {
            $response->withError($error);
        }
        return $response;
    }

    /**
     * Returns a Reload Redirect Response
     * @param string $url Optional.
     * @return Response
     */
    public static function reload(string $url = ""): Response {
        return new Response("redirect", [
            "reload" => true,
        ], $url);
    }
    
    /**
     * Returns a JSON Response
     * @param array   $result  Optional.
     * @param Request $request Optional.
     * @param Errors  $errors  Optional.
     * @return Response
     */
    public static function json(
        array   $result = null,
        Request $request = null,
        Errors  $errors = null
    ): Response {
        return new Response("JSON", [
            "result"  => $result,
            "request" => $request,
            "errors"  => $errors,
        ]);
    }
    
    /**
     * Returns a Script Response
     * @param string  $action
     * @param array   $result  Optional.
     * @param Request $request Optional.
     * @param Errors  $errors  Optional.
     * @return Response
     */
    public static function script(
        string  $action,
        array   $result = null,
        Request $request = null,
        Errors  $errors = null
    ): Response {
        return new Response("script", [
            "action"  => $action,
            "result"  => $result,
            "request" => $request,
            "errors"  => $errors,
        ]);
    }
    
    /**
     * Returns the Template Response
     * @param string  $template
     * @param string  $mainMenu Optional.
     * @param string  $subMenu  Optional.
     * @param array   $result   Optional.
     * @param Request $request  Optional.
     * @param Errors  $errors   Optional.
     * @return Response
     */
    public static function view(
        string  $template,
        string  $mainMenu = "",
        string  $subMenu = "",
        array   $result = null,
        Request $request = null,
        Errors  $errors = null
    ): Response {
        return new Response("view", [
            "template" => $template,
            "mainMenu" => $mainMenu,
            "subMenu"  => $subMenu,
            "result"   => $result,
            "request"  => $request,
            "errors"   => $errors,
        ]);
    }

    /**
     * Returns the Error Response
     * @param string $mainMenu Optional.
     * @param string $subMenu  Optional.
     * @return Response
     */
    public static function error(string $mainMenu = "", string $subMenu = ""): Response {
        return new Response("view", [
            "template" => "core/error",
            "mainMenu" => $mainMenu,
            "subMenu"  => $subMenu,
        ]);
    }


    
    /**
     * Creates a new Response instance
     * @param string $type
     * @param array  $info Optional.
     * @param string $url  Optional.
     */
    public function __construct(string $type, array $info = [], string $url = "") {
        $this->{"is" . ucfirst($type)} = true;
        $this->info    = (object)$info;
        $this->url     = new Url($url);
        $this->storage = [];
    }
    
    /**
     * Returns the request data at the given key
     * @param string $key
     * @return mixed
     */
    public function __get(string $key) {
        return $this->get($key);
    }

    /**
     * Returns true if the request data at the given key is set
     * @param string $key
     * @return boolean
     */
    public function __isset(string $key) {
        $value = $this->get($key);
        return !empty($value);
    }
    
    /**
     * Returns the request data at the given key
     * @param string $key
     * @return mixed
     */
    public function get(string $key) {
        if ($key == "url") {
            return $this->url->toString();
        }
        if ($key == "data") {
            return $this->getData();
        }
        if (isset($this->info->{$key})) {
            return $this->info->{$key};
        }
        if (isset($this->{$key})) {
            return $this->{$key};
        }
        return null;
    }
    
    
    
    /**
     * Adds the given Message key to the storage
     * @param string  $message
     * @param boolean $success
     * @return Response
     */
    public function withMessage(string $message, bool $success): Response {
        $this->storage[$message . ($success ? "Success" : "Error")] = 1;
        return $this;
    }

    /**
     * Adds the given success key to the storage
     * @param string $message
     * @return Response
     */
    public function withSuccess(string $message): Response {
        return $this->withMessage($message, true);
    }
    
    /**
     * Adds the given error key to the storage
     * @param string $message
     * @return Response
     */
    public function withError(string $message): Response {
        return $this->withMessage($message, false);
    }
    
    /**
     * Adds the given data to the storage
     * @param string $key
     * @param string $value
     * @return Response
     */
    public function withData(string $key, string $value): Response {
        $this->storage[$key] = $value;
        return $this;
    }
    
    /**
     * Adds the given object to the storage
     * @param array $object
     * @return Response
     */
    public function withObject(array $object): Response {
        foreach ($object as $key => $value) {
            $this->storage[$key] = $value;
        }
        return $this;
    }
    
    
    
    /**
     * Adds the given param to the url
     * @param string $key
     * @param mixed  $value
     * @return Response
     */
    public function withParam(string $key, $value): Response {
        $this->url->set($key, $value);
        return $this;
    }
    
    /**
     * Merges the given url with the current url
     * @param Url $url
     * @return Response
     */
    public function withUrl(Url $url): Response {
        $this->url->merge($url);
        return $this;
    }
    
    /**
     * Merges the given url with the current url
     * @param Request $request Optional.
     * @return Response
     */
    public function withQuery(Request $request = null): Response {
        if (!empty($this->info->request)) {
            $query = $this->info->request->getQuery();
        } else {
            $query = $request->getQuery();
        }
        $this->url->merge($query);
        return $this;
    }

    
    
    
    /**
     * Sets the result
     * @param array $result
     * @return Response
     */
    public function withResult(array $result): Response {
        if (!empty($result)) {
            $this->info->result = $result;
        }
        return $this;
    }
    
    /**
     * Sets the request
     * @param Request $request
     * @return Response
     */
    public function withRequest(Request $request): Response {
        if (!empty($request)) {
            $this->info->request = $request;
        }
        return $this;
    }
    
    /**
     * Sets the errors
     * @param Errors $errors Optional.
     * @return Response
     */
    public function withErrors(Errors $errors = null): Response {
        if (!empty($errors)) {
            $this->info->errors = $errors;
        }
        return $this;
    }
    
    
    
    /**
     * Returns the given data as an Object
     * @return array
     */
    public function getData(): array {
        $data = [];
        if (isset($this->info->result)) {
            $data += $this->info->result;
        }
        if (isset($this->info->request)) {
            $data += $this->info->request->toArray();
        }
        if (!$this->isAPI && isset($this->info->errors)) {
            $data += $this->info->errors->get(true);
        }
        return $data;
    }

    /**
     * Returns the given data as an Object
     * @param boolean $withSuffix Optional.
     * @return array
     */
    public function getErrors(bool $withSuffix = false): array {
        if (isset($this->info->errors)) {
            return $this->info->errors->get($withSuffix);
        }
        return [];
    }
}
