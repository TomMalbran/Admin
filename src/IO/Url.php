<?php
namespace Admin\IO;

/**
 * The Url wrapper
 */
class Url {
    
    private $uri    = "";
    private $params = [];
    
    
    /**
     * Creates a new Url instance
     * @param string $uri
     */
    public function __construct(string $uri = "") {
        $this->uri = $uri instanceof Url ? $uri->toString() : $uri;
    }
    
    
    
    /**
     * Adds a new param
     * @param string         $key
     * @param string|integer $value
     * @return void
     */
    public function add(string $key, $value) {
        $this->params[$key] = (string)$value;
    }
    
    /**
     * Removes a param
     * @param string $key
     * @return void
     */
    public function remove(string $key) {
        if (isset($this->params[$key])) {
            unset($this->params[$key]);
        }
    }

    /**
     * Merges the given Url with this one
     * @param Url $url
     * @return void
     */
    public function merge(Url $url) {
        $this->params = array_merge($this->params, $url->toArray());
    }
    
    
    
    /**
     * Returns the params as an Object
     * @return array
     */
    public function toArray(): array {
        return $this->params;
    }
    
    /**
     * Returns the url as a string
     * @return string
     */
    public function toString(): string {
        if (!empty($this->params)) {
            $params = [];
            foreach ($this->params as $key => $value) {
                $params[] = "$key=$value";
            }
            return $this->uri . "?" . implode("&", $params);
        }
        return $this->uri;
    }
    
    /**
     * Returns the url as a string
     * @param string $uri
     * @return string
     */
    public function toUrl(string $uri): string {
        $this->uri = $uri;
        return $this->toString();
    }
}
