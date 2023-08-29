<?php
namespace Admin\View;

use Admin\Admin;
use Admin\IO\View;
use Admin\IO\Request;
use Admin\IO\Response;

/**
 * The Home View
 */
class Home {

    /**
     * Shows the home page
     * @param Request $request
     * @return Response
     */
    public static function getAll(Request $request): Response {
        $view = new View("view", "", "home");
        return $view->create("home", $request, [
            "actions" => self::getActions(),
        ]);
    }

    /**
     * Returns the Action Items
     * @return mixed[]
     */
    private static function getActions(): array {
        $items    = Admin::loadData(Admin::HomeData);
        $sections = Admin::getSections();
        $result   = [];

        foreach ($items as $item) {
            $result[] = [
                "actionUrl"  => $item["url"],
                "actionIcon" => $item["icon"],
                "actionName" => $item["name"],
            ];
        }
        foreach ($sections as $section) {
            if (!empty($section["home"])) {
                $result[] = [
                    "actionUrl"  => $section["url"],
                    "actionIcon" => $section["icon"],
                    "actionName" => $section["home"],
                ];
            }
        }
        return $result;
    }
}
