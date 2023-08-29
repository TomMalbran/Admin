<?php
namespace Admin\IO;

/**
 * The Url wrapper
 */
class Url {

    private string $uri = "";

    /** @var array{} */
    private array $params = [];


    /**
     * Creates a new Url instance
     * @param string $uri
     */
    public function __construct(string $uri = "") {
        $this->uri = $uri instanceof Url ? $uri->toString() : $uri;
    }



    /**
     * Sets a new param
     * @param string $key
     * @param mixed  $value
     * @return Url
     */
    public function set(string $key, mixed $value): Url {
        $this->params[$key] = (string)$value;
        return $this;
    }

    /**
     * Removes a param
     * @param string $key
     * @return Url
     */
    public function remove(string $key): Url {
        if (isset($this->params[$key])) {
            unset($this->params[$key]);
        }
        return $this;
    }

    /**
     * Merges the given Url with this one
     * @param Url $url
     * @return Url
     */
    public function merge(Url $url): Url {
        $this->params = array_merge($this->params, $url->toArray());
        return $this;
    }



    /**
     * Returns the params as an Object
     * @return array{}
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
