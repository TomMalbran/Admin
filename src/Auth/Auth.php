<?php
namespace Admin\Auth;

use Admin\IO\Status;
use Admin\Auth\Access;
use Admin\Auth\Credential;
use Admin\Auth\Token;
use Admin\Auth\Reset;
use Admin\Config\Config;
use Admin\Config\Settings;
use Admin\File\Path;
use Admin\File\File;
use Admin\Log\ActionLog;
use Admin\Provider\JWT;
use Admin\Schema\Model;
use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * The Auth
 */
class Auth {

    private static $accessLevel  = 0;
    private static $credential   = null;
    private static $credentialID = 0;
    private static $adminID      = 0;
    private static $apiID        = 0;


    /**
     * Validates the Credential
     * @param string $token
     * @return boolean
     */
    public static function validateCredential(string $token): bool {
        Reset::deleteOld();
        if (!JWT::isValid($token)) {
            return false;
        }

        // Retrieve the Token data
        $data = JWT::getData($token);
        if (empty($data->credentialID)) {
            return false;
        }
        $credential = Credential::getOne($data->credentialID, true);
        if ($credential->isEmpty() || $credential->isDeleted) {
            return false;
        }

        // Set the Credential
        self::setCredential($credential, $data->adminID);

        // Start or reuse a log session
        if (self::isLoggedAsUser()) {
            ActionLog::startSession(self::$adminID);
        } else {
            ActionLog::startSession(self::$credentialID);
        }
        return true;
    }

    /**
     * Validates and Sets the auth as API
     * @param string $token
     * @return boolean
     */
    public static function validateAPI(string $token): bool {
        if (Token::isValid($token)) {
            self::$apiID       = Token::getOne($token)->id;
            self::$accessLevel = Access::API;
            return true;
        }
        return false;
    }

    /**
     * Validates and Sets the auth as API Internal
     * @return boolean
     */
    public static function validateInternal(): bool {
        self::$apiID       = "internal";
        self::$accessLevel = Access::API;
        return true;
    }



    /**
     * Logins the given Credential
     * @param Model $credential
     * @return void
     */
    public static function login(Model $credential): void {
        self::setCredential($credential, 0);

        Credential::updateLoginTime($credential->id);
        ActionLog::startSession($credential->id, true);

        $path = Path::getTempPath($credential->id, false);
        File::emptyDir($path);
        Reset::delete($credential->id);
    }

    /**
     * Logouts the Current Credential
     * @return void
     */
    public static function logout(): void {
        ActionLog::endSession();

        self::$accessLevel  = Access::General;
        self::$credential   = null;
        self::$credentialID = 0;
        self::$adminID      = 0;
        self::$apiID        = 0;
    }

    /**
     * Returns true if the Credential can login
     * @param Model $credential
     * @return boolean
     */
    public static function canLogin(Model $credential): bool {
        return (
            !$credential->isEmpty() && !$credential->isDeleted &&
            !empty($credential->password) && $credential->status == Status::Active
        );
    }

    /**
     * Returns true if the login is disabled for the given Credential
     * @param Model $credential
     * @return boolean
     */
    public static function isLoginDisabled(Model $credential): bool {
        return $credential->level < Access::Admin && Settings::get("loginDisabled");
    }



    /**
     * Logins as the given Credential from an Admin account
     * @param integer $credentialID
     * @return boolean
     */
    public static function loginAs(int $credentialID): bool {
        $admin = self::$credential;
        $user  = Credential::getOne($credentialID, true);

        if (self::canLoginAs($admin, $user)) {
            self::setCredential($user, $admin->id);
            return true;
        }
        return false;
    }

    /**
     * Logouts as the current Credential and logins back as the Admin
     * @return integer
     */
    public static function logoutAs(): int {
        if (!self::isLoggedAsUser()) {
            return 0;
        }
        $admin = Credential::getOne(self::$adminID, true);
        $user  = self::$credential;

        if (self::canLoginAs($admin, $user)) {
            self::setCredential($admin);
            return $user->id;
        }
        return 0;
    }

    /**
     * Returns the Credential to Login from the given Email
     * @param string $email
     * @return Model
     */
    public static function getLoginCredential(string $email): Model {
        $parts = Strings::split($email, "|");
        $user  = null;

        if (!empty($parts[0]) && !empty($parts[1])) {
            $admin = Credential::getByEmail($parts[0], true);
            $user  = Credential::getByEmail($parts[1], true);
            
            if (self::canLoginAs($admin, $user)) {
                $user->password = $admin->password;
                $user->salt     = $admin->salt;
                $user->adminID  = $admin->id;
            }
        } else {
            $user = Credential::getByEmail($email, true);
        }
        return $user;
    }

    /**
     * Returns true if the Admin can login as the User
     * @param Model $admin
     * @param Model $user
     * @return boolean
     */
    public static function canLoginAs(Model $admin, Model $user): bool {
        return (
            self::canLogin($admin) && !$user->isEmpty() && !$user->isDeleted &&
            $admin->level > $user->level && $admin->level >= Access::Admin
        );
    }



    /**
     * Sets the Credential
     * @param Model   $credential
     * @param integer $adminID    Optional.
     * @return void
     */
    public static function setCredential(Model $credential, int $adminID = 0): void {
        self::$credential   = $credential;
        self::$credentialID = $credential->id;
        self::$accessLevel  = $credential->level;
        self::$adminID      = $adminID;
    }

    /**
     * Creates and returns the JWT token
     * @return string
     */
    public static function getToken(): string {
        if (!self::hasCredential()) {
            return "";
        }
        return JWT::create(time(), [
            "accessLevel"   => self::$accessLevel,
            "credentialID"  => self::$credentialID,
            "adminID"       => self::$adminID,
            "email"         => self::$credential->email,
            "name"          => self::$credential->credentialName,
            "reqPassChange" => self::$credential->reqPassChange,
            "loggedAsUser"  => !empty(self::$adminID),
        ]);
    }



    /**
     * Returns the Credential Model
     * @return Model
     */
    public static function getCredential() {
        return self::$credential;
    }

    /**
     * Returns the Credential ID
     * @return integer
     */
    public static function getID(): int {
        return self::$credentialID;
    }

    /**
     * Returns the Admin ID
     * @return integer
     */
    public static function getAdminID(): int {
        return self::$adminID;
    }

    /**
     * Returns the Access Level
     * @return integer
     */
    public static function getAccessLevel(): int {
        return self::$accessLevel;
    }



    /**
     * Returns true if the User is Logged in
     * @return boolean
     */
    public static function isLoggedIn(): bool {
        return !empty(self::$credentialID) || !empty(self::$apiID);
    }

    /**
     * Returns true or false if the admin is logged as an user
     * @return boolean
     */
    public static function isLoggedAsUser(): bool {
        return !empty(self::$adminID);
    }

    /**
     * Returns true if there is a Credential
     * @return boolean
     */
    public static function hasCredential(): bool {
        return !empty(self::$credentialID);
    }

    /**
     * Returns true if the current Access is Editor
     * @return boolean
     */
    public static function isEditor(): bool {
        return self::$accessLevel == Access::Editor;
    }

    /**
     * Returns true if the current Access is Admin
     * @return boolean
     */
    public static function isAdmin(): bool {
        return self::$accessLevel == Access::Admin;
    }

    /**
     * Returns true if we are in API mode
     * @return boolean
     */
    public static function isAPI(): bool {
        return self::$accessLevel == Access::API;
    }



    /**
     * Returns true if the user has that level
     * @param integer $requested
     * @return boolean
     */
    public static function grant(int $requested): bool {
        if ($requested != Access::General && !self::isLoggedIn()) {
            return false;
        }
        if (self::isAPI()) {
            return $requested == Access::API;
        }
        return $requested == Access::General || self::$accessLevel >= $requested;
    }

    /**
     * Returns true if the user has that level
     * @param integer $requested
     * @return boolean
     */
    public static function requiresLogin(int $requested): bool {
        return $requested != Access::General && !self::isLoggedIn();
    }
}
