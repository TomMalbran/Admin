<?php
namespace Admin\View;

use Admin\IO\Request;
use Admin\Config\Settings;
use Admin\Log\ActionLog;

/**
 * The Preferences View
 */
class Preferences {
    
    /**
     * Returns all the Preferences
     * @return array
     */
    public static function getAll(): array {
        $request = Settings::getAll();
        $result  = [];

        foreach ($request as $section => $row) {
            foreach ($row as $variable => $value) {
                $result["$section-$variable"] = $value;
            }
        }
        return $result;
    }
    
    /**
     * Returns all the Preferences from the given section
     * @param string $section
     * @return array
     */
    public static function getForSection(string $section): array {
        return Settings::getAll($section);
    }
    
    
    
    /**
     * Saves the all the Preferences
     * @param Request $request
     * @return void
     */
    public static function saveAll(Request $request): void {
        Settings::save($request->toArray());
        ActionLog::add("Preferences", "Save");
    }
    
    /**
     * Saves the given Preferences from the given section, if those are already on the DB
     * @param string $section
     * @param array  $data
     * @return void
     */
    public static function saveIntoSection(string $section, array $data): void {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields["$section-$key"] = $value;
        }
        Settings::save($fields);
        ActionLog::add("Preferences", "Save");
    }
}
