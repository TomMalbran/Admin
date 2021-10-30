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

    private static $loaded   = false;
    private static $sections = [];
    private static $useTabs  = false;


    /**
     * Loads the Data
     * @return void
     */
    public static function load() {
        if (self::$loaded) {
            return;
        }
        $data = Admin::loadData(Admin::PersonalizeData);
        self::$loaded   = true;
        self::$sections = $data["sections"];
        self::$useTabs  = $data["useTabs"];
    }

    /**
     * Creates and returns the View
     * @param string $hash Optional.
     * @return View
     */
    private static function getView(string $hash = ""): View {
        $url = "personalize" . (!empty($hash) ? "#$hash" : "");
        return new View("view", $url, "personalize");
    }

    /**
     * Returns the Option Items
     * @param array $settings
     * @param array $errors   Optional.
     * @return array
     */
    private static function getOptions(array $settings, array $errors = []): array {
        self::load();
        $tabs       = [];
        $options    = [];
        $isSelected = true;

        foreach (self::$sections as $section) {
            $content = [];
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
                $options[] = $fields;
                $content[] = $fields;
                $isFirst   = false;
            }

            $tabs[] = [
                "key"        => $section["key"],
                "value"      => $section["title"],
                "isSelected" => $isSelected,
                "options"    => $content,
            ];
            $isSelected = false;
        }

        return [
            "useTabs" => self::$useTabs,
            "tabs"    => $tabs,
            "options" => $options,
        ];
    }



    /**
     * Shows the Personalize page
     * @param Request $request
     * @return Response
     */
    public static function getAll(Request $request): Response {
        $settings = Settings::getAllFlat();
        $options  = self::getOptions($settings);
        return self::getView()->create("personalize", $request, $options);
    }

    /**
     * Saves the Personalize options
     * @param Request $request
     * @return Response
     */
    public static function save(Request $request) {
        self::load();
        $errors = new Errors();

        foreach (self::$sections as $section) {
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
            $options = self::getOptions($request->toArray(), $errors->getObject());
            return self::getView($request->subsection)->create("personalize", $request, $options);
        }

        Settings::save($request->toArray());
        ActionLog::add("Personalize", "Save");
        return self::getView($request->subsection)->success($request, "save");
    }
}
