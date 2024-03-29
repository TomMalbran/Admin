<?php
namespace Admin\Schema;

use Admin\Schema\Database;
use Admin\Schema\Structure;
use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * The Selection wrapper
 */
class Selection {

    private Database  $db;
    private Structure $structure;

    private int $index = 66;

    /** @var string[] */
    private array $tables  = [];

    /** @var string[] */
    private array $keys    = [];

    /** @var string[] */
    private array $selects = [];

    /** @var string[] */
    private array $joins   = [];

    /** @var array{}[] */
    private array $request = [];


    /**
     * Creates a new Selection instance
     * @param Database  $db
     * @param Structure $structure
     */
    public function __construct(Database $db, Structure $structure) {
        $this->db        = $db;
        $this->structure = $structure;
    }



    /**
     * Adds the Fields to the Selects
     * @return Selection
     */
    public function addFields(): Selection {
        $mainKey = $this->structure->table;

        if (!empty($this->structure->idKey) && !empty($this->structure->idName)) {
            $this->selects[] = "$mainKey.{$this->structure->idKey} AS id";
        }
        foreach ($this->structure->fields as $field) {
            if ($field->hasName) {
                $this->selects[] = "$mainKey.$field->key AS $field->name";
            } else {
                $this->selects[] = "$mainKey.$field->key";
            }
        }
        return $this;
    }

    /**
     * Adds extra Selects
     * @param string[]|string $selects
     * @param boolean         $addMainKey Optional.
     * @return Selection
     */
    public function addSelects(array|string $selects, bool $addMainKey = false): Selection {
        $selects = Arrays::toArray($selects);
        foreach ($selects as $select) {
            if ($addMainKey) {
                $this->selects[] = $this->structure->getKey($select);
            } else {
                $this->selects[] = $select;
            }
        }
        return $this;
    }

    /**
     * Adds the Joins
     * @param boolean $withSelects Optional.
     * @return Selection
     */
    public function addJoins(bool $withSelects = true): Selection {
        $mainKey = $this->structure->table;

        foreach ($this->structure->joins as $join) {
            if ($join->asTable) {
                $joinKey = $join->asTable;
            } elseif (Arrays::contains($this->tables, $join->table)) {
                $joinKey = chr($this->index++);
            } else {
                $joinKey        = $join->table;
                $this->tables[] = $join->table;
            }

            $onTable    = $join->onTable ?: $mainKey;
            $leftKey    = $join->leftKey;
            $rightKey   = $join->rightKey;
            $and        = $join->and;
            $expression = "LEFT JOIN `{$join->table}` AS $joinKey ON ($joinKey.$leftKey = $onTable.$rightKey $and)";

            $this->joins[]          = $expression;
            $this->keys[$join->key] = $joinKey;

            if ($withSelects) {
                foreach ($join->fields as $field) {
                    $this->selects[] = "$joinKey.{$field->key} AS $field->prefixName";
                }
            }
        }
        return $this;
    }

    /**
     * Adds the Counts
     * @return Selection
     */
    public function addCounts(): Selection {
        foreach ($this->structure->counts as $count) {
            $joinKey    = chr($this->index++);
            $key        = $count->key;
            $what       = $count->isSum ? "SUM($count->mult * $count->value)" : "COUNT(*)";
            $groupKey   = "{$count->table}.{$count->key}";
            $asKey      = $count->asKey;
            $onTable    = $count->onTable ?: $this->structure->table;
            $leftKey    = $count->leftKey;
            $where      = $count->noDeleted ? "WHERE isDeleted = 0" : "";
            $select     = "SELECT $groupKey, $what AS $asKey FROM `{$count->table}` $where GROUP BY $groupKey";
            $expression = "LEFT JOIN ($select) AS $joinKey ON ($joinKey.$leftKey = $onTable.$key)";

            $this->joins[]             = $expression;
            $this->selects[]           = "$joinKey.$asKey";
            $this->keys[$count->index] = $joinKey;
        }
        return $this;
    }



    /**
     * Does a Request to the Query
     * @param Query $query
     * @return mixed[]
     */
    public function request(Query $query): array {
        $this->setTableKeys($query);

        $mainKey    = $this->structure->table;
        $selects    = Strings::join($this->selects, ", ");
        $joins      = Strings::join($this->joins, " ");
        $where      = $query->get();
        $expression = "SELECT $selects FROM `$mainKey` AS $mainKey $joins $where";

        $this->request = $this->db->query($expression, $query);
        return $this->request;
    }

    /**
     * Sets the Table Keys to the condition
     * @param Query $query
     * @return Selection
     */
    private function setTableKeys(Query $query): Selection {
        $columns = $query->getColumns();
        $mainKey = $this->structure->table;

        foreach ($columns as $column) {
            $found = false;
            foreach ($this->structure->fields as $field) {
                if ($column === $field->key) {
                    $query->updateColumn($column, "$mainKey.{$field->key}");
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                foreach ($this->structure->joins as $join) {
                    $joinKey = $this->keys[$join->key];
                    foreach ($join->fields as $field) {
                        if ($column === $field->key) {
                            $query->updateColumn($column, "$joinKey.{$field->key}");
                            $found = true;
                            break;
                        }
                    }
                }
            }

            if (!$found) {
                foreach ($this->structure->counts as $count) {
                    if (!empty($this->keys[$count->index])) {
                        $joinKey = $this->keys[$count->index];
                        $field   = $count->asKey;
                        if ($column === $field) {
                            $query->updateColumn($column, "$joinKey.{$field}");
                            $found = true;
                            break;
                        }
                    }
                }
            }
        }
        return $this;
    }



    /**
     * Generates the Result from the Request
     * @param string[]|string|null $extras Optional.
     * @return mixed[]
     */
    public function resolve(array|string $extras = null): array {
        $result = [];

        foreach ($this->request as $row) {
            $fields = [];
            if (!empty($this->structure->idKey) && !empty($this->structure->idName)) {
                if (!empty($row["id"])) {
                    $fields["id"] = $row["id"];
                } elseif (!empty($row[$this->structure->idKey])) {
                    $fields["id"] = $row[$this->structure->idKey];
                } elseif (!empty($row[$this->structure->idName])) {
                    $fields["id"] = $row[$this->structure->idName];
                }
            }

            // Parse the Fields
            foreach ($this->structure->fields as $field) {
                $values = $field->toValues($row);
                $fields = array_merge($fields, $values);
            }

            // Parse the Joins
            foreach ($this->structure->joins as $join) {
                $values = $join->toValues($row);
                $fields = array_merge($fields, $values);
            }

            // Parse the Counts
            foreach ($this->structure->counts as $count) {
                $fields[$count->asKey] = $count->getValue($row);
            }

            // Parse the Extras
            if (!empty($extras)) {
                $extras = Arrays::toArray($extras);
                foreach ($extras as $extra) {
                    $fields[$extra] = $row[$extra];
                }
            }

            $result[] = $fields;
        }
        return $result;
    }
}
