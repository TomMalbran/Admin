<?php
namespace Admin\Auth;

use Admin\IO\Request;
use Admin\IO\Status;
use Admin\Auth\Access;
use Admin\File\Path;
use Admin\Schema\Factory;
use Admin\Schema\Schema;
use Admin\Schema\Model;
use Admin\Schema\Query;
use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * The Auth Credential
 */
class Credential {

    /**
     * Returns the Credential Schema
     * @return Schema
     */
    private static function schema(): Schema {
        return Factory::getSchema("credentials");
    }



    /**
     * Returns the Credential with the given ID
     * @param integer $credentialID
     * @param boolean $complete     Optional.
     * @return Model
     */
    public static function getOne(int $credentialID, bool $complete = false): Model {
        $query = Query::create("CREDENTIAL_ID", "=", $credentialID);
        return self::requestOne($query, $complete);
    }

    /**
     * Returns the Credential with the given Email
     * @param string  $email
     * @param boolean $complete Optional.
     * @return Model
     */
    public static function getByEmail(string $email, bool $complete = true): Model {
        $query = Query::create("email", "=", $email);
        return self::requestOne($query, $complete);
    }



    /**
     * Returns true if there is an Credential with the given ID
     * @param integer $credentialID
     * @return boolean
     */
    public static function exists(int $credentialID): bool {
        return self::schema()->exists($credentialID);
    }

    /**
     * Returns true if there is an Credential with the given Email
     * @param string  $email
     * @param integer $skipID Optional.
     * @return boolean
     */
    public static function emailExists(string $email, int $skipID = 0): bool {
        $query = Query::create("email", "=", $email);
        $query->addIf("CREDENTIAL_ID", "<>", $skipID);
        return self::schema()->exists($query);
    }



    /**
     * Returns all the Credentials
     * @param Request|null $sort Optional.
     * @return mixed[]
     */
    public static function getAll(?Request $sort = null): array {
        return self::request(null, false, $sort);
    }

    /**
     * Requests data to the database
     * @param Query|null   $query    Optional.
     * @param boolean      $complete Optional.
     * @param Request|null $sort     Optional.
     * @return mixed[]
     */
    private static function request(?Query $query = null, bool $complete = false, ?Request $sort = null): array {
        $request = self::schema()->getAll($query, $sort);
        $result  = [];

        foreach ($request as $row) {
            $fields = $row;
            $fields["credentialName"] = self::createName($row);
            $fields["gravatar"]       = self::getGravatar($row["email"]);
            $fields["levelName"]      = Access::getName($row["level"]);
            $fields["statusName"]     = Status::getName($row["status"]);

            if (!empty($row["avatar"]) && Path::exists("avatars", $row["avatar"])) {
                $fields["avatarFile"] = $row["avatar"];
                $fields["avatar"]     = Path::getUrl("avatars", $row["avatar"]);
            }
            if (!$complete) {
                unset($fields["password"]);
                unset($fields["salt"]);
            }
            $result[] = $fields;
        }
        return $result;
    }

    /**
     * Requests a single row from the database
     * @param Query|null $query    Optional.
     * @param boolean    $complete Optional.
     * @return Model
     */
    private static function requestOne(?Query $query = null, bool $complete = false): Model {
        $request = self::request($query, $complete);
        return self::schema()->getModel($request);
    }



    /**
     * Returns the total amount of Credentials
     * @return integer
     */
    public static function getTotal(): int {
        return self::schema()->getTotal();
    }

    /**
     * Returns a select of all the Credentials
     * @param integer $selectedID Optional.
     * @return mixed[]
     */
    public static function getSelect(int $selectedID = 0): array {
        $request = self::schema()->getMap();
        $result  = [];

        foreach ($request as $row) {
            $result[] = [
                "key"        => $row["credentialID"],
                "value"      => self::createName($row),
                "isSelected" => $row["credentialID"] == $selectedID,
            ];
        }
        return $result;
    }

    /**
     * Returns true if the given password is correct for the given Credential ID
     * @param Model  $credential
     * @param string $password
     * @return boolean
     */
    public static function isPasswordCorrect(Model $credential, string $password): bool {
        $hash = self::createHash($password, $credential->salt);
        return $hash["password"] == $credential->password;
    }



    /**
     * Creates a new Credential
     * @param Request $request
     * @param integer $status
     * @param integer $level
     * @return integer
     */
    public static function create(Request $request, int $status, int $level): int {
        $fields = self::getFields($request, $status, $level);
        return self::schema()->create($request, $fields + [
            "lastLogin"    => time(),
            "currentLogin" => time(),
        ]);
    }

    /**
     * Edits the given Credential
     * @param integer      $credentialID
     * @param Request      $request
     * @param integer|null $status       Optional.
     * @param integer|null $level        Optional.
     * @return boolean
     */
    public static function edit(int $credentialID, Request $request, ?int $status = null, ?int $level = null): bool {
        $fields = self::getFields($request, $status, $level);
        return self::schema()->edit($credentialID, $fields);
    }

    /**
     * Deletes the given Credential
     * @param integer $credentialID
     * @return boolean
     */
    public static function delete(int $credentialID): bool {
        return self::schema()->delete($credentialID);
    }

    /**
     * Parses the data and returns the fields
     * @param Request      $request
     * @param integer|null $status  Optional.
     * @param integer|null $level   Optional.
     * @return array{}
     */
    private static function getFields(Request $request, ?int $status = null, ?int $level = null): array {
        $result = [
            "firstName" => $request->firstName,
            "lastName"  => $request->lastName,
            "email"     => $request->email,
        ];
        if ($request->has("password")) {
            $hash = self::createHash($request->password);
            $result["password"] = $hash["password"];
            $result["salt"]     = $hash["salt"];
        }
        if ($status !== null) {
            $fields["status"] = $status;
        }
        if ($level !== null) {
            $result["level"] = $level;
        }
        return $result;
    }



    /**
     * Updates the login time for the given Credential
     * @param integer $credentialID
     * @return boolean
     */
    public static function updateLoginTime(int $credentialID): bool {
        $query   = Query::create("CREDENTIAL_ID", "=", $credentialID);
        $current = self::schema()->getValue($query, "currentLogin");
        return self::schema()->edit($credentialID, [
            "lastLogin"    => $current,
            "currentLogin" => time(),
        ]);
    }

    /**
     * Sets the Credential Password
     * @param integer $credentialID
     * @param string  $password
     * @return array{}
     */
    public static function setPassword(int $credentialID, string $password): array {
        $hash = self::createHash($password);
        self::schema()->edit($credentialID, [
            "password" => $hash["password"],
            "salt"     => $hash["salt"],
        ]);
        return $hash;
    }



    /**
     * Returns the Gravatar Url
     * @param string $email
     * @param string $rating Optional.
     * @return string
     */
    public static function getGravatar(string $email, string $rating = "pg"): string {
        $email  = md5(strtolower(trim($email)));
        $result = "https://www.gravatar.com/avatar/$email?default=identicon&s=60&r=$rating";
        return $result;
    }

    /**
     * Creates a Hash and Salt (if required) for the the given Password
     * @param string $pass
     * @param string $salt Optional.
     * @return array{}
     */
    public static function createHash(string $pass, string $salt = ""): array {
        $salt = !empty($salt) ? $salt : Strings::random(50);
        $hash = base64_encode(hash_hmac("sha256", $pass, $salt, true));
        return [ "password" => $hash, "salt" => $salt ];
    }

    /**
     * Returns the Real Name for the given User
     * @param Model|array{} $data
     * @param string        $prefix Optional.
     * @return string
     */
    public static function createName(Model|array $data, string $prefix = ""): string {
        $id        = Arrays::getValue($data, "credentialID", "", $prefix);
        $firstName = Arrays::getValue($data, "firstName",    "", $prefix);
        $lastName  = Arrays::getValue($data, "lastName",     "", $prefix);
        $email     = Arrays::getValue($data, "email",        "", $prefix);
        $result    = "";

        if (!empty($firstName) && !empty($lastName)) {
            $result = "$firstName $lastName";
        } elseif (!empty($email)) {
            $result = Strings::makeBreakable($email);
        } elseif (!empty($id)) {
            $result = "#$id";
        }
        return $result;
    }

    /**
     * Seeds the Owner
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $password
     * @return boolean
     */
    public static function seedOwner(string $firstName, string $lastName, string $email, string $password): bool {
        $query = Query::create("email", "=", $email);
        if (!self::schema()->exists($query)) {
            $hash = self::createHash($password);
            self::schema()->create([
                "firstName"    => $firstName,
                "lastName"     => $lastName,
                "email"        => $email,
                "password"     => $hash["password"],
                "salt"         => $hash["salt"],
                "level"        => Access::Admin,
                "status"       => Status::Active,
                "lastLogin"    => time(),
                "currentLogin" => time(),
            ]);
            print("<br>Owner <i>$firstName</i> created<br>");
            return true;
        }

        print("<br>No <i>Owner</i> created<br>");
        return false;
    }
}
