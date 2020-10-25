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
        "Editor" => self::Editor,
        "Admin"  => self::Admin,
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
        return is_numeric($value) && in_array($value, array_values(self::$Values));
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
     * @param string $value
     * @return integer
     */
    public static function getValue(string $value): integer {
        if (!empty(self::$Values[$name])) {
            return self::$Values[$name];
        }
        return self::General;
    }

    /**
     * Returns the Access Name
     * @param integer $value
     * @return string
     */
    public static function getName(int $value): string {
        if (!empty(self::$Names[$value])) {
            return self::$Names[$value];
        }
        return "";
    }
}
