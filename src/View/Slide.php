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

/**
 * The Slide View
 */
class Slide {

    private static $loaded  = false;
    private static $schema  = null;
    private static $options = null;


    /**
     * Loads the Slides Schema
     * @return Schema
     */
    public static function getSchema(): Schema {
        if (!self::$loaded) {
            self::$loaded = false;
            self::$schema = Factory::getSchema("slides");
        }
        return self::$schema;
    }

    /**
     * Creates and returns the View
     * @return View
     */
    private static function getView(): View {
        return new View("slides", "slides", "slides");
    }

    /**
     * Returns the Slide Options
     * @param boolean $asArray Optional.
     * @return mixed
     */
    private static function getOptions(bool $asArray = true) {
        return Admin::loadData(Admin::SlideData, "admin", $asArray);
    }



    /**
     * Returns the Slides list view
     * @param Request $request
     * @return Response
     */
    public static function getAll(Request $request): Response {
        $list = self::getSchema()->getAll();
        return self::getView()->create("main", $request, [
            "list"    => $list,
            "hasList" => !empty($list),
        ]);
    }

    /**
     * Returns the Active Slides
     * @return Response
     */
    public static function getActive() {
        $query = Query::create("status", "=", Status::Active);
        $list  = self::getSchema()->getAll($query);
        return Response::json($list);
    }

    /**
     * Returns the view for a single Slide
     * @param integer $slideID
     * @param Request $request
     * @return Response
     */
    public static function getOne(int $slideID, Request $request): Response {
        $slide = self::getSchema()->getOne($slideID);
        return self::getView()->create("view", $request, [], $slide);
    }

    /**
     * Returns the Slide create view
     * @param Request $request
     * @return Response
     */
    public static function create(Request $request): Response {
        $options = self::getOptions();
        return self::getView()->create("edit", $request, [
            "statuses" => Status::getSelect(),
        ] + $options);
    }

    /**
     * Returns the Slide edit view
     * @param integer $slideID
     * @param Request $request
     * @return Response
     */
    public static function edit(int $slideID, Request $request): Response {
        $options = self::getOptions();
        $slide   = self::getSchema()->getOne($slideID);
        return self::getView()->create("edit", $request, [
            "isEdit"   => true,
            "statuses" => Status::getSelect($slide->status),
        ] + $options, $slide);
    }



    /**
     * Creates/Edits a Slide
     * @param Request $request
     * @return Response
     */
    public static function process(Request $request): Response {
        $options = self::getOptions(false);
        $schema  = self::getSchema();
        $isEdit  = $request->has("slideID");
        $slideID = $request->getInt("slideID");
        $errors  = new Errors();

        if ($isEdit && !$schema->exists($slideID)) {
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
                "statuses" => Status::getSelect($request->getInt("status")),
            ], null, $errors);
        }

        if (!$isEdit) {
            $slideID = $schema->createWithOrder($request);
            ActionLog::add("Slide", "Create", $slideID);
        } else {
            $schema->editWithOrder($slideID, $request);
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
        $success = false;
        if ($request->has("confirmed") && self::getSchema()->deleteWithOrder($slideID)) {
            ActionLog::add("Slide", "Delete", $slideID);
            $success = true;
        }
        return self::getView()->delete($request, $success, $slideID);
    }
}
