<?php
namespace Admin\View;

use Admin\Admin;
use Admin\IO\View;
use Admin\IO\Request;
use Admin\IO\Response;
use Admin\IO\Errors;
use Admin\IO\Status;
use Admin\Schema\Factory;
use Admin\Schema\Schema;
use Admin\Schema\Query;
use Admin\Log\ActionLog;
use Admin\Utils\Arrays;
use Admin\Utils\Numbers;

/**
 * The Slide View
 */
class Slide {

    private static $loaded   = false;
    private static $schema   = null;
    private static $options  = null;
    private static $useTabs  = false;
    private static $mainType = "";


    /**
     * Loads the Data
     * @return void
     */
    public static function load() {
        if (self::$loaded) {
            return;
        }
        self::$loaded   = true;
        self::$schema   = Factory::getSchema("slides");
        self::$options  = Admin::loadData(Admin::SlideData, "admin", true);
        self::$useTabs  = Arrays::length(self::$options) > 1;
        self::$mainType = Arrays::getFirstKey(self::$options);
    }

    /**
     * Creates a list of tabs
     * @param string $selected
     * @return array
     */
    private static function getTabs(string $selected): array {
        $result = [];
        foreach (self::$options as $key => $option) {
            $result[] = [
                "key"        => $key,
                "value"      => $option["name"],
                "isSelected" => $selected === $key,
            ];
        }
        return $result;
    }

    /**
     * Returns the Options for the given type
     * @param string  $type
     * @param boolean $asObject Optional.
     * @return mixed
     */
    private static function getOptions(string $type, bool $asObject = false) {
        $result = self::$options[$type]["options"];
        return $asObject ? (object)$result : $result;
    }

    /**
     * Creates and returns the View
     * @return View
     */
    private static function getView(): View {
        return new View("slides", "slides", "slides");
    }



    /**
     * Returns the Slides list view
     * @param Request $request
     * @return Response
     */
    public static function getAll(Request $request): Response {
        self::load();
        $slides = self::$schema->getAll();
        $tabs   = self::getTabs(self::$mainType);
        $lists  = [];

        foreach ($tabs as $tab) {
            $lists[$tab["key"]] = [
                "key"        => $tab["key"],
                "list"       => [],
                "hasList"    => false,
                "isSelected" => $tab["isSelected"],
            ];
        }
        foreach ($slides as $slide) {
            $lists[$slide["type"]]["list"][]  = $slide;
            $lists[$slide["type"]]["hasList"] = true;
        }

        return self::getView()->create("main", $request, [
            "useTabs" => self::$useTabs,
            "tabs"    => $tabs,
            "lists"   => Arrays::getValues($lists),
        ]);
    }

    /**
     * Returns the Active Slides
     * @param string $type Optional.
     * @return Response
     */
    public static function getActive(string $type = ""): Response {
        self::load();
        $query = Query::create("status", "=", Status::Active);
        $query->add("type", "=", !empty($type) ? $type : self::$mainType);

        $list  = self::$schema->getAll($query);
        $total = count($list);

        return Response::json([
            "list"       => $list,
            "amount"     => $total,
            "totalWidth" => $total * 100,
            "slideWidth" => Numbers::divide(100, $total),
        ]);
    }

    /**
     * Returns the view for a single Slide
     * @param integer $slideID
     * @param Request $request
     * @return Response
     */
    public static function getOne(int $slideID, Request $request): Response {
        self::load();
        $slide = self::$schema->getOne($slideID);
        return self::getView()->create("view", $request, [], $slide);
    }

    /**
     * Returns the Slide create view
     * @param string  $type
     * @param Request $request
     * @return Response
     */
    public static function create(string $type, Request $request): Response {
        self::load();
        return self::getView()->create("edit", $request, [
            "type"     => $type,
            "useTabs"  => self::$useTabs,
            "statuses" => Status::getSelect(),
        ] + self::getOptions($type));
    }

    /**
     * Returns the Slide edit view
     * @param integer $slideID
     * @param Request $request
     * @return Response
     */
    public static function edit(int $slideID, Request $request): Response {
        self::load();
        $slide = self::$schema->getOne($slideID);
        return self::getView()->create("edit", $request, [
            "isEdit"   => true,
            "type"     => $slide->type,
            "useTabs"  => self::$useTabs,
            "statuses" => Status::getSelect($slide->status),
        ] + self::getOptions($slide->type), $slide);
    }



    /**
     * Creates/Edits a Slide
     * @param Request $request
     * @return Response
     */
    public static function process(Request $request): Response {
        self::load();
        $isEdit  = $request->has("slideID");
        $slideID = $request->getInt("slideID");
        $type    = $request->getString("type");
        $options = self::getOptions($type, true);
        $errors  = new Errors();

        if ($isEdit && !self::$schema->exists($slideID)) {
            $errors->add("exists");
        } else {
            if (!$request->has("name")) {
                $errors->add("name");
            }
            if ($options->hasTitle && $options->reqTitle && !$request->has("title")) {
                $errors->add("title");
            }
            if ($options->hasDescription && $options->reqDescription && !$request->has("description")) {
                $errors->add("description");
            }
            if ($options->hasButton && $options->reqButton && !$request->has("button")) {
                $errors->add("button");
            }
            if ($options->hasLink && $options->reqLink && !$request->has("link")) {
                $errors->add("link");
            }
            if ($options->hasColor && $options->reqColor && !$request->has("color")) {
                $errors->add("color");
            }

            if (!$request->isValidStatus("status")) {
                $errors->add("status");
            }
            if (!$request->isValidPosition("position")) {
                $errors->add("position");
            }

            $request->validateImage("image", $errors);
            if ($options->hasLogo) {
                $request->validateImage("logo", $errors);
            }
        }

        if ($errors->has()) {
            return self::getView()->create("edit", $request, [
                "isEdit"   => $isEdit,
                "type"     => $type,
                "useTabs"  => self::$useTabs,
                "statuses" => Status::getSelect($request->getInt("status")),
            ] + (array)$options, null, $errors);
        }

        $query = Query::create("type", "=", $type);
        if (!$isEdit) {
            $slideID = self::$schema->createWithOrder($request, null, $query);
            ActionLog::add("Slide", "Create", $slideID);
        } else {
            self::$schema->editWithOrder($slideID, $request, null, $query);
            ActionLog::add("Slide", "Edit", $slideID);
        }
        return self::getView()->edit($request, $isEdit, $slideID);
    }

    /**
     * Deletes the given Slide
     * @param integer $slideID
     * @param Request $request
     * @return Response
     */
    public static function delete(int $slideID, Request $request): Response {
        self::load();
        $success = false;
        $slide   = self::$schema->getOne($slideID);
        if ($request->has("confirmed") && !$slide->isEmpty()) {
            $query = Query::create("type", "=", $sldie->type);
            self::$schema->deleteWithOrder($slideID, $query);
            ActionLog::add("Slide", "Delete", $slideID);
            $success = true;
        }
        return self::getView()->delete($request, $success, $slideID);
    }
}
