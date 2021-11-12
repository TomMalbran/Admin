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
    private static function load(): void {
        if (self::$loaded) {
            return;
        }
        $adminData    = Admin::loadData(Admin::ActionData, "admin");
        $internalData = Admin::loadData(Admin::ActionData, "internal");
        $sections     = Admin::getSections();

        self::$loaded = true;
        self::$data   = Arrays::extend($internalData, $adminData);

        foreach ($sections as $key => $section) {
            if (!empty($section["actions"])) {
                self::$data[$key] = [
                    "name"   => $section["singular"],
                    "action" => $section["actions"],
                ];
            }
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
