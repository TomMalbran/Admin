<?php
namespace Admin\IO;

use Admin\Utils\Arrays;

/**
 * The Status used by the System
 */
class Status {

    const Active   = 0;
    const Inactive = 1;


    /** @var array{} */
    public static array $List = [
        self::Active   => "Activo",
        self::Inactive => "Inactivo",
    ];

    /** @var array{} */
    public static array $FemList = [
        self::Active   => "Activa",
        self::Inactive => "Inactiva",
    ];

    /** @var array{} */
    public static array $Names = [
        self::Active   => "<span class='result-success'>Activo</span>",
        self::Inactive => "<span class='result-error'>Inactivo</span>",
    ];

    /** @var array{} */
    public static array $FemNames = [
        self::Active   => "<span class='result-success'>Activa</span>",
        self::Inactive => "<span class='result-error'>Inactiva</span>",
    ];



    /**
     * Returns true if the given Value is valid
     * @param integer $value
     * @return boolean
     */
    public static function isValid(int $value): bool {
        return is_numeric($value) && in_array($value, array_keys(self::$List));
    }

    /**
     * Returns the Status from a Value
     * @param mixed $value
     * @return integer
     */
    public function get(mixed $value): int {
        if (!empty(self::$Names[$value])) {
            return (int)$value;
        }
        return self::Active;
    }

    /**
     * Creates a select for the templates
     * @param integer $selectedID Optional.
     * @return mixed[]
     */
    public static function getSelect(int $selectedID = 0): array {
        return Arrays::createSelectFromMap(self::$List, $selectedID);
    }

    /**
     * Creates a select for the templates
     * @param integer $selectedID Optional.
     * @return mixed[]
     */
    public static function getFemSelect(int $selectedID = 0): array {
        return Arrays::createSelectFromMap(self::$FemList, $selectedID);
    }

    /**
     * Returns the Name for the given Value
     * @param integer $value
     * @return string
     */
    public static function getName(int $value): string {
        if (!empty(self::$Names[$value])) {
            return self::$Names[$value];
        }
        return "";
    }

    /**
     * Returns the Female Name for the given Value
     * @param integer $value
     * @return string
     */
    public static function getFemName(int $value): string {
        if (!empty(self::$FemNames[$value])) {
            return self::$FemNames[$value];
        }
        return "";
    }
}
