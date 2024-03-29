<?php
namespace Admin\Utils;

/**
 * Several Array Utils
 */
class Arrays {

    /**
     * Returns true if the given value is an array
     * @param mixed $array
     * @return boolean
     */
    public static function isArray(mixed $array): bool {
        return is_array($array);
    }

    /**
     * Returns true if the given value is a map
     * @param mixed $array
     * @return boolean
     */
    public static function isMap(mixed $array): bool {
        return self::isArray($array) && self::isArray(array_values($array)[0]);
    }



    /**
     * Returns the length of the given array
     * @param mixed $array
     * @return integer
     */
    public static function length(mixed $array): int {
        if (self::isMap($array)) {
            return count(array_keys($array));
        }
        if (self::isArray($array)) {
            return count($array);
        }
        return 0;
    }

    /**
     * Returns true if the array contains the needle
     * @param mixed $array
     * @param mixed $needle
     * @return boolean
     */
    public static function contains(mixed $array, mixed $needle): bool {
        return in_array($needle, self::toArray($array));
    }

    /**
     * Returns true if the array contains the needle as a key
     * @param mixed[] $array
     * @param mixed   $needle
     * @return boolean
     */
    public static function containsKey(array $array, mixed $needle): bool {
        return in_array($needle, array_keys($array));
    }



    /**
     * Converts a single value or an array into an array
     * @param mixed $array
     * @return mixed[]
     */
    public static function toArray(mixed $array): array {
        return self::isArray($array) ? $array : [ $array ];
    }

    /**
     * Converts an empty array into an object or returns the array
     * @param mixed[]|null $array Optional.
     * @return mixed
     */
    public static function toObject(?array $array = null): mixed {
        return !empty($array) ? $array : new \stdClass();
    }

    /**
     * Converts a single value or a map into an array
     * @param mixed $array
     * @return mixed[]
     */
    public static function getValues(mixed $array): array {
        return self::isArray($array) ? array_values($array) : [ $array ];
    }

    /**
     * Returns a random value from the array
     * @param mixed[] $array
     * @return mixed
     */
    public static function random(array $array): mixed {
        return $array[array_rand($array)];
    }

    /**
     * Removes the empty entries from the given array
     * @param mixed[] $array
     * @return mixed[]
     */
    public static function removeEmpty(array $array): array {
        $result = [];
        foreach ($array as $value) {
            if (!empty($value)) {
                $result[] = $value;
            }
        }
        return $result;
    }

    /**
     * Returns an array with values in the Base
     * @param mixed[] $base
     * @param mixed[] $array
     * @return mixed[]
     */
    public static function subArray(array $base, array $array): array {
        $result = [];
        foreach ($array as $value) {
            if (in_array($value, $base)) {
                $result[] = $value;
            }
        }
        return $result;
    }

    /**
     * Extends the first array replacing values from the second array
     * @param mixed[] $array1
     * @param mixed[] $array2
     * @return mixed[]
     */
    public static function extend(array &$array1, array &$array2): array {
        $result = $array1;
        foreach ($array2 as $key => &$value) {
            if (self::isArray($value) && isset($result[$key]) && self::isArray($result[$key])) {
                $result[$key] = self::extend($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Sorts an array using the given callback
     * @param mixed[]  $array
     * @param callable $callback
     * @return mixed[]
     */
    public static function sort(array &$array, callable $callback): array {
        usort($array, $callback);
        return $array;
    }

    /**
     * Sorts the arrays at the given key of the given array using the given callback
     * @param mixed[]  $array
     * @param string   $field
     * @param callable $callback
     * @return mixed[]
     */
    public static function sortArray(array &$array, string $field, callable $callback): array {
        foreach ($array as $value) {
            if (!empty($value[$field]) && self::isArray($value[$field])) {
                usort($value[$field], $callback);
            }
        }
        return $array;
    }



    /**
     * Creates a map using the given array
     * @param mixed[]              $array
     * @param string               $key
     * @param string[]|string|null $value Optional.
     * @return mixed[]
     */
    public static function createMap(array $array, string $key, array|string $value = null): array {
        $result = [];
        foreach ($array as $row) {
            $result[$row[$key]] = !empty($value) ? self::getValue($row, $value) : $row;
        }
        return $result;
    }

    /**
     * Creates an array using the given array
     * @param mixed[]              $array
     * @param string[]|string|null $value Optional.
     * @return mixed[]
     */
    public static function createArray(array $array, array|string $value = null): array {
        $result = [];
        foreach ($array as $row) {
            $result[] = !empty($value) ? self::getValue($row, $value) : $row;
        }
        return $result;
    }

    /**
     * Creates a reduced array/map using the given array/map
     * @param mixed[]         $array
     * @param string[]|string ...$keys
     * @return mixed[]
     */
    public static function reduceArray(array $array, array|string ...$keys): array {
        $result = [];
        foreach ($array as $index => $row) {
            $result[$index] = [];
            foreach ($keys as $key) {
                if (self::isArray($key)) {
                    $result[$index][$key[1]] = $row[$key[0]];
                } else {
                    $result[$index][$key] = $row[$key];
                }
            }
        }
        return $result;
    }

    /**
     * Creates a select using the given array
     * @param mixed[]         $array
     * @param string          $key
     * @param string[]|string $value
     * @param mixed|null      $selected Optional.
     * @param string|null     $extra    Optional.
     * @return mixed[]
     */
    public static function createSelect(array $array, string $key, array|string $value, mixed $selected = null, ?string $extra = null): array {
        $result = [];
        foreach ($array as $row) {
            $fields = [
                "key"        => $row[$key],
                "value"      => self::getValue($row, $value, " - ", ""),
                "isSelected" => self::contains($selected, $row[$key]),
            ];
            if ($extra) {
                $fields[$extra] = self::getValue($row, $extra);
            }
            $result[] = $fields;
        }
        return $result;
    }

    /**
     * Creates a select using the given array
     * @param mixed[]    $array
     * @param mixed|null $selected Optional.
     * @param boolean    $withNone Optional.
     * @return mixed[]
     */
    public static function createSelectFromMap(array $array, mixed $selected = null, bool $withNone = false): array {
        $result = [];
        if ($withNone) {
            $result[] = [
                "key"        => 0,
                "value"      => "",
                "isSelected" => self::contains($selected, 0),
            ];
        }
        foreach ($array as $key => $value) {
            $result[] = [
                "key"        => $key,
                "value"      => $value,
                "isSelected" => self::contains($selected, $key),
            ];
        }
        return $result;
    }

    /**
     * Returns a function to select in a Select
     * @return callable
     */
    public static function getSelectedFunc(): callable {
        return function ($value, $render) {
            [ $key, $val ] = explode("-", $value);
            $key = $render($key);
            $val = $render($val);
            return $key == $val ? "selected" : "";
        };
    }



    /**
     * Returns the key adding the prefix or not
     * @param string $key
     * @param string $prefix Optional.
     * @return string
     */
    public static function getKey(string $key, string $prefix = ""): string {
        return !empty($prefix) ? $prefix . ucfirst($key) : $key;
    }

    /**
     * Returns one or multiple values as a string
     * @param mixed           $array
     * @param string[]|string $key
     * @param string          $glue     Optional.
     * @param string          $prefix   Optional.
     * @param boolean         $useEmpty Optional.
     * @return mixed
     */
    public static function getValue(mixed $array, array|string $key, string $glue = " - ", string $prefix = "", bool $useEmpty = false): mixed {
        $result = "";
        if (self::isArray($key)) {
            $values = [];
            foreach ($key as $id) {
                $fullKey = self::getKey($id, $prefix);
                if ($useEmpty && isset($array[$fullKey])) {
                    $values[] = $array[$fullKey];
                } elseif (!$useEmpty && !empty($array[$fullKey])) {
                    $values[] = $array[$fullKey];
                }
            }
            $result = implode($glue, $values);
        } else {
            $fullKey = self::getKey($key, $prefix);
            if ($useEmpty && isset($array[$fullKey])) {
                $result = $array[$fullKey];
            } elseif (!$useEmpty && !empty($array[$fullKey])) {
                $result = $array[$fullKey];
            }
        }
        return $result;
    }

    /**
     * Returns the first value that is not empty in the given keys
     * @param mixed[]    $array
     * @param string[]   $keys
     * @param mixed|null $default Optional.
     * @return string
     */
    public static function getAnyValue(array $array, array $keys, mixed $default = null): string {
        foreach ($keys as $key) {
            if (!empty($array[$key])) {
                return $array[$key];
            }
        }
        return $default;
    }

    /**
     * Returns the first Key of the given array
     * @param mixed[] $array
     * @return mixed
     */
    public static function getFirstKey(array $array): mixed {
        return array_keys($array)[0];
    }

    /**
     * Returns the index at the given id key with the given is value
     * @param mixed[] $array
     * @param string  $idKey
     * @param mixed   $idValue
     * @return mixed
     */
    public static function findIndex(array $array, string $idKey, mixed $idValue): mixed {
        foreach ($array as $index => $elem) {
            if ($elem[$idKey] == $idValue) {
                return $index;
            }
        }
        return -1;
    }

    /**
     * Returns the Value at the given id with the given key
     * @param mixed[] $data
     * @param string  $idKey
     * @param mixed   $idValue
     * @param string  $key     Optional.
     * @return mixed
     */
    public static function findValue(array $data, string $idKey, mixed $idValue, string $key = ""): mixed {
        foreach ($data as $elem) {
            if ($elem[$idKey] == $idValue) {
                return $key ? $elem[$key] : $elem;
            }
        }
        return $key ? "" : [];
    }
}
