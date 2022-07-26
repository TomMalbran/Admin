<?php
namespace Admin\Auth;

use Admin\Schema\Factory;
use Admin\Schema\Schema;
use Admin\Schema\Query;
use Admin\Utils\Strings;

/**
 * The Auth Reset
 */
class Reset {

    /**
     * Returns the Reset Schema
     * @return Schema
     */
    private static function schema(): Schema {
        return Factory::getSchema("resets");
    }



    /**
     * Returns the Credential ID for the given Code
     * @param string $code
     * @return integer
     */
    public static function getCredentialID(string $code): int {
        $query = Query::create("code", "=", $code);
        return self::schema()->getValue($query, "CREDENTIAL_ID");
    }

    /**
     * Returns true if the given code exists
     * @param string $code
     * @return boolean
     */
    public static function codeExists(string $code): bool {
        $query = Query::create("code", "=", trim($code));
        return self::schema()->exists($query);
    }



    /**
     * Creates and saves a recover code for the given Credential
     * @param integer $credentialID
     * @return string
     */
    public static function create(int $credentialID): string {
        $code = Strings::randomCode(6, "ud");
        self::schema()->replace([
            "CREDENTIAL_ID" => $credentialID,
            "code"          => $code,
            "time"          => time(),
        ]);
        return $code;
    }

    /**
     * Deletes the reset data for the given Credential
     * @param integer $credentialID
     * @return boolean
     */
    public static function delete(int $credentialID): bool {
        $query = Query::create("CREDENTIAL_ID", "=", $credentialID);
        return self::schema()->remove($query);
    }

    /**
     * Deletes the old reset data for all the Credentials
     * @return boolean
     */
    public static function deleteOld(): bool {
        $query = Query::create("time", "<", time() - 900);
        return self::schema()->remove($query);
    }
}
