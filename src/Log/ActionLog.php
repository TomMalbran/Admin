<?php
namespace Admin\Log;

use Admin\IO\Request;
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
    
    private static $loaded       = false;
    private static $logIDs       = null;
    private static $logSessions  = null;
    private static $logActions   = null;
    private static $credentialID = 0;
    
    
    /**
     * Loads the Action Schemas
     * @return void
     */
    public static function load(): void {
        if (!self::$loaded) {
            self::$logIDs      = Factory::getSchema("logIDs");
            self::$logSessions = Factory::getSchema("logSessions");
            self::$logActions  = Factory::getSchema("logActions");
        }
    }

    /**
     * Loads the IDs Schemas
     * @return Schema
     */
    public static function getIDsSchema(): Schema {
        self::load();
        return self::$logIDs;
    }

    /**
     * Loads the Sessions Schemas
     * @return Schema
     */
    public static function getSessionsSchema(): Schema {
        self::load();
        return self::$logSessions;
    }

    /**
     * Loads the Actions Schemas
     * @return Schema
     */
    public static function getActionsSchema(): Schema {
        self::load();
        return self::$logActions;
    }



    /**
     * Returns the Filter Query
     * @param Request $filters
     * @return Query
     */
    private static function getFilterQuery(Request $filters): Query {
        $query = new Query();
        $query->addIf("CREDENTIAL_ID", "=", $filters->credentialID);
        if ($filters->has("fromTime") && $filters->has("toTime")) {
            $query->betweenTimes("time", $filters->fromTime, $filters->toTime);
        }
        return $query;
    }

    /**
     * Returns all the Actions Log filtered by the given filters
     * @param Request $filters
     * @param Request $sort
     * @return array
     */
    public static function filter(Request $filters, Request $sort): array {
        $query = self::getFilterQuery($filters);
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
        $query = self::getFilterQuery($filters);
        return self::getSessionsSchema()->getTotal($query);
    }

    /**
     * Returns the Actions Log using the given Query
     * @param Query $query
     * @return array
     */
    private static function request(Query $query): array {
        $sessionIDs = self::getSessionsSchema()->getColumn($query, "SESSION_ID");
        $querySess  = Query::create("SESSION_ID", "IN", $sessionIDs)->orderBy("time", false);
        $queryActs  = Query::create("SESSION_ID", "IN", $sessionIDs)->orderBy("time", true);
        $actions    = [];
        $result     = [];
        
        if (empty($sessionIDs)) {
            return [];
        }
        
        $request = self::getActionsSchema()->getMap($queryActs);
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
        
        $request = self::getSessionsSchema()->getMap($querySess);
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
     * @return void
     */
    public static function startSession(int $credentialID, bool $destroy = false): void {
        self::$credentialID = $credentialID;
        $sessionID = self::getSessionID();
        
        if ($destroy || empty($sessionID)) {
            $sessionID = self::getSessionsSchema()->create([
                "CREDENTIAL_ID" => $credentialID,
                "ip"            => Server::getIP(),
                "userAgent"     => Server::getUserAgent(),
                "time"          => time(),
            ]);
            self::setSessionID($sessionID);
        }
    }
    
    /**
     * Ends the Log Session
     * @return void
     */
    public static function endSession(): void {
        self::$credentialID = 0;
        self::setSessionID();
    }
    


    /**
     * Logs the given Action
     * @param string            $section
     * @param string            $action
     * @param integer|integer[] $dataID  Optional.
     * @return void
     */
    public static function add(string $section, string $action, $dataID = ""): void {
        $sessionID = self::getSessionID();
        if (!empty($sessionID)) {
            $dataID = Arrays::toArray($dataID);
            foreach ($dataID as $index => $value) {
                $dataID[$index] = (int)$value;
            }
            
            self::getActionsSchema()->create([
                "SESSION_ID"    => $sessionID,
                "CREDENTIAL_ID" => self::$credentialID,
                "section"       => $section,
                "action"        => $action,
                "dataID"        => JSON::encode($dataID),
                "time"          => time(),
            ]);
        }
    }

    /**
     * Returns the Session ID for the current Credential
     * @return integer
     */
    public static function getSessionID(): int {
        $query = Query::create("CREDENTIAL_ID", "=", self::$credentialID);
        return (int)self::getIDsSchema()->getValue($query, "SESSION_ID");
    }

    /**
     * Sets the given Session ID for the current Credential
     * @param integer $sessionID Optional.
     * @return void
     */
    public static function setSessionID(int $sessionID = 0): void {
        self::getIDsSchema()->replace([
            "CREDENTIAL_ID" => self::$credentialID,
            "SESSION_ID"    => $sessionID,
        ]);
    }
}
