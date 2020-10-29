<?php
namespace Admin\Schema;

use Admin\Admin;

/**
 * The Action Data
 */
class Action {
    
    private static $loaded = false;
    private static $data   = [];
    
    
    /**
     * Loads the Actions Data
     * @return void
     */
    public static function load(): void {
        if (!self::$loaded) {
            $adminData    = Admin::loadData(Admin::ActionData, "admin");
            $internalData = Admin::loadData(Admin::ActionData, "internal");

            self::$loaded = true;
            self::$data   = Arrays::extend($internalData, $adminData);
        }
    }


    
    /**
     * Returns the Name for the given Section
     * @param string $section
     * @return string
     */
    public static function getSection(string $section): string {
        self::load();
        if (!empty(self::$data[$section])) {
            return self::$data[$section]["name"];
        }
        return $section;
    }

    /**
     * Returns the Name for the given Action
     * @param string $section
     * @param string $action
     * @return string
     */
    public static function getAction(string $section, string $action): string {
        self::load();
        if (!empty(self::$data[$section]) && !empty(self::$data[$section]["actions"][$action])) {
            return self::$data[$section]["actions"][$action];
        }
        return $action;
    }
}
