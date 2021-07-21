<?php
namespace Admin\View;

use Admin\Admin;
use Admin\IO\View;
use Admin\IO\Request;
use Admin\IO\Response;
use Admin\IO\Errors;
use Admin\Config\Settings;
use Admin\Log\ActionLog;

/**
 * The Personalize View
 */
class Personalize {

    /**
     * Creates and returns the View
     * @return View
     */
    private static function getView(): View {
        return new View("view", "personalize", "personalize");
    }

    /**
     * Returns the Option Items
     * @param array $settings
     * @param array $errors   Optional.
     * @return array
     */
    private static function getOptions(array $settings, array $errors = []): array {
        $sections = Admin::loadData(Admin::PersonalizeData);
        $result   = [];
        foreach ($sections as $section) {
            $isFirst = true;
            foreach ($section["options"] as $option) {
                $key    = $option["key"];
                $fields = [
                    "title"      => $isFirst ? $section["title"] : "",
                    "isText"     => $option["type"] == "text",
                    "isNumber"   => $option["type"] == "number",
                    "isTextarea" => $option["type"] == "textarea",
                    "isImage"    => $option["type"] == "image",
                    "value"      => !empty($settings[$key]) ? $settings[$key] : "",
                ] + $option;
                if (!empty($errors[$key])) {
                    $fields += $errors[$key];
                }
                $result[] = $fields;
                $isFirst  = false;
            }
        }
        return $result;
    }



    /**
     * Shows the Personalize page
     * @param Request $request
     * @return Response
     */
    public static function getAll(Request $request): Response {
        $settings = Settings::getAllFlat();
        return self::getView()->create("personalize", $request, [
            "options" => self::getOptions($settings),
        ]);
    }

    /**
     * Saves the Personalize options
     * @param Request $request
     * @return Response
     */
    public static function save(Request $request) {
        $sections = Admin::loadData(Admin::PersonalizeData);
        $errors   = new Errors();

        foreach ($sections as $section) {
            $isFirst = true;
            foreach ($section["options"] as $option) {
                $key = $option["key"];
                if ($option["isRequired"] && !$request->has($key)) {
                    $errors->add($key, "empty");
                } elseif ($option["type"] == "image") {
                    if (!$request->isValidImage($key)) {
                        $errors->add($key, "type");
                    } elseif (!$request->fileExists($key)) {
                        $errors->add($key, "exists");
                    }
                } elseif ($option["type"] == "number") {
                    if (!$request->isNumeric($key)) {
                        $errors->add($key, "number");
                    }
                }
            }
        }
        if ($errors->has()) {
            return self::getView()->create("personalize", $request, [
                "options" => self::getOptions($request->toArray(), $errors->getObject()),
            ]);
        }

        Settings::save($request->toArray());
        ActionLog::add("Personalize", "Save");
        return self::getView()->success($request, "save");
    }
}
