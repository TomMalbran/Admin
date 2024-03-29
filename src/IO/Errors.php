<?php
namespace Admin\IO;

use Admin\Utils\Arrays;

/**
 * The Errors wrapper
 */
class Errors {

    /** @var array{} */
    private array $errors = [];


    /**
     * Creates a new Errors instance
     * @param string[]|string|null $errors
     */
    public function __construct(array|string $errors = null) {
        if ($errors !== null) {
            $errors = Arrays::toArray($errors);
            foreach ($errors as $error) {
                $this->add($error);
            }
        }
    }



    /**
     * Sets the given key on the error data with the given value
     * @param string $error
     * @param string $message
     * @return void
     */
    public function __set(string $error, string $message): void {
        $this->add($error, $message);
    }

    /**
     * Sets the given key on the error data with the given value
     * @param string $error
     * @return string
     */
    public function __get(string $error): string {
        if ($this->has($error)) {
            return $this->errors[$error];
        }
        return "";
    }



    /**
     * Adds a new error
     * @param string $error
     * @param string $message Optional.
     * @return Errors
     */
    public function add(string $error, string $message = "error"): Errors {
        $this->errors[$error] = $message;
        return $this;
    }

    /**
     * Adds a new error if the condition is true
     * @param boolean $condition
     * @param string  $error
     * @param string  $message   Optional.
     * @return Errors
     */
    public function addIf(bool $condition, string $error, string $message = "error"): Errors {
        if ($condition) {
            $this->add($error, $message);
        }
        return $this;
    }

    /**
     * Adds a new form error
     * @param string $message
     * @return Errors
     */
    public function form(string $message): Errors {
        $this->errors["form"] = $message;
        return $this;
    }

    /**
     * Adds a new global error
     * @param string $message
     * @return Errors
     */
    public function global(string $message): Errors {
        $this->errors["global"] = $message;
        return $this;
    }



    /**
     * Returns true if there are errors or if the given error exists
     * @param string[]|string|null $error Optional.
     * @return boolean
     */
    public function has(array|string $error = null): bool {
        if ($error === null) {
            return !empty($this->errors);
        }
        $errors = Arrays::toArray($error);
        foreach ($errors as $err) {
            if (!empty($this->errors[$err])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the errors as an Object
     * @param boolean $withSuffix Optional.
     * @return array{}
     */
    public function get(bool $withSuffix = true): array {
        if (!$withSuffix) {
            return $this->errors;
        }

        $result = [];
        foreach ($this->errors as $error => $message) {
            $result["{$error}Error"] = $message;
        }
        return $result;
    }

    /**
     * Returns the Errors as double object
     * @return array{}
     */
    public function getObject(): array {
        $result = [];
        foreach ($this->errors as $error => $message) {
            if (empty($result[$error])) {
                $result[$error] = [ "hasError" => 1 ];
            }
            $result[$error]["{$message}Error"] = 1;
        }
        return $result;
    }
}
