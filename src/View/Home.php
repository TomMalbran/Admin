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
        $view = new View("core", "", "home");
        return $view->create("home", $request, [
            "actions" => self::getActions(),
        ]);
    }

    /**
     * Returns the Action Items
     * @return array
     */
    private static function getActions(): array {
        $items  = Admin::loadData(Admin::HomeData);
        $result = [];
        foreach ($items as $item) {
            $result[] = [
                "actionKey"  => $item["key"],
                "actionUrl"  => $item["url"],
                "actionIcon" => $item["icon"],
                "actionName" => $item["name"],
            ];
        }
        return $result;
    }
}