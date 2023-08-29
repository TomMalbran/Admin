<?php
namespace Admin\Log;

use Admin\IO\Request;
use Admin\Auth\Auth;
use Admin\Log\Action;
use Admin\Schema\Factory;
use Admin\Schema\Schema;
use Admin\Schema\Query;
use Admin\Utils\Arrays;
use Admin\Utils\JSON;
use Admin\Utils\Server;

/**
 * The Actions Log
 */
class ActionLog {

    /**
     * Loads the IDs Schemas
     * @return Schema
     */
    public static function idSchema(): Schema {
        return Factory::getSchema("logIDs");
    }

    /**
     * Loads the Sessions Schemas
     * @return Schema
     */
    public static function sessionSchema(): Schema {
        return Factory::getSchema("logSessions");
    }

    /**
     * Loads the Actions Schemas
     * @return Schema
     */
    public static function actionSchema(): Schema {
        return Factory::getSchema("logActions");
    }



    /**
     * Returns the Filter Query
     * @param Request $filters
     * @return Query
     */
    private static function createQuery(Request $filters): Query {
        $query = new Query();
        $query->addIf("CREDENTIAL_ID", "=", $filters->credentialID);
        if ($filters->has("fromTime") && $filters->has("toTime")) {
            $query->betweenTimes("time", $filters->fromTime, $filters->toTime);
        }
        return $query;
    }

    /**
     * Returns all the Actions Log items
     * @param Request $filters
     * @param Request $sort
     * @return mixed[]
     */
    public static function getAll(Request $filters, Request $sort): array {
        $query = self::createQuery($filters);
        $query->orderBy("time", false);
        $query->paginate($sort->page, $sort->amount);
        return self::request($query);
    }

    /**
     * Returns the Total Actions Log with the given Filters
     * @param Request $filters
     * @return integer
     */
    public static function getTotal(Request $filters): int {
        $query = self::createQuery($filters);
        return self::sessionSchema()->getTotal($query);
    }

    /**
     * Returns the Actions Log using the given Query
     * @param Query $query
     * @return mixed[]
     */
    private static function request(Query $query): array {
        $sessionIDs   = self::sessionSchema()->getColumn($query, "SESSION_ID");
        $querySession = Query::create("SESSION_ID", "IN", $sessionIDs)->orderBy("time", false);
        $queryActs    = Query::create("SESSION_ID", "IN", $sessionIDs)->orderBy("time", true);
        $actions      = [];
        $result       = [];

        if (empty($sessionIDs)) {
            return [];
        }

        $request = self::actionSchema()->getMap($queryActs);
        foreach ($request as $row) {
            if (empty($actions[$row["sessionID"]])) {
                $actions[$row["sessionID"]] = [];
            }
            $actions[$row["sessionID"]][] = [
                "time"    => $row["time"],
                "section" => Action::getSection($row["section"]),
                "action"  => Action::getAction($row["section"], $row["action"]),
                "dataID"  => !empty($row["dataID"]) ? JSON::decode($row["dataID"]) : "",
            ];
        }

        $request = self::sessionSchema()->getMap($querySession);
        foreach ($request as $row) {
            $result[] = [
                "sessionID"      => $row["sessionID"],
                "credentialID"   => $row["credentialID"],
                "credentialName" => $row["credentialName"],
                "time"           => $row["time"],
                "date"           => $row["timeFull"],
                "ip"             => $row["ip"],
                "userAgent"      => $row["userAgent"],
                "actions"        => !empty($actions[$row["sessionID"]]) ? $actions[$row["sessionID"]] : [],
            ];
        }

        return $result;
    }



    /**
     * Starts a Log Session
     * @param integer $credentialID
     * @param boolean $destroy      Optional.
     * @return boolean
     */
    public static function startSession(int $credentialID, bool $destroy = false): bool {
        $sessionID = self::getSessionID();

        if ($destroy || empty($sessionID)) {
            $sessionID = self::sessionSchema()->create([
                "CREDENTIAL_ID" => $credentialID,
                "ip"            => Server::getIP(),
                "userAgent"     => Server::getUserAgent(),
                "time"          => time(),
            ]);
            return self::setSessionID($sessionID);
        }
        return false;
    }

    /**
     * Ends the Log Session
     * @return boolean
     */
    public static function endSession(): bool {
        return self::setSessionID();
    }



    /**
     * Logs the given Action
     * @param string        $section
     * @param string        $action
     * @param mixed|integer $dataID  Optional.
     * @return boolean
     */
    public static function add(string $section, string $action, mixed $dataID = 0): bool {
        $sessionID = self::getSessionID();
        if (empty($sessionID)) {
            return false;
        }

        $dataID = Arrays::toArray($dataID);
        foreach ($dataID as $index => $value) {
            $dataID[$index] = (int)$value;
        }

        self::actionSchema()->create([
            "SESSION_ID"    => $sessionID,
            "CREDENTIAL_ID" => Auth::getID(),
            "section"       => $section,
            "action"        => $action,
            "dataID"        => JSON::encode($dataID),
            "time"          => time(),
        ]);
        return true;
    }

    /**
     * Returns the Session ID for the current Credential
     * @return integer
     */
    public static function getSessionID(): int {
        $query = Query::create("CREDENTIAL_ID", "=", Auth::getID());
        return (int)self::idSchema()->getValue($query, "SESSION_ID");
    }

    /**
     * Sets the given Session ID for the current Credential
     * @param integer $sessionID Optional.
     * @return boolean
     */
    public static function setSessionID(int $sessionID = 0): bool {
        self::idSchema()->replace([
            "CREDENTIAL_ID" => Auth::getID(),
            "SESSION_ID"    => $sessionID,
        ]);
        return true;
    }
}
