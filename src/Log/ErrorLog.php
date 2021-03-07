<?php
namespace Admin\Log;

use Admin\Admin;
use Admin\IO\Request;
use Admin\Schema\Factory;
use Admin\Schema\Schema;
use Admin\Schema\Query;
use Admin\Schema\Model;
use Admin\Utils\Strings;
use Admin\Utils\Utils;

/**
 * The Errors Log
 */
class ErrorLog {

    private static $loaded       = false;
    private static $schema       = null;
    private static $internalPath = "";
    private static $adminPath    = "";
    private static $maxLog       = 1000;


    /**
     * Initializes the Log
     * @return void
     */
    public static function init(): void {
        self::$internalPath = Admin::getPath("src", "internal");
        self::$adminPath    = Admin::getPath(Admin::SourceDir, "admin");
        set_error_handler("\\Admin\\Log\\ErrorLog::handler");
    }

    /**
     * Loads the Error Schema
     * @return Schema
     */
    public static function getSchema(): Schema {
        if (!self::$loaded) {
            self::$loaded = false;
            self::$schema = Factory::getSchema("logErrors");
        }
        return self::$schema;
    }



    /**
     * Returns an Error with the given Code
     * @param integer $errorID
     * @return Model
     */
    public static function getOne(int $errorID): Model {
        return self::getSchema()->getOne($errorID);
    }

    /**
     * Returns true if the given Error exists
     * @param integer $errorID
     * @return boolean
     */
    public static function exists(int $errorID): bool {
        return self::getSchema()->exists($errorID);
    }



    /**
     * Returns the Filter Query
     * @param Request $filters
     * @return Query
     */
    private static function getFilterQuery(Request $filters): Query {
        $query = new Query();
        $query->addIf("description", "LIKE", $filters->search);
        if ($filters->has("fromTime") && $filters->has("toTime")) {
            $query->betweenTimes("time", $filters->fromTime, $filters->toTime);
        }
        return $query;
    }

    /**
     * Returns all the Product Logs filtered by the given times
     * @param Request $filters
     * @param Request $sort
     * @return array
     */
    public static function filter(Request $filters, Request $sort): array {
        $query = self::getFilterQuery($filters);
        return self::getSchema()->getAll($query, $sort);
    }

    /**
     * Returns the Total Actions Log with the given Filters
     * @param Request $filters
     * @return integer
     */
    public static function getTotal(Request $filters): int {
        $query = self::getFilterQuery($filters);
        return self::getSchema()->getTotal($query);
    }

    /**
     * Marks an Error as Resolved
     * @param integer $errorID
     * @return boolean
     */
    public static function markResolved(int $errorID): bool {
        $schema = self::getSchema();
        if ($schema->exists($errorID)) {
            $schema->edit($errorID, [
                "isResolved" => 1,
            ]);
            return true;
        }
        return false;
    }



    /**
     * Handes the PHP Error
     * @param integer $code
     * @param string  $description
     * @param string  $file
     * @param integer $line
     * @return boolean
     */
    public static function handler(int $code, string $description, string $file = "", int $line = 0): bool {
        [ $error, $level ] = self::mapErrorCode($code);
        $schema      = self::getSchema();
        $description = Strings::replace($description, [ "'", "`" ], "");

        if (Strings::contains($file, self::$internalPath)) {
            $fileName = Strings::replace($file, self::$internalPath . "/", "Admin/");
        } else {
            $fileName = Strings::replace($file, self::$adminPath . "/", "");
        }

        $query = Query::create("code", "=", $code);
        $query->add("description", "=", $description);
        $query->addIf("file", "=", $fileName);
        $query->addIf("line", "=", $line);

        if ($schema->getTotal($query) > 0) {
            $query->orderBy("updatedTime", false)->limit(1);
            $schema->edit($query, [
                "amount"      => Query::inc(1),
                "updatedTime" => time(),
            ]);
        } else {
            $schema->create([
                "code"        => $code,
                "level"       => $level,
                "error"       => $error,
                "description" => $description,
                "file"        => $fileName,
                "line"        => $line,
                "amount"      => 1,
                "updatedTime" => time(),
            ]);

            $total = $schema->getTotal();
            if ($total > self::$maxLog) {
                $query = Query::createOrderBy("updatedTime", false);
                $query->limit($total - self::$maxLog);
                $schema->remove($query);
            }
        }
        return false;
    }

    /**
     * Map an error code into an Error word, and log location.
     * @param integer $code
     * @return array
     */
    public static function mapErrorCode(int $code): array {
        $error = "";
        $level = 0;

        switch ($code) {
            case E_PARSE:
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $error = "Fatal Error";
                $level = LOG_ERR;
                break;
            case E_WARNING:
            case E_USER_WARNING:
            case E_COMPILE_WARNING:
            case E_RECOVERABLE_ERROR:
                $error = "Warning";
                $level = LOG_WARNING;
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $error = "Notice";
                $log   = LOG_NOTICE;
                break;
            case E_STRICT:
                $error = "Strict";
                $level = LOG_NOTICE;
                break;
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $error = "Deprecated";
                $level = LOG_NOTICE;
                break;
            default:
                break;
        }
        return [ $error, $level ];
    }
}
