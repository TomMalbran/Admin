<?php
namespace Admin\Schema;

use Admin\Admin;
use Admin\Config\Settings;
use Admin\Schema\Factory;
use Admin\Schema\Structure;
use Admin\File\File;
use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * The Schema Migration
 */
class Migration {

    private static $db = null;


    /**
     * Migrates the Tables
     * @param boolean $canDelete Optional.
     * @return void
     */
    public static function migrate(bool $canDelete = false): void {
        self::$db = Factory::getDatabase();

        $schemas     = Factory::getSchemas();
        $tableNames  = self::$db->getTables(null, false);
        $schemaNames = [];

        // Create or update the Tables
        foreach ($schemas as $schemaKey => $schemaData) {
            $structure     = new Structure($schemaKey, $schemaData);
            $schemaNames[] = $structure->table;

            if (!Arrays::contains($tableNames, $structure->table)) {
                self::createTable($structure);
            } else {
                self::updateTable($structure, $canDelete);
            }
        }

        // Delete the Tables or show which to delete
        self::deleteTables($tableNames, $schemaNames, $canDelete);

        // Run extra migrations created in files
        self::extraMigrations();
    }

    /**
     * Creates a New Table
     * @param Structure $structure
     * @return void
     */
    private static function createTable(Structure $structure): void {
        $fields  = [];
        $primary = [];
        $keys    = [];

        foreach ($structure->fields as $field) {
            $fields[$field->key] = $field->getType();
            if ($field->isPrimary) {
                $primary[] = $field->key;
            }
            if ($field->isKey) {
                $keys[] = $field->key;
            }
        }

        $sql = self::$db->createTable($structure->table, $fields, $primary, $keys);
        print("<br>Created table <b>$structure->table</b> ... <br>");
        print(Strings::toHtml($sql) . "<br><br>");
    }

    /**
     * Delete the Tables or show which to delete
     * @param array   $tableNames
     * @param array   $schemaNames
     * @param boolean $canDelete
     * @return void
     */
    private static function deleteTables(array $tableNames, array $schemaNames, bool $canDelete): void {
        $prebr = "<br>";
        foreach ($tableNames as $tableName) {
            if (!Arrays::contains($schemaNames, $tableName)) {
                if ($canDelete) {
                    self::$db->deleteTable($tableName);
                    print("{$prebr}Deleted table <i>$tableName</i><br>");
                } else {
                    print("{$prebr}Delete table <i>$tableName</i> (manually)<br>");
                }
                $prebr = "";
            }
        }
    }

    /**
     * Updates the Table
     * @param Structure $structure
     * @param boolean   $canDelete
     * @return void
     */
    private static function updateTable(Structure $structure, bool $canDelete): void {
        $primaryKeys = self::$db->getPrimaryKeys($structure->table);
        $tableKeys   = self::$db->getTableKeys($structure->table);
        $tableFields = self::$db->getTableFields($structure->table);
        $update      = false;
        $adds        = [];
        $drops       = [];
        $modifies    = [];
        $renames     = [];
        $primary     = [];
        $addPrimary  = false;
        $keys        = [];
        $prev        = "";

        // Add new Columns
        foreach ($structure->fields as $field) {
            $found  = false;
            $rename = false;
            foreach ($tableFields as $tableField) {
                $tableKey = $tableField["Field"];
                if (Strings::isEqual($field->key, $tableKey) && $field->key !== $tableKey) {
                    $rename = true;
                    break;
                }
                if ($field->key === $tableKey) {
                    $found = true;
                    break;
                }
            }

            $type = $field->getType();
            if ($rename) {
                $update    = true;
                $renames[] = [
                    "key"  => $tableKey,
                    "new"  => $field->key,
                    "type" => $type,
                ];
            } elseif (!$found) {
                $update = true;
                $adds[] = [
                    "key"   => $field->key,
                    "type"  => $type,
                    "after" => $prev,
                ];
            }
            $prev = $field->key;
        }

        // Remove Columns
        foreach ($tableFields as $tableField) {
            $tableKey = $tableField["Field"];
            $found    = false;
            foreach ($structure->fields as $field) {
                if (Strings::isEqual($field->key, $tableKey)) {
                    $found = true;
                }
            }
            if (!$found) {
                $drops[] = $tableKey;
                $update  = true;
            }
        }

        // Modify Columns
        foreach ($structure->fields as $field) {
            foreach ($tableFields as $tableField) {
                if ($field->key === $tableField["Field"]) {
                    $oldData = $tableField["Type"];
                    if ($tableField["Null"] === "NO") {
                        $oldData .= " NOT NULL";
                    }
                    if ($tableField["Default"] !== NULL) {
                        $oldData .= " DEFAULT '{$tableField["Default"]}'";
                    }
                    if (!empty($tableField["Extra"])) {
                        $oldData .= " " . Strings::toUpperCase($tableField["Extra"]);
                    }
                    $newData = $field->getType();
                    if ($newData !== $oldData) {
                        $update     = true;
                        $modifies[] = [
                            "key"  => $field->key,
                            "type" => $newData,
                        ];
                    }
                    break;
                }
            }
        }

        // Update the Table Primary Keys and Index Keys
        foreach ($structure->fields as $field) {
            if ($field->isPrimary) {
                $primary[] = $field->key;
                if (!Arrays::contains($primaryKeys, $field->key)) {
                    $addPrimary = true;
                    $update     = true;
                }
            }
            if ($field->isKey) {
                $found = false;
                foreach ($tableKeys as $tableKey) {
                    if ($tableKey["Key_name"] === $field->key) {
                        $found = true;
                    }
                }
                if (!$found) {
                    $keys[] = $field->key;
                    $update = true;
                }
            }
        }

        // Nothing to change
        if (!$update) {
            print("No changes for <i>$structure->table</i><br>");
            return;
        }

        // Update the Table
        print("<br>Updated table <b>$structure->table</b> ... <br>");
        foreach ($adds as $add) {
            $sql = self::$db->addColumn($structure->table, $add["key"], $add["type"], $add["after"]);
            print("$sql<br>");
        }
        foreach ($renames as $rename) {
            $sql = self::$db->renameColumn($structure->table, $rename["key"], $rename["new"], $rename["type"]);
            print("$sql<br>");
        }
        foreach ($modifies as $modify) {
            $sql = self::$db->updateColumn($structure->table, $modify["key"], $modify["type"]);
            print("$sql<br>");
        }
        foreach ($drops as $drop) {
            $sql = self::$db->deleteColumn($structure->table, $drop, $canDelete);
            print($sql . (!$canDelete ? " (manually)" : "") . "<br>");
        }
        foreach ($keys as $key) {
            $sql = self::$db->createIndex($structure->table, $key);
            print("$sql<br>");
        }
        if ($addPrimary) {
            $sql = self::$db->updatePrimary($structure->table, $primary);
            print("$sql<br>");
        }
        print("<br>");
    }



    /**
     * Runs extra Migrations
     * @return void
     */
    private static function extraMigrations() {
        $migration = Settings::getInt("lastMigration", "core");
        $path      = Admin::getPath(Admin::MigrationsDir);

        if (!File::exists($path)) {
            print("<br>No <i>migrations</i> required<br>");
            return;
        }

        $files = File::getFilesInDir($path);
        $names = [];
        foreach ($files as $file) {
            if (File::hasExtension($file, "php")) {
                $names[] = (int)File::getName($file);
            }
        }

        sort($names);
        $first = !empty($migration) ? $migration + 1 : 1;
        $last  = end($names);
        if (empty($names) || $first > $last) {
            print("<br>No <i>migrations</i> required<br>");
            return;
        }

        print("<br>Running <b>migrations $first to $last</b><br>");
        foreach ($names as $name) {
            if ($name >= $first) {
                include_once("$path/$name.php");
                call_user_func("migration$name", self::$db);
            }
        }

        Settings::set("core", "lastMigration", $last);
    }
}
