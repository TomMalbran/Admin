<?php
namespace Admin\Schema;

use Admin\Schema\Query;
use Admin\Utils\Arrays;
use Admin\Utils\Strings;

use mysqli;
use mysqli_stmt;

/**
 * The mysqli Database Wrapper
 */
class Database {

    private mysqli $mysqli;

    public string $host;
    public string $username;
    public string $password;
    public string $database;
    public string $charset;


    /**
     * Creates a new Database instance
     * @param mixed $config Object[
     *    host     Usually localhost
     *    username Database username
     *    password Database password
     *    database Database name
     *    charset  Database charset
     * ].
     */
    public function __construct(mixed $config) {
        $this->host     = $config->host;
        $this->username = $config->username;
        $this->password = $config->password;
        $this->database = $config->database;
        $this->charset  = !empty($config->charset) ? $config->charset : "";

        $this->connect();
    }

    /**
     * Closes the connection
     */
    public function __destruct() {
        if (!empty($this->mysqli)) {
            $this->mysqli->close();
        }
    }



    /**
     * Connects with the database
     * @return boolean
     */
    public function connect(): bool {
        $this->mysqli = new mysqli($this->host, $this->username, $this->password, $this->database);
        if ($this->mysqli->connect_error) {
            trigger_error("Connect Error ({$this->mysqli->connect_errno}) {$this->mysqli->connect_error}", E_USER_ERROR);
            return false;
        }
        if (!empty($this->charset)) {
            $this->mysqli->set_charset($this->charset);
        }
        return false;
    }

    /**
     * Closes the connection
     * @return boolean
     */
    public function close(): bool {
        return $this->mysqli->close();
    }



    /**
     * Process the given query
     * @param string        $expression
     * @param Query|mixed[] $params     Optional.
     * @return array{}[]
     */
    public function query(string $expression, Query|array $params = []): array {
        $binds     = $params instanceof Query ? $params->params : $params;
        $statement = $this->processQuery($expression, $binds);
        return $this->dynamicBindResults($statement);
    }

    /**
     * Process the given expression using a Query
     * @param string $expression
     * @param Query  $query
     * @return array{}[]
     */
    public function getData(string $expression, Query $query): array {
        $expression .= $query->get(true);
        return $this->query($expression, $query->params);
    }

    /**
     * Selects the given columns from a single table and returns the result as an array
     * @param string          $table
     * @param string[]|string $columns Optional.
     * @param Query|null      $query   Optional.
     * @return array{}[]
     */
    public function getAll(string $table, array|string $columns = "*", ?Query $query = null): array {
        $selection  = Strings::join($columns, ", ");
        $expression = "SELECT $selection FROM $table ";
        $params     = [];

        if (!empty($query)) {
            $expression .= $query->get();
            $params      = $query->params;
        }
        return $this->query($expression, $params);
    }

    /**
     * Selects the given column from a single table and returns a single value
     * @param string $table
     * @param string $column
     * @param Query  $query
     * @return mixed
     */
    public function getValue(string $table, string $column, Query $query): mixed {
        $request = $this->getAll($table, $column, $query->limit(1));

        if (isset($request[0][$column])) {
            return $request[0][$column];
        }
        return "";
    }

    /**
     * Selects the given columns from a single table and returns the first row
     * @param string          $table
     * @param string[]|string $columns
     * @param Query           $query
     * @return array{}[]
     */
    public function getRow(string $table, array|string $columns, Query $query): array {
        $request = $this->getAll($table, $columns, $query->limit(1));

        if (isset($request[0])) {
            return $request[0];
        }
        return [];
    }

    /**
     * Selects the given column from a single table and returns the entire column
     * @param string $table
     * @param string $column
     * @param Query  $query
     * @return string[]
     */
    public function getColumn(string $table, string $column, Query $query): array {
        $request = $this->getAll($table, $column, $query);
        $result  = [];

        foreach ($request as $row) {
            if (!empty($row[$column]) && !Arrays::contains($result, $row[$column])) {
                $result[] = $row[$column];
            }
        }
        return $result;
    }



    /**
     * Returns true if the given Data is already in the given table
     * @param string $table
     * @param Query  $query
     * @return boolean
     */
    public function exists(string $table, Query $query): bool {
        return $this->getTotal($table, $query) == 1;
    }

    /**
     * Returns the Count in the given table
     * @param string $table
     * @param Query  $query
     * @return integer
     */
    public function getTotal(string $table, Query $query): int {
        $expression = "SELECT COUNT(*) AS cnt FROM `$table` " . $query->get();
        $request    = $this->query($expression, $query);

        if (isset($request[0]["cnt"])) {
            return (int)$request[0]["cnt"];
        }
        return 0;
    }

    /**
     * Returns the Sums of the given column in the given table
     * @param string $table
     * @param string $column
     * @param Query  $query
     * @return integer
     */
    public function getSum(string $table, string $column, Query $query): int {
        $expression = "SELECT COALESCE(SUM($column), 0) AS sum FROM `$table` "  . $query->get();
        $request    = $this->query($expression, $query);

        if (isset($request[0]["sum"])) {
            return (int)$request[0]["sum"];
        }
        return 0;
    }



    /**
     * Replaces or Inserts the given content into the given table
     * @param string    $table
     * @param array{}[] $fields
     * @param string    $method Optional.
     * @return integer The Inserted ID or -1
     */
    public function insert(string $table, array $fields, string $method = "INSERT"): int {
        $bindParams  = [];
        $expression  = "$method INTO `$table` ";
        $expression .= $this->buildInsertHeader($fields);
        $expression .= $this->buildTableData($fields, $bindParams, true);
        $statement   = $this->processQuery($expression, $bindParams);
        $result      = $statement->affected_rows > 0 ? $statement->insert_id : -1;
        $statement->close();
        return $result;
    }

    /**
     * Replaces or Inserts multiple rows
     * @param string  $table
     * @param array{} $fields
     * @param string  $method Optional.
     * @return boolean
     */
    public function batch(string $table, array $fields, string $method = "REPLACE"): bool {
        $bindParams  = [];
        $expression  = "$method INTO `$table` ";
        $expression .= $this->buildInsertHeader($fields[0]);

        $rows = [];
        foreach ($fields as $tableData) {
            $rows[] = $this->buildTableData($tableData, $bindParams, true);
        }

        $expression .= Strings::join($rows, ", ");
        $statement   = $this->processQuery($expression, $bindParams);

        return $statement->affected_rows > 0;
    }



    /**
     * Updates the content of the database based on the query and given fields
     * @param string    $table
     * @param array{}[] $fields
     * @param Query     $query
     * @return boolean
     */
    public function update(string $table, array $fields, Query $query): bool {
        $bindParams  = [];
        $expression  = "UPDATE `$table` SET ";
        $expression .= $this->buildTableData($fields, $bindParams, false);
        $expression .= " " . $query->get();
        $bindParams  = array_merge($bindParams, $query->params);
        $statement   = $this->processQuery($expression, $bindParams);
        return $this->closeQuery($statement);
    }

    /**
     * Updates a single value increasing it by the given amount
     * @param string  $table
     * @param string  $column
     * @param integer $amount
     * @param Query   $query
     * @return boolean
     */
    public function increase(string $table, string $column, int $amount, Query $query): bool {
        return $this->update($table, [ $column => Query::inc($amount) ], $query);
    }



    /**
     * Deletes from the given table
     * @param string $table
     * @param Query  $query
     * @return boolean
     */
    public function delete(string $table, Query $query): bool {
        $expression = "DELETE FROM `$table` " . $query->get();
        $statement  = $this->processQuery($expression, $query->params);

        return $statement->affected_rows > 0;
    }

    /**
     * Deletes from the given table
     * @param string $table
     * @return boolean
     */
    public function deleteAll(string $table): bool {
        $expression = "DELETE FROM `$table`";
        $statement  = $this->processQuery($expression, []);

        return $statement->affected_rows > 0;
    }

    /**
     * Truncates the given table
     * @param string $table
     * @return boolean
     */
    public function truncate(string $table): bool {
        $expression = "TRUNCATE TABLE `$table`";
        $statement  = $this->processQuery($expression, []);

        return ($statement->affected_rows > 0);
    }



    /**
     * Escape harmful characters which might affect a query
     * @param string $str The string to escape.
     * @return string The escaped string.
     */
    public function escape(string $str): string {
        return $this->mysqli->real_escape_string($str);
    }

    /**
     * Process a mysqli query
     * @param string  $expression
     * @param mixed[] $bindParams Optional.
     * @return mysqli_stmt|null
     */
    private function processQuery(string $expression, array $bindParams = []): ?mysqli_stmt {
        $query      = Strings::replace(trim($expression), "\n", "");
        $statement  = $this->mysqli->prepare($expression);

        if (!$statement) {
            trigger_error("Problem preparing query: {$this->mysqli->error} ($query)", E_USER_ERROR);
            return null;
        }

        if (Arrays::isArray($bindParams) && !empty($bindParams)) {
            $params = [ "" ]; // Create the empty 0 index
            foreach ($bindParams as $value) {
                $params[0] .= $this->determineType($value);
                array_push($params, $value);
            }
            call_user_func_array([ $statement, "bind_param" ], $this->refValues($params));
        }

        $statement->execute();
        if ($statement->error) {
            trigger_error("Problem executing query: {$statement->error} {$this->mysqli->error} ($query)", E_USER_ERROR);
            $statement->close();
            return null;
        }

        return $statement;
    }

    /**
     * Takes care of prepared statements' bind_result method, when the number of variables to pass is unknown.
     * @param mysqli_stmt $statement
     * @return boolean
     */
    private function closeQuery(mysqli_stmt $statement): bool {
        $result = $statement->affected_rows > 0;
        $statement->close();
        return $result;
    }

    /**
     * Replaces any parameter placeholders in a query with the value of that
     * parameter. Useful for debugging. Assumes anonymous parameters from
     * $params are are in the same order as specified in $query
     * @param string $expression
     * @param Query  $query
     * @return string
     */
    public function interpolateQuery(string $expression, Query $query): string {
        $expression = Strings::replace(trim($expression), "\n", "");
        $params     = $query->params;
        $keys       = [];
        $values     = [];

        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $keys[] = '/:' . $key . '/';
            } else {
                $keys[] = '/[?]/';
            }
            if (is_string($value)) {
                $values[] = "'$value'";
            } else {
                $values[] = $value;
            }
        }
        return preg_replace($keys, $values, $expression, 1);
    }

    /**
     * This method is used to prepare the statements by turning the item type into a type used by mysqli
     * @param mixed $item Input to determine the type.
     * @return string The parameter type.
     */
    private function determineType(mixed $item): string {
        return match (gettype($item)) {
            "NULL", "string"     => "s",
            "boolean", "integer" => "i",
            "blob"               => "b",
            "double"             => "d",
            default              => "",
        };
    }

    /**
     * This is required for PHP 5.3+
     * @param string[] $array
     * @return string[]
     */
    private function refValues(array $array): array {
        if (strnatcmp(phpversion(), "5.3") >= 0) {
            $refs = [];
            for ($i = 0; $i < count($array); $i++) {
                $refs[$i] = & $array[$i];
            }
            return $refs;
        }
        return $array;
    }

    /**
     * Takes care of prepared statements' bind_result method, when the number of variables to pass is unknown.
     * @param mysqli_stmt $statement
     * @return array{}[]
     */
    private function dynamicBindResults(mysqli_stmt $statement): array {
        $parameters = [];
        $results    = [];
        $meta       = $statement->result_metadata();

        // If $meta is false yet sqlstate is true, there's no sql error but the query is
        // most likely an update/insert/delete which doesn't produce any results
        if (!$meta && $statement->sqlstate) {
            return [];
        }

        $row = [];
        while ($field = $meta->fetch_field()) {
            $row[$field->name] = null;
            $parameters[]      = & $row[$field->name];
        }
        call_user_func_array([ $statement, "bind_result" ], $parameters);

        $statement->store_result();
        while ($statement->fetch()) {
            $x = [];
            foreach ($row as $key => $val) {
                $string  = (string)$val;
                $x[$key] = ctype_digit($string) && strrpos($string, "0", -strlen($string)) === false ? (int)$val : $val;
            }
            array_push($results, $x);
        }
        $statement->free_result();

        return $results;
    }

    /**
     * Builds the query for inserting or updating
     * @param array{}[] $fields
     * @return string
     */
    private function buildInsertHeader(array $fields): string {
        return "(`" . Strings::joinKeys($fields, "`, `") . "`) VALUES ";
    }

    /**
     * Process the table data for building the query for inserting or updating
     * @param array{}[] $fields
     * @param mixed[]   $bindParams
     * @param boolean   $isInsert
     * @return string
     */
    private function buildTableData(array $fields, array &$bindParams, bool $isInsert): string {
        $result = "";
        if ($isInsert) {
             $result .= "(";
        }

        foreach ($fields as $column => $value) {
            if (!$isInsert) {
                $result .= "`$column` = ";
            }

            if (!Arrays::isArray($value)) {
                $result .= "?, ";
                $bindParams[] = $value;
            } else {
                $key = key($value);
                $val = $value[$key];
                switch ($key) {
                case "[E]":
                    $result .= "`$val`, ";
                    break;
                case "[I]":
                    $result .= $column . $val . ", ";
                    break;
                case "[F]":
                    $result .= $val[0] . ", ";
                    if (!empty($val[1])) {
                        foreach ($val[1] as $v) {
                            $bindParams[] = $v;
                        }
                    }
                    break;
                case "[N]":
                    if ($val == null) {
                        $result .= "!$column, ";
                    } else {
                        $result .= "!$val, ";
                    }
                    break;
                default:
                    die("Wrong operation");
                }
            }
        }

        $result = rtrim($result, ", ");
        if ($isInsert) {
            $result .= ")";
        }
        return $result;
    }



    /**
     * Returns an array with all the tables
     * @param string[]|null $filter Optional.
     * @return string[]
     */
    public function getTables(?array $filter = null): array {
        $request = $this->query("SHOW TABLES FROM `$this->database`");
        $result  = [];

        foreach ($request as $row) {
            foreach ($row as $value) {
                if ((!empty($filter) && !Arrays::contains($filter, $value)) || empty($filter)) {
                    $result[] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * Returns an array with all the tables
     * @param string $table
     * @return boolean
     */
    public function hasTable(string $table): bool {
        $request = $this->query("SHOW TABLES LIKE '$table'");
        return !empty($request);
    }

    /**
     * Returns the Table Primary Keys
     * @param string $table
     * @return string[]
     */
    public function getPrimaryKeys(string $table): array {
        $request = $this->query("SHOW KEYS FROM `$table`");
        $result  = [];

        foreach ($request as $row) {
            if ($row["Key_name"] == "PRIMARY") {
                $result[] = $row["Column_name"];
            }
        }
        return $result;
    }

    /**
     * Returns the Table Primary Key with Auto Increment
     * @param string $table
     * @return string
     */
    public function getAutoIncrement(string $table): string {
        $request = $this->query("SELECT *
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = '$table'
                AND DATA_TYPE = 'int'
                AND COLUMN_DEFAULT IS NULL
                AND IS_NULLABLE = 'NO'
                AND EXTRA like '%auto_increment%'
            LIMIT 1
        ");
        if (!empty($request[0])) {
            return $request[0]["COLUMN_NAME"];
        }
        return "";
    }

    /**
     * Returns the Table Keys
     * @param string $table
     * @return string[]
     */
    public function getTableKeys(string $table): array {
        return $this->query("SHOW INDEXES IN `$table`");
    }

    /**
     * Returns the Table Fields
     * @param string $table
     * @return string[]
     */
    public function getTableFields(string $table): array {
        return $this->query("SHOW FIELDS FROM `$table`");
    }



    /**
     * Creates a Table
     * @param string   $tableName
     * @param mixed[]  $fields
     * @param string[] $primary
     * @param string[] $keys
     * @return string
     */
    public function createTable(string $tableName, array $fields, array $primary, array $keys): string {
        $sql = "CREATE TABLE $tableName (\n";

        foreach ($fields as $key => $type) {
            $sql .= "  `$key` " . $type . ",\n";
        }
        $sql .= "  PRIMARY KEY (" . Strings::join($primary, ", ") . ")";
        foreach ($keys as $key) {
            $sql .= ",\n  KEY `$key` (`$key`)";
        }
        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8";

        $this->query($sql);
        return $sql;
    }

    /**
     * Deletes a Table
     * @param string $tableName
     * @return string
     */
    public function deleteTable(string $tableName): string {
        $sql = "DROP TABLE `$tableName`";
        $this->query($sql);
        return $sql;
    }

    /**
     * Renames a Column from the Table
     * @param string $tableName
     * @param string $column
     * @param string $type
     * @param string $afterColumn Optional.
     * @return string
     */
    public function addColumn(string $tableName, string $column, string $type, string $afterColumn = ""): string {
        $sql  = "ALTER TABLE `$tableName` ADD COLUMN `$column` $type ";
        $sql .= !empty($afterColumn) ? "AFTER `$afterColumn`" : "FIRST";
        $this->query($sql);
        return $sql;
    }

    /**
     * Renames a Column from the Table
     * @param string $tableName
     * @param string $oldColumn
     * @param string $newColumn
     * @param string $type
     * @return string
     */
    public function renameColumn(string $tableName, string $oldColumn, string $newColumn, string $type): string {
        $sql = "ALTER TABLE `$tableName` CHANGE `$oldColumn` `$newColumn` $type";
        $this->query($sql);
        return $sql;
    }

    /**
     * Updates a Column from the Table
     * @param string $tableName
     * @param string $column
     * @param string $type
     * @return string
     */
    public function updateColumn(string $tableName, string $column, string $type): string {
        $sql = "ALTER TABLE `$tableName` MODIFY COLUMN `$column` $type";
        $this->query($sql);
        return $sql;
    }

    /**
     * Deletes a Column from the Table
     * @param string  $tableName
     * @param string  $column
     * @param boolean $execute   Optional.
     * @return string
     */
    public function deleteColumn(string $tableName, string $column, bool $execute = true): string {
        $sql = "ALTER TABLE `$tableName` DROP COLUMN `$column`";
        if ($execute) {
            $this->query($sql);
        }
        return $sql;
    }

    /**
     * Updates the Primary Keys on the Table
     * @param string   $tableName
     * @param string[] $primary
     * @return string
     */
    public function updatePrimary(string $tableName, array $primary): string {
        $sql  = "ALTER TABLE `$tableName` DROP PRIMARY KEY \n";
        $sql .= "ALTER TABLE `$tableName` ADD PRIMARY KEY (" . Strings::join($primary, ", ") . ")";
        $this->query($sql);
        return $sql;
    }

    /**
     * Creates an Index on the Table
     * @param string $tableName
     * @param string $key
     * @return string
     */
    public function createIndex(string $tableName, string $key): string {
        $sql = "CREATE INDEX $key ON $tableName($key)";
        $this->query($sql);
        return $sql;
    }



    /**
     * Dumps the entire database
     * @param string[]   $filter Optional.
     * @param mixed|null $fp     Optional.
     * @return boolean
     */
    public function dump(array $filter = [], mixed $fp = null): bool {
        $crlf = "\r\n";

        // SQL Dump Header
        $this->write(
            $fp,
            "# ========================================================= $crlf" .
            "# $crlf" .
            "# Database dump of tables in `{$this->database}` $crlf" .
            "# " . date("d M Y, H:i:s") . $crlf .
            "# $crlf" .
            "# ========================================================= $crlf" .
            $crlf
        );

        // Get all tables in the database
        $tables = $this->getTables($filter);

        // Dump each table
        foreach ($tables as $table) {
            $this->write(
                $fp,
                $crlf .
                "#$crlf" .
                "# Table structure for table `$table` $crlf" .
                "#$crlf" .
                $crlf .
                "DROP TABLE IF EXISTS `$table`; $crlf" .
                $crlf .
                $this->getTableSQLData($table) . "; $crlf"
            );

            // Are there any rows in this table?
            $rows = $this->getTableContent($table);
            if (!empty($rows)) {
                $this->write(
                    $fp,
                    $crlf .
                    "# $crlf" .
                    "# Dumping data in `$table` $crlf" .
                    "# $crlf" .
                    $crlf .
                    $rows .
                    "# -------------------------------------------------------- $crlf"
                );
            }
        }
        $this->write($fp, $crlf . "# Done" . $crlf);
        return true;
    }

    /**
     * Writes the content in a file or prints them in the screen
     * @param mixed  $fp
     * @param string $content
     * @return boolean
     */
    private function write(mixed $fp, string $content): bool {
        if (!empty($fp)) {
            fwrite($fp, $content);
        } else {
            print($content);
        }
        return true;
    }

    /**
     * Returns the table's SQL data
     * @param string $tableName
     * @return string
     */
    private function getTableSQLData(string $tableName): string {
        $crlf    = "\r\n";
        $result  = "CREATE TABLE `$tableName` (" . $crlf;
        $request = $this->query("SHOW FIELDS FROM `$tableName`");

        foreach ($request as $row) {
            // Make the CREATE for this column.
            $result .= "  " . $row["Field"] . " " . $row["Type"] . ($row["Null"] != "YES" ? " NOT NULL" : "");

            // Add a default...?
            if (isset($row["Default"])) {
                // Make a special case of auto-timestamp.
                if ($row["Default"] == "CURRENT_TIMESTAMP") {
                    $result .= " /*!40102 NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP */";
                } else {
                    $result .= " default " . (is_numeric($row["Default"]) ? $row["Default"] : "'" . $this->escape($row["Default"]) . "'");
                }
            }

            // And now any extra information. (such as auto_increment.)
            $result .= ($row["Extra"] != "" ? " " . $row["Extra"] : "") . "," . $crlf;
        }

        // Take off the last comma.
        $result = substr($result, 0, -strlen($crlf) - 1);

        // Find the keys.
        $request = $this->query("SHOW KEYS FROM `$tableName`");
        $indexes = [];

        foreach ($request as $row) {
            // IS this a primary key, unique index, or regular index?
            if ($row["Key_name"] == "PRIMARY") {
                $row["Key_name"] = "PRIMARY KEY";
            } elseif (empty($row["Non_unique"])) {
                $row["Key_name"] = "UNIQUE " . $row["Key_name"];
            } elseif ($row["Comment"] == "FULLTEXT" || (isset($row["Index_type"]) && $row["Index_type"] == "FULLTEXT")) {
                $row["Key_name"] = "FULLTEXT " . $row["Key_name"];
            } else {
                $row["Key_name"] = "KEY " . $row["Key_name"];
            }

            // Is this the first column in the index?
            if (empty($indexes[$row["Key_name"]])) {
                $indexes[$row["Key_name"]] = [];
            }

            // A sub part, like only indexing 15 characters of a varchar.
            if (!empty($row["Sub_part"])) {
                $indexes[$row["Key_name"]][$row["Seq_in_index"]] = $row["Column_name"] . "(" . $row["Sub_part"] . ")";
            } else {
                $indexes[$row["Key_name"]][$row["Seq_in_index"]] = $row["Column_name"];
            }
        }

        // Build the CREATEs for the keys.
        foreach ($indexes as $keyname => $columns) {
            ksort($columns);
            $result .= "," . $crlf . "  $keyname (" . Strings::join($columns, ", ") . ")";
        }

        // Now just get the comment and type...
        $request = $this->query("
            SHOW TABLE STATUS
            LIKE '" . strtr($tableName, [ '_' => '\\_', '%' => '\\%' ]) . "'
        ");

        $result .= $crlf . ") ENGINE=" . (isset($request[0]["Type"]) ? $request[0]["Type"] : $request[0]["Engine"]);
        $result .= $request[0]["Comment"] != "" ? " COMMENT='" . $request[0]["Comment"] . "'" : "";

        return $result;
    }

    /**
     * Returns the table content
     * @param string $tableName
     * @return string
     */
    private function getTableContent(string $tableName): string {
        $crlf   = "\r\n";
        $result = "";
        $start  = 0;

        do {
            $request = $this->query("SELECT /*!40001 SQL_NO_CACHE */ * FROM $tableName LIMIT $start, 250");
            $start  += 250;

            if (!empty($request)) {
                $result .= "INSERT INTO `$tableName`" . $crlf . "\t(`" . Strings::joinKeys($request[0], "`, `") . "`) $crlf VALUES ";

                foreach ($request as $index => $row) {
                    $fieldList = [];
                    foreach ($row as $value) {
                        // Try to figure out the type of each field. (NULL, number, or 'string'.)
                        if (!isset($value)) {
                            $fieldList[] = "NULL";
                        } elseif (is_numeric($value)) {
                            $fieldList[] = $value;
                        } else {
                            $fieldList[] = "'" . $this->escape($value) . "'";
                        }
                    }
                    $result .= "(" . Strings::join($fieldList, ", ") . ")";

                    if ($index < count($request) - 1) {
                        $result .= "," . $crlf . "\t";
                    }
                }
                $result .= ";" . $crlf;
            }
        } while (!empty($request));

        return $result;
    }
}
