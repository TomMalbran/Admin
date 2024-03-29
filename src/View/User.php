<?php
namespace Admin\View;

use Admin\IO\View;
use Admin\IO\Request;
use Admin\IO\Response;
use Admin\IO\Errors;
use Admin\IO\Status;
use Admin\Auth\Credential;
use Admin\Auth\Access;
use Admin\Log\ActionLog;

/**
 * The User View
 */
class User {

    /**
     * Creates and returns the View
     * @return View
     */
    private static function view(): View {
        return new View("users", "users", "users");
    }



    /**
     * Returns the Users list view
     * @param Request $request
     * @return Response
     */
    public static function getAll(Request $request): Response {
        $list = Credential::getAll();
        return self::view()->create("main", $request, [
            "list"    => $list,
            "hasList" => !empty($list),
        ]);
    }

    /**
     * Returns the view for a single User
     * @param integer $credentialID
     * @param Request $request
     * @return Response
     */
    public static function getOne(int $credentialID, Request $request): Response {
        $credential = Credential::getOne($credentialID);
        return self::view()->create("view", $request, [], $credential);
    }

    /**
     * Returns the User create view
     * @param Request $request
     * @return Response
     */
    public static function create(Request $request): Response {
        return self::view()->create("edit", $request, [
            "levels"   => Access::getSelect(),
            "statuses" => Status::getSelect(),
        ]);
    }

    /**
     * Returns the User edit view
     * @param integer $credentialID
     * @param Request $request
     * @return Response
     */
    public static function edit(int $credentialID, Request $request): Response {
        $credential = Credential::getOne($credentialID);
        return self::view()->create("edit", $request, [
            "isEdit"   => true,
            "levels"   => Access::getSelect($credential->level),
            "statuses" => Status::getSelect($credential->status),
        ], $credential);
    }



    /**
     * Creates/Edits a User
     * @param Request $request
     * @return Response
     */
    public static function process(Request $request): Response {
        $isEdit       = $request->has("credentialID");
        $credentialID = $request->getInt("credentialID");
        $errors       = new Errors();

        if ($isEdit && !Credential::exists($credentialID)) {
            $errors->add("exists");
        } else {
            if (!Access::isValid($request->level)) {
                $errors->add("level");
            }
            if (!$request->has("firstName")) {
                $errors->add("firstName");
            }
            if (!$request->has("lastName")) {
                $errors->add("lastName");
            }
            if (!$request->has("email") || !$request->isValidEmail("email") || Credential::emailExists($request->email, $credentialID)) {
                $errors->add("email");
            }
            if (!$isEdit && !$request->has("password")) {
                $errors->add("password");
                $errors->add("passwordEmpty");
            } elseif ($request->has("password") && !$request->isValidPassword("password")) {
                $errors->add("password");
                $errors->add("passwordInvalid");
            }
            if (!$request->isValidStatus("status")) {
                $errors->add("status");
            }
        }

        if ($errors->has()) {
            return self::view()->create("edit", $request, [
                "isEdit"   => $isEdit,
                "levels"   => Access::getSelect($request->getInt("level")),
                "statuses" => Status::getSelect($request->getInt("status")),
            ], null, $errors);
        }

        if (!$isEdit) {
            $credentialID = Credential::create($request, $request->status, $request->level);
            ActionLog::add("User", "Create", $credentialID);
        } else {
            Credential::edit($credentialID, $request, $request->status, $request->level);
            ActionLog::add("User", "Edit", $credentialID);
        }
        return self::view()->edit($request, $isEdit, $credentialID);
    }

    /**
     * Deletes the given User
     * @param integer $credentialID
     * @param Request $request
     * @return Response
     */
    public static function delete(int $credentialID, Request $request): Response {
        $success = false;
        if ($request->has("confirmed") && Credential::delete($credentialID)) {
            ActionLog::add("User", "Delete", $credentialID);
            $success = true;
        }
        return self::view()->delete($request, $success, $credentialID);
    }
}
