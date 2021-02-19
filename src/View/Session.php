<?php
namespace Admin\View;

use Admin\IO\View;
use Admin\IO\Request;
use Admin\IO\Response;
use Admin\IO\Errors;
use Admin\Auth\Auth;
use Admin\Auth\Credential;
use Admin\Auth\Reset;
use Admin\Auth\Spam;
use Admin\Config\Config;
use Admin\Log\ActionLog;
use Admin\Provider\Unsplash;
use Admin\Provider\Mailer;
use Admin\Utils\Strings;

/**
 * The Session View
 */
class Session {
    
    /**
     * Creates and returns the View
     * @return View
     */
    private static function getView(): View {
        return new View("session", "session/view");
    }

    /**
     * Returns a View
     * @param string  $template
     * @param Request $request
     * @param array   $result   Optional.
     * @param Errors  $errors   Optional.
     * @return Response
     */
    private static function showView(string $template, Request $request, array $result = [], Errors $errors = null): Response {
        $isAjax   = $request->has("ajax");
        $isReload = $request->has("reload");

        if ($isReload || (!$isReload && !$isAjax)) {
            $image = Unsplash::getImage();
            if (!empty($image)) {
                $result["bgImage"] = $image["url"];
                $result["bgAlt"]   = $image["location"];
            }
        }
        return self::getView()->create($template, $request, $result, null, $errors);
    }
    
    
    
    /**
     * Shows the login form
     * @param Request $request
     * @return Response
     */
    public static function getAll(Request $request): Response {
        return self::showView("login", $request);
    }
    
    /**
     * Logins the user
     * @param Request $request
     * @return Response
     */
    public static function login(Request $request): Response {
        $redirectUrl = str_replace([ "?ajax=1", "&ajax=1" ], "", $request->redirectUrl);
        if (Auth::isLoggedIn()) {
            return Response::reload($redirectUrl);
        }
        
        $errors = new Errors();
        if (Spam::protection()) {
            $errors->add("spam");
        } elseif (!$request->has("email")) {
            $errors->add("email");
        } elseif (!Strings::contains($request->email, "|") && !$request->isValidEmail("email")) {
            $errors->add("email");
        } elseif (!$request->has("password")) {
            $errors->add("password");
        } else {
            $credential = Auth::getLoginCredential($request->email);
            if (!Auth::canLogin($credential)) {
                $errors->add("credentials");
            } elseif (Auth::isLoginDisabled($credential)) {
                $errors->add("disabled");
            } elseif (!Credential::isPasswordCorrect($credential, $request->password)) {
                $errors->add("credentials");
            }
        }
        
        if ($errors->has()) {
            return self::showView("login", $request, [
                "email"       => trim($request->email),
                "redirectUrl" => $redirectUrl,
            ], $errors);
        }

        Auth::login($credential);
        ActionLog::add("Session", "Login");
        return Response::reload($redirectUrl);
    }
    
    /**
     * Logins as an User
     * @param integer $credentialID
     * @return Response
     */
    public static function loginAs(int $credentialID): Response {
        if (Auth::loginAs($credentialID)) {
            ActionLog::add("Session", "LoginAs", $credentialID);
        }
        return Response::reload();
    }

    /**
     * Logouts the User
     * @param Request $request
     * @return Response
     */
    public static function logout(Request $request): Response {
        $redirectUrl = Config::getRoute($request->redirectUrl);
        ActionLog::add("Session", "Logout");
        Auth::logout();
        return Response::reload()->withParam("redirectUrl", $redirectUrl);
    }
    
    
    
    /**
     * Shows the remember password form
     * @param Request $request
     * @return Response
     */
    public static function remember(Request $request): Response {
        if (Auth::isLoggedIn()) {
            return Response::reload();
        }
        if (!$request->has("post")) {
            return self::showView("remember", $request);
        }
        if ($request->has("email")) {
            $credential = Credential::getByEmail($request->email);
            if (!$credential->isEmpty()) {
                $resetCode = Reset::create($credential->id);
                Mailer::sendReset($request->email, $resetCode);
                ActionLog::add("Session", "RequestReset");
                return Response::redirect("session/code");
            }
        }
        $errors = new Errors("email");
        return self::showView("remember", $request, [], $errors);
    }
    
    /**
     * Shows the Remember Code form
     * @param Request $request
     * @return Response
     */
    public static function code(Request $request): Response {
        if (Auth::isLoggedIn()) {
            return Response::reload();
        }
        if (!$request->has("post")) {
            return self::showView("code", $request);
        }
        if ($request->has("resetCode") && Reset::codeExists($request->resetCode)) {
            return Response::redirect("session/reset")->withData("resetCode", trim($request->resetCode));
        }
        $errors = new Errors("resetCode");
        return self::showView("code", $request, [], $errors);
    }
    
    /**
     * Shows the Reset Password form
     * @param Request $request
     * @return Response
     */
    public static function reset(Request $request): Response {
        if (Auth::isLoggedIn()) {
            return Response::reload();
        }
        if (!$request->has("post")) {
            return self::showView("reset", $request);
        }
        
        $errors = new Errors();
        if (!$request->has("password") || !$request->isValidPassword("password")) {
            $errors->add("password");
        }
        if (!$request->has("resetCode") || !Reset::codeExists($request->resetCode)) {
            $errors->add("resetCode");
        }
        if ($errors->has()) {
            $request->remove("password");
            return self::showView("reset", $request, [], $errors);
        }
        
        $credentialID = Reset::getCredentialID($request->resetCode);
        Credential::setPassword($credentialID, $request->password);
        Reset::delete($credentialID);
        ActionLog::add("Session", "ResetPass");
        return Response::redirect("session")->withSuccess("reset");
    }

    
    
    /**
     * Views a Session
     * @param Request $request
     * @return Response
     */
    public static function view(Request $request): Response {
        $credential = Credential::getOne(Auth::getID());
        return self::getView()->create("edit", $request, [], $credential);
    }
    
    /**
     * Edits the Session
     * @param Request $request
     * @return Response
     */
    public static function edit(Request $request): Response {
        $errors = new Errors();
        
        if (!$request->has("firstName")) {
            $errors->add("firstName");
        }
        if (!$request->has("lastName")) {
            $errors->add("lastName");
        }
        if (!$request->isValidEmail("email") || Credential::emailExists($request->email, Auth::getID())) {
            $errors->add("email");
        }
        if ($request->has("password") && !$request->isValidPassword("password")) {
            $errors->add("password");
        }
        
        if ($errors->has()) {
            $request->remove("password");
            return self::getView()->create("edit", $request, [], null, $errors);
        }
        Credential::edit(Auth::getID(), $request);
        ActionLog::add("Session", "Edit");
        return self::getView()->success($request, "edit");
    }
}
