<?php
namespace Admin\Auth;

use Admin\Utils\Arrays;

/**
 * The Auth Access
 */
class Access {

    const General = 0;
    const Editor  = 1;
    const Admin   = 2;
    const API     = 10;



    /**
     * All the valid levels
     */
    public static $Values = [
        self::General => "General",
        self::Editor  => "Editor",
        self::Admin   => "Admin",
        self::API     => "API",
    ];
    public static $Names = [
        self::Editor => "Editor",
        self::Admin  => "Administrador",
    ];



    /**
     * Returns true if the given level is valid
     * @param mixed $value
     * @return boolean
     */
    public static function isValid($value): bool {
        return is_numeric($value) && in_array($value, array_keys(self::$Names));
    }

    /**
     * Creates a select for the templates
     * @param integer $selectedID Optional.
     * @return array
     */
    public static function getSelect(int $selectedID = 0): array {
        return Arrays::createSelectFromMap(self::$Names, $selectedID);
    }



    /**
     * Returns the Access Value
     * @param string $accessValue
     * @return string
     */
    public static function getID(string $accessValue): int {
        foreach (self::$Values as $id => $value) {
            if ($accessValue == $value) {
                return $id;
            }
        }
        return self::General;
    }

    /**
     * Returns the Access Value
     * @param integer $accessLevel
     * @return string
     */
    public static function getValue(int $accessLevel): string {
        if (!empty(self::$Values[$accessLevel])) {
            return self::$Values[$accessLevel];
        }
        return self::$Values[self::General];
    }

    /**
     * Returns the Access Name
     * @param integer $accessLevel
     * @return string
     */
    public static function getName(int $accessLevel): string {
        if (!empty(self::$Names[$accessLevel])) {
            return self::$Names[$accessLevel];
        }
        return "";
    }
}
