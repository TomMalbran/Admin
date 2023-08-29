<?php
namespace Admin\Log;

use Admin\Admin;
use Admin\Utils\Arrays;

/**
 * The Action Data
 */
class Action {

    private static bool $loaded = false;

    /** @var array{} */
    private static array $data = [];


    /**
     * Loads the Actions Data
     * @return boolean
     */
    private static function load(): bool {
        if (self::$loaded) {
            return false;
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
        return true;
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
