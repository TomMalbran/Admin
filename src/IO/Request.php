<?php
namespace Admin\IO;

use Admin\IO\Errors;
use Admin\IO\Status;
use Admin\File\Path;
use Admin\File\File;
use Admin\File\FileType;
use Admin\File\Image;
use Admin\Utils\Arrays;
use Admin\Utils\DateTime;
use Admin\Utils\Numbers;
use Admin\Utils\Strings;
use Admin\Utils\CSV;
use Admin\Utils\JSON;
use Admin\Utils\Utils;
use ArrayAccess;

/**
 * The Request Wrapper
 */
class Request implements ArrayAccess {

    /** @var array{} */
    private array $request;

    /** @var array{} */
    private array $files;

    /** @var array{} */
    private array $filters;


    /**
     * Creates a new Request instance
     * @param array{}|null $request Optional.
     */
    public function __construct(?array $request = null) {
        $this->request = $request ?: $_REQUEST;
        $this->files   = $_FILES;
        $this->filters = [];
    }



    /**
     * Returns the request data at the given key
     * @param string $key
     * @return mixed
     */
    public function __get(string $key): mixed {
        return $this->get($key);
    }

    /**
     * Sets the given key on the request data with the given value
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function __set(string $key, mixed $value): void {
        $this->set($key, $value);
    }

    /**
     * Returns true if the given key is set in the request data
     * @param string $key
     * @return boolean
     */
    public function __isset(string $key): bool {
        return $this->exists($key);
    }

    /**
     * Removes the request data at the given key
     * @param string $key
     * @return void
     */
    public function __unset(string $key) {
        $this->remove($key);
    }



    /**
     * Returns the request data at the given key or the default
     * @param string       $key
     * @param mixed|string $default Optional.
     * @return mixed
     */
    public function get(string $key, mixed $default = ""): mixed {
        return isset($this->request[$key]) ? $this->request[$key] : $default;
    }

    /**
     * Returns the request data at the given key or the default
     * @param string       $key
     * @param mixed|string $default Optional.
     * @return mixed
     */
    public function getOr(string $key, mixed $default): mixed {
        return !empty($this->request[$key]) ? $this->request[$key] : $default;
    }

    /**
     * Returns the request data at the given key or the default
     * @param string  $key
     * @param integer $default Optional.
     * @return integer
     */
    public function getInt(string $key, int $default = 0): int {
        return isset($this->request[$key]) ? (int)$this->request[$key] : $default;
    }

    /**
     * Returns the request data at the given key or the default
     * @param string  $key
     * @param integer $default
     * @return integer
     */
    public function getIntOr(string $key, int $default): int {
        return !empty($this->request[$key]) ? (int)$this->request[$key] : $default;
    }

    /**
     * Returns the request data at the given key as a trimmed string or the default
     * @param string $key
     * @param string $default Optional.
     * @return string
     */
    public function getString(string $key, string $default = ""): string {
        return isset($this->request[$key]) ? trim((string)$this->request[$key]) : $default;
    }

    /**
     * Returns the request data at the given key as a trimmed string or the default
     * @param string $key
     * @param string $default
     * @return string
     */
    public function getStringOr(string $key, string $default): string {
        return !empty($this->request[$key]) ? trim((string)$this->request[$key]) : $default;
    }

    /**
     * Returns the request data at the given key as an array and removing the empty entries
     * @param string $key
     * @return mixed[]
     */
    public function getArray(string $key): array {
        return Arrays::removeEmpty($this->get($key, []));
    }

    /**
     * Returns the request data at the given key from an array or the default
     * @param string       $key
     * @param integer      $index
     * @param mixed|string $default Optional.
     * @return mixed
     */
    public function getFromArray(string $key, int $index, mixed $default = ""): mixed {
        if (isset($this->request[$key]) && isset($this->request[$key][$index])) {
            return $this->request[$key][$index];
        }
        return $default;
    }

    /**
     * Returns the request data at the given key as JSON
     * @param string  $key
     * @param boolean $asArray Optional.
     * @return mixed
     */
    public function getJSON(string $key, bool $asArray = false): mixed {
        return JSON::decode($this->get($key, "[]"), $asArray);
    }



    /**
     * Sets the given key on the request data with the given value
     * @param string       $key
     * @param mixed|string $value Optional.
     * @return Request
     */
    public function set(string $key, mixed $value = ""): Request {
        $this->request[$key] = $value;
        return $this;
    }

    /**
     * Sets the data of the give object
     * @param mixed[] $object
     * @return Request
     */
    public function setObject(array $object): Request {
        foreach ($object as $key => $value) {
            $this->request[$key] = $value;
        }
        return $this;
    }

    /**
     * Removes the request data at the given key
     * @param string $key
     * @return Request
     */
    public function remove(string $key): Request {
        if ($this->exists($key)) {
            unset($this->request[$key]);
        }
        return $this;
    }



    /**
     * Returns true if the given key exists in the request data
     * @param string[]|string|null $key   Optional.
     * @param integer|null         $index Optional.
     * @return boolean
     */
    public function has(array|string $key = null, ?int $index = null): bool {
        if ($key === null) {
            return !empty($this->request);
        }
        if (Arrays::isArray($key)) {
            foreach ($key as $keyID) {
                if (empty($this->request[$keyID])) {
                    return false;
                }
            }
            return true;
        }
        if ($index !== null) {
            return !empty($this->request[$key]) && !empty($this->request[$key][$index]);
        }
        return !empty($this->request[$key]);
    }

    /**
     * Returns true if the given key is set in the request data
     * @param string[]|string $key
     * @return boolean
     */
    public function exists(array|string $key): bool {
        if (Arrays::isArray($key)) {
            foreach ($key as $keyID) {
                if (!isset($this->request[$keyID])) {
                    return false;
                }
            }
            return true;
        }
        return isset($this->request[$key]);
    }



    /**
     * Checks if all the given keys are not empty or set
     * @param string[] $emptyKeys
     * @param string[] $setKeys   Optional.
     * @return boolean
     */
    public function isEmpty(array $emptyKeys, array $setKeys = []): bool {
        foreach ($emptyKeys as $field) {
            if (!$this->has($field)) {
                return true;
            }
        }
        foreach ($setKeys as $field) {
            if (!$this->exists($field)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the array is empty
     * @param string $key
     * @return boolean
     */
    public function isEmptyArray(string $key): bool {
        $array = $this->getArray($key);
        return empty($array);
    }



    /**
     * Returns true if the given value is a number and greater and/or equal to cero
     * @param string       $key
     * @param integer      $min  Optional.
     * @param integer|null $max  Optional.
     * @param integer|null $mult Optional.
     * @return boolean
     */
    public function isNumeric(string $key, int $min = 1, ?int $max = null, ?int $mult = 1): bool {
        return Numbers::isValid($this->getInt($key) * $mult, $min, $max);
    }

    /**
     * Returns true if the given price is valid
     * @param string       $key
     * @param integer      $min Optional.
     * @param integer|null $max Optional.
     * @return boolean
     */
    public function isValidPrice(string $key, int $min = 1, ?int $max = null): bool {
        return Numbers::isValidPrice($this->getInt($key), $min, $max);
    }

    /**
     * Returns true if the given value is alpha-numeric
     * @param string       $key
     * @param boolean      $withDashes Optional.
     * @param integer|null $length     Optional.
     * @return boolean
     */
    public function isAlphaNum(string $key, bool $withDashes = false, ?int $length = null): bool {
        return Utils::isAlphaNum($this->get($key, ""), $withDashes, $length);
    }

    /**
     * Returns true if the given email is valid
     * @param string $key
     * @return boolean
     */
    public function isValidEmail(string $key): bool {
        return Utils::isValidEmail($this->get($key));
    }

    /**
     * Returns true if the given password is valid
     * @param string  $key
     * @param string  $checkSets Optional.
     * @param integer $minLength Optional.
     * @return boolean
     */
    public function isValidPassword(string $key, string $checkSets = "ad", int $minLength = 6): bool {
        return Utils::isValidPassword($this->get($key), $checkSets, $minLength);
    }

    /**
     * Returns true if the given Position is valid
     * @param string $key
     * @return boolean
     */
    public function isValidPosition(string $key): bool {
        return !$this->has($key) || $this->isNumeric($key, 0);
    }

    /**
     * Returns true if the given Status is valid
     * @param string $key
     * @param string $groupName Optional.
     * @return boolean
     */
    public function isValidStatus(string $key, string $groupName = "general"): bool {
        return Status::isValid($this->get($key), $groupName);
    }



    /**
     * Returns true if the given date is Valid
     * @param string $key
     * @return boolean
     */
    public function isValidDate(string $key): bool {
        return DateTime::isValidDate($this->get($key));
    }

    /**
     * Returns true if the given hour is Valid
     * @param string        $key
     * @param string[]|null $minutes Optional.
     * @return boolean
     */
    public function isValidHour(string $key, ?array $minutes = null): bool {
        return DateTime::isValidHour($this->get($key), $minutes);
    }

    /**
     * Returns true if the given dates are a valid period
     * @param string $fromKey
     * @param string $toKey
     * @return boolean
     */
    public function isValidPeriod(string $fromKey, string $toKey): bool {
        return DateTime::isValidPeriod($this->get($fromKey), $this->get($toKey));
    }

    /**
     * Returns true if the given hours are a valid period
     * @param string $fromKey
     * @param string $toKey
     * @return boolean
     */
    public function isValidHourPeriod(string $fromKey, string $toKey): bool {
        return DateTime::isValidHourPeriod($this->get($fromKey), $this->get($toKey));
    }

    /**
     * Returns true if the given hours are a valid period
     * @param string $fromDateKey
     * @param string $fromHourKey
     * @param string $toDateKey
     * @param string $toHourKey
     * @return boolean
     */
    public function isValidFullPeriod(
        string $fromDateKey,
        string $fromHourKey,
        string $toDateKey,
        string $toHourKey
    ): bool {
        return DateTime::isValidFullPeriod(
            $this->get($fromDateKey),
            $this->get($fromHourKey),
            $this->get($toDateKey),
            $this->get($toHourKey)
        );
    }

    /**
     * Returns true if the given week day is valid
     * @param string $key
     * @return boolean
     */
    public function isValidWeekDay(string $key): bool {
        return DateTime::isValidWeekDay($this->getInt($key));
    }

    /**
     * Returns true if the given date is in the future
     * @param string $key
     * @param string $type Optional.
     * @return boolean
     */
    public function isFutureDate(string $key, string $type = "middle"): bool {
        return DateTime::isFutureDate($this->get($key), $type);
    }



    /**
     * Returns the request as an array
     * @return array{}
     */
    public function toArray(): array {
        return $this->request;
    }

    /**
     * Returns the requested data as a binary
     * @param string  $key
     * @param integer $default Optional.
     * @return integer
     */
    public function toBinary(string $key, int $default = 1): int {
        return $this->has($key) ? $default : 0;
    }

    /**
     * Returns the requested number as an integer using the given decimals
     * @param string  $key
     * @param integer $decimals
     * @return integer
     */
    public function toInt(string $key, int $decimals): int {
        return Numbers::toInt($this->get($key), $decimals);
    }

    /**
     * Returns the requested price in cents
     * @param string       $key
     * @param integer|null $index Optional.
     * @return integer
     */
    public function toCents(string $key, ?int $index = null): int {
        $value = $index !== null ? $this->getFromArray($key, $index, 0) : $this->get($key);
        return Numbers::toCents((float)$value);
    }

    /**
     * Returns the requested string as html
     * @param string $key
     * @param string $default Optional.
     * @return string
     */
    public function toHtml(string $key, string $default = ""): string {
        return Strings::toHtml($this->getString($key, $default));
    }

    /**
     * Returns the requested array encoded as JSON
     * @param string $key
     * @return string
     */
    public function toJSON(string $key): string {
        return JSON::encode($this->get($key, []));
    }

    /**
     * Returns the requested array encoded as CSV
     * @param string $key
     * @return string
     */
    public function toCSV(string $key): string {
        return CSV::encode($this->get($key));
    }



    /**
     * Returns the given strings as a time
     * @param string  $key
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public function toTime(string $key, bool $useTimezone = true): int {
        return DateTime::toTime($this->get($key), $useTimezone);
    }

    /**
     * Returns the given strings as a time
     * @param string  $dateKey
     * @param string  $hourKey
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public function toTimeHour(string $dateKey, string $hourKey, bool $useTimezone = true): int {
        return DateTime::toTimeHour($this->get($dateKey), $this->get($hourKey), $useTimezone);
    }

    /**
     * Returns the given string as a time
     * @param string  $key
     * @param string  $type        Optional.
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public function toDay(string $key, string $type = "start", bool $useTimezone = true): int {
        return DateTime::toDay($this->get($key), $type, $useTimezone);
    }

    /**
     * Returns the given string as a time of the start of the day
     * @param string  $key
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public function toDayStart(string $key, bool $useTimezone = true): int {
        return DateTime::toDayStart($this->get($key), $useTimezone);
    }

    /**
     * Returns the given string as a time of the middle of the day
     * @param string  $key
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public function toDayMiddle(string $key, bool $useTimezone = true): int {
        return DateTime::toDayMiddle($this->get($key), $useTimezone);
    }

    /**
     * Returns the given string as a time of the end of the day
     * @param string  $key
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public function toDayEnd(string $key, bool $useTimezone = true): int {
        return DateTime::toDayEnd($this->get($key), $useTimezone);
    }



    /**
     * Returns the Array keys from the given array
     * @param string $key
     * @return mixed[]
     */
    public function getKeys(string $key): array {
        return array_keys($this->get($key, []));
    }



    /**
     * Returns the request file at the given key
     * @param string $key
     * @return mixed
     */
    public function getFile(string $key): mixed {
        return isset($this->files[$key]) ? $this->files[$key] : null;
    }

    /**
     * Returns the request file name at the given key
     * @param string $key
     * @return string
     */
    public function getFileName(string $key): string {
        if ($this->hasFile($key)) {
            return $this->files[$key]["name"];
        }
        return "";
    }

    /**
     * Returns the request file temporal name at the given key
     * @param string $key
     * @return string
     */
    public function getTmpName(string $key): string {
        if ($this->hasFile($key)) {
            return $this->files[$key]["tmp_name"];
        }
        return "";
    }

    /**
     * Returns true if the given key exists in the files data
     * @param string $key
     * @return boolean
     */
    public function hasFile(string $key): bool {
        return !empty($this->files[$key]) && !empty($this->files[$key]["name"]);
    }

    /**
     * Returns true if there was a size error in the upload
     * @param string $key
     * @return boolean
     */
    public function hasSizeError(string $key): bool {
        if ($this->hasFile($key)) {
            return !empty($this->files[$key]["error"]) && $this->files[$key]["error"] == UPLOAD_ERR_INI_SIZE;
        }
        return true;
    }

    /**
     * Returns true if the file at the given key has the given extension
     * @param string          $key
     * @param string[]|string $extensions
     * @return boolean
     */
    public function hasExtension(string $key, array|string $extensions): bool {
        if ($this->hasFile($key)) {
            return File::hasExtension($_FILES[$key]["name"], $extensions);
        }
        return false;
    }

    /**
     * Returns true if the file at the given key is a valid Image
     * @param string $key
     * @return boolean
     */
    public function isValidImage(string $key): bool {
        if ($this->hasFile($key)) {
            return Image::isValidType($_FILES[$key]["tmp_name"]);
        }
        return FileType::isImage($this->get($key));
    }

    /**
     * Returns true if the file at the given key is a valid Video
     * @param string $key
     * @return boolean
     */
    public function isValidVideo(string $key): bool {
        return FileType::isVideo($this->get($key));
    }

    /**
     * Returns true if the file at the given key exists in source
     * @param string $key
     * @return boolean
     */
    public function fileExists(string $key): bool {
        if ($this->has($key)) {
            return Path::exists(Path::Source, $this->get($key));
        }
        return false;
    }



    /**
     * Validates an Image
     * @param string  $key
     * @param Errors  $errors
     * @param boolean $isRequired Optional.
     * @return Request
     */
    public function validateImage(string $key, Errors $errors, bool $isRequired = true): Request {
        if ($isRequired && !$this->has($key)) {
            $errors->add("{$key}Empty");
        }
        if ($this->has($key)) {
            if (!$this->isValidImage($key)) {
                $errors->add("{$key}Type");
            } elseif (!$this->fileExists($key)) {
                $errors->add("{$key}Exists");
            }
        }
        return $this;
    }

    /**
     * Validates a Video
     * @param string  $key
     * @param Errors  $errors
     * @param boolean $isRequired Optional.
     * @return Request
     */
    public function validateVideo(string $key, Errors $errors, bool $isRequired = true): Request {
        if ($isRequired && !$this->has($key)) {
            $errors->add("{$key}Empty");
        }
        if ($this->has($key)) {
            if (!$this->isValidVideo($key)) {
                $errors->add("{$key}Type");
            } elseif (!$this->fileExists($key)) {
                $errors->add("{$key}Exists");
            }
        }
        return $this;
    }

    /**
     * Validates a File
     * @param string               $key
     * @param Errors               $errors
     * @param string[]|string|null $extensions Optional.
     * @param boolean              $isRequired Optional.
     * @return Request
     */
    public function validateFile(string $key, Errors $errors, array|string $extensions = null, bool $isRequired = true): Request {
        if ($isRequired && !$this->has($key)) {
            $errors->add("{$key}Empty");
        }
        if ($this->has($key)) {
            if (!empty($extensions) && !$this->hasExtension($key, $extensions)) {
                $errors->add("{$key}Type");
            } elseif (!$this->fileExists($key)) {
                $errors->add("{$key}Exists");
            }
        }
        return $this;
    }

    /**
     * Adds Filters to the Query
     * @param mixed $filters
     * @return Request
     */
    public function addFilter(mixed $filters): Request {
        if (!empty($filters)) {
            $filters = Arrays::toArray($filters);
            foreach ($filters as $filter) {
                $this->filters[] = $filter;
            }
        }
        return $this;
    }

    /**
     * Creates a query for the urls
     * @return Url
     */
    public function getQuery(): Url {
        $url = new Url();
        if ($this->has("page")) {
            $url->set("page", $this->get("page"));
        }

        $filters = array_merge([ "search", "filter", "from", "to" ], $this->filters);
        foreach ($filters as $key) {
            if ($this->has($key)) {
                $url->set($key, $this->get($key));
            }
        }
        return $url;
    }



    /**
     * Implements the Array Access Interface
     * @param mixed $key
     * @return mixed
     */
    public function offsetGet(mixed $key): mixed {
        return $this->get($key);
    }

    /**
     * Implements the Array Access Interface
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $key, mixed $value): void {
        $this->set($key, $value);
    }

    /**
     * Implements the Array Access Interface
     * @param mixed $key
     * @return boolean
     */
    public function offsetExists(mixed $key): bool {
        return array_key_exists($key, $this->request);
    }

    /**
     * Implements the Array Access Interface
     * @param mixed $key
     * @return void
     */
    public function offsetUnset(mixed $key): void {
        unset($this->request[$key]);
    }
}
