<?php
namespace Admin\Schema;

use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * The Database Query
 */
class Query {

    public string $where     = "";
    public string $prefix    = "AND";
    public bool   $addPrefix = false;

    public string $limit     = "";
    public string $groupBy   = "";
    public string $orderBy   = "";

    /** @var mixed[] */
    public array  $params    = [];

    /** @var string[] */
    public array  $columns   = [];

    /** @var string[] */
    public array  $groups    = [];

    /** @var string[] */
    public array  $orders    = [];


    /**
     * Creates a new Query instance
     * @param Query|null $query Optional.
     */
    public function __construct(?Query $query = null) {
        if (!empty($query)) {
            $this->where     = $query->where;
            $this->params    = $query->params;
            $this->prefix    = $query->prefix;
            $this->addPrefix = $query->addPrefix;

            $this->limit     = $query->limit;
            $this->orderBy   = $query->orderBy;
            $this->groupBy   = $query->groupBy;

            $this->columns   = $query->columns;
            $this->groups    = $query->groups;
            $this->orders    = $query->orders;
        }
    }



    /**
     * Returns the Prefix
     * @return string
     */
    public function getPrefix(): string {
        $prefix = $this->addPrefix ? $this->prefix . " " : "";
        if (!$this->addPrefix) {
            $this->addPrefix = true;
        }
        return $prefix;
    }

    /**
     * Adds an expression as an and
     * @param string $column
     * @param string $expression
     * @param mixed  $value
     * @return Query
     */
    public function add(string $column, string $expression, mixed $value): Query {
        $prefix = $this->getPrefix();
        $binds  = Arrays::isArray($value) ? self::createBinds($value) : "?";
        $value  = $expression == "LIKE" ? "%$value%" : $value;
        $values = Arrays::toArray($value);

        $this->where    .= "{$prefix} {$column} {$expression} {$binds} ";
        $this->params    = array_merge($this->params, $values);
        $this->columns[] = $column;
        return $this;
    }

    /**
     * Adds an expression as an and if the value is not empty
     * @param string       $column
     * @param string       $expression
     * @param mixed        $value
     * @param boolean|null $condition  Optional.
     * @return Query
     */
    public function addIf(string $column, string $expression, mixed $value, ?bool $condition = null): Query {
        if ($condition !== null && $condition) {
            $this->add($column, $expression, $value);
        } elseif ($condition === null && !empty($value)) {
            $this->add($column, $expression, $value);
        }
        return $this;
    }

    /**
     * Adds an expression with a value
     * @param string $expression
     * @param mixed  ...$values
     * @return Query
     */
    public function addExp(string $expression, mixed ...$values): Query {
        $prefix       = $this->getPrefix();
        $this->where .= "{$prefix}{$expression} ";
        $this->params = array_merge($this->params, $values);
        return $this;
    }

    /**
     * Adds an expression with a value if the value is not empty
     * @param string $expression
     * @param mixed  ...$values
     * @return Query
     */
    public function addExpIf(string $expression, mixed ...$values): Query {
        if (!empty($values[0])) {
            $this->addExp($expression, ...$values);
        }
        return $this;
    }

    /**
     * Adds an expression as NULL
     * @param string $column
     * @return Query
     */
    public function addNull(string $column): Query {
        $prefix          = $this->getPrefix();
        $this->where    .= "{$prefix}ISNULL($column) = 1";
        $this->columns[] = $column;
        return $this;
    }



    /**
     * Adds a Open Parenthesis
     * @return Query
     */
    public function startParen(): Query {
        $prefix          = $this->getPrefix();
        $this->where    .= "{$prefix}(";
        $this->addPrefix = false;
        return $this;
    }

    /**
     * Adds a Close Parenthesis
     * @return Query
     */
    public function endParen(): Query {
        $this->where .= ") ";
        return $this;
    }

    /**
     * Adds an And
     * @return Query
     */
    public function and(): Query {
        $this->where    .= " AND ";
        $this->addPrefix = false;
        return $this;
    }

    /**
     * Adds an Or
     * @return Query
     */
    public function or(): Query {
        $this->where    .= " OR ";
        $this->addPrefix = false;
        return $this;
    }

    /**
     * Starts an Or expression
     * @return Query
     */
    public function startOr(): Query {
        $prefix          = $this->getPrefix();
        $this->where    .= "{$prefix}(";
        $this->prefix    = "OR";
        $this->addPrefix = false;
        return $this;
    }

    /**
     * Ends an Or expression
     * @return Query
     */
    public function endOr(): Query {
        $this->where .= ") ";
        $this->prefix = "AND";
        return $this;
    }

    /**
     * Adds a Search expression
     * @param string[]|string $column
     * @param mixed           $value
     * @param string          $expression      Optional.
     * @param boolean         $caseInsensitive Optional.
     * @param boolean         $splitValue      Optional.
     * @return Query
     */
    public function search(array|string $column, mixed $value, string $expression = "LIKE", bool $caseInsensitive = true, bool $splitValue = false): Query {
        if (empty($value)) {
            return $this;
        }

        $valueParts = $splitValue ? Strings::split($value, " ") : [ $value ];
        $valueParts = Arrays::removeEmpty($valueParts);
        $columns    = Arrays::toArray($column);
        $multiParts = Arrays::length($valueParts) > 1;
        $multiCols  = Arrays::length($columns) > 1;
        $isFirst    = true;

        if ($multiParts) {
            $this->startParen();
        }
        foreach ($valueParts as $valuePart) {
            $valueSearch = $caseInsensitive ? Strings::toLowerCase($valuePart) : $valuePart;
            if ($multiParts && !$isFirst) {
                $this->and();
            }
            if ($multiCols) {
                $this->startOr();
            }
            foreach ($columns as $columnSearch) {
                $this->add($columnSearch, $expression, $valueSearch);
            }
            if ($multiCols) {
                $this->endOr();
            }
            $isFirst = false;
        }
        if ($multiParts) {
            $this->endParen();
        }
        return $this;
    }

    /**
     * Adds an expression as an and where the column is between the given times
     * @param string  $column
     * @param integer $fromTime
     * @param integer $toTime
     * @return Query
     */
    public function betweenTimes(string $column, int $fromTime, int $toTime): Query {
        if (!empty($fromTime) && !empty($toTime)) {
            $this->add($column, ">=", $fromTime);
            $this->add($column, "<=", $toTime);
        } elseif (!empty($fromTime)) {
            $this->add($column, ">=", $fromTime);
        } elseif (!empty($toTime)) {
            $this->add($column, "<=", $toTime);
        }
        return $this;
    }

    /**
     * Adds an Group By
     * @param string $column
     * @return Query
     */
    public function groupBy(string $column): Query {
        if (!empty($column)) {
            $this->groupBy  = $column;
            $this->groups[] = $column;
        }
        return $this;
    }

    /**
     * Adds an Order By
     * @param string  $column
     * @param boolean $isASC  Optional.
     * @return Query
     */
    public function orderBy(string $column, bool $isASC = true): Query {
        if (!empty($column)) {
            $prefix         = !empty($this->orderBy) ? ", " : "";
            $this->orderBy .= " {$prefix}{$column} " . ($isASC ? "ASC" : "DESC");
            $this->orders[] = $column;
        }
        return $this;
    }

    /**
     * Orders Randomly
     * @return Query
     */
    public function random(): Query {
        $this->orderBy = "RAND()";
        $this->orders  = [];
        return $this;
    }

    /**
     * Adds an Limit
     * @param integer      $from
     * @param integer|null $to   Optional.
     * @return Query
     */
    public function limit(int $from, ?int $to = null): Query {
        if ($to != null) {
            $this->limit = max($from, 0) . ", " . max($to - $from + 1, 1);
        } else {
            $this->limit = $from;
        }
        return $this;
    }

    /**
     * Adds a Limit
     * @param integer|null $from Optional.
     * @param integer|null $to   Optional.
     * @return Query
     */
    public function limitIf(?int $from = null, ?int $to = null): Query {
        if (!empty($from)) {
            $this->limit($from, $to);
        }
        return $this;
    }

    /**
     * Adds a limit using pagination
     * @param integer $page   Optional.
     * @param integer $amount Optional.
     * @return Query
     */
    public function paginate(int $page = 0, int $amount = 100): Query {
        $from = $page * $amount;
        $to   = $from + $amount - 1;
        return $this->limit($from, $to);
    }



    /**
     * Returns true if the Query is empty
     * @return boolean
     */
    public function isEmpty(): bool {
        return empty($this->where);
    }

    /**
     * Returns true if the given Column is in the Query
     * @param string $column
     * @return boolean
     */
    public function hasColumn(string $column): bool {
        return Arrays::contains($this->columns, $column);
    }

    /**
     * Returns true if there is an Order By
     * @param string|null $order Optional.
     * @return boolean
     */
    public function hasOrder(?string $order = null): bool {
        if (!empty($order)) {
            return Arrays::contains($this->orders, $order);
        }
        return !empty($this->orderBy);
    }



    /**
     * Returns the complete Query to use with the Database
     * @param boolean $addWhere Optional.
     * @return string
     */
    public function get(bool $addWhere = true): string {
        $result  = $this->getWhere($addWhere);
        $result .= $this->getOrderLimit();
        return preg_replace("!\s+!", " ", $result);
    }

    /**
     * Returns the where part of the Query to use with the Database
     * @param boolean $addWhere Optional.
     * @return string
     */
    public function getWhere(bool $addWhere = false): string {
        if (!empty($this->where)) {
            return ($addWhere ? "WHERE " : "AND ") . $this->where;
        }
        return "";
    }

    /**
     * Returns the group order and limit part of the Query to use with the Database
     * @return string
     */
    public function getOrderLimit(): string {
        $result = "";
        if (!empty($this->groupBy)) {
            $result .= " GROUP BY " . $this->groupBy;
        }
        if (!empty($this->orderBy)) {
            $result .= " ORDER BY " . $this->orderBy;
        }
        if (!empty($this->limit)) {
            $result .= " LIMIT " . $this->limit;
        }
        return $result;
    }

    /**
     * Returns the params part of the Query to use with the Database
     * @param boolean $duplicate Optional.
     * @return mixed[]
     */
    public function getParams(bool $duplicate = false): array {
        if ($duplicate) {
            return array_merge($this->params, $this->params);
        }
        return $this->params;
    }

    /**
     * Returns the Columns
     * @return string[]
     */
    public function getColumns(): array {
        $result = array_merge($this->columns, $this->groups, $this->orders);
        return array_unique($result);
    }

    /**
     * Updates a Column
     * @param string $oldColumn
     * @param string $newColumn
     * @return Query
     */
    public function updateColumn(string $oldColumn, string $newColumn): Query {
        foreach ([ "where", "orderBy", "groupBy" ] as $type) {
            foreach ([ "(", " " ] as $prefix) {
                $this->{$type} = Strings::replace(
                    $this->{$type},
                    "{$prefix}{$oldColumn}",
                    "{$prefix}{$newColumn}"
                );
            }
        }
        return $this;
    }



    /**
     * Creates a new Query with the given values
     * @param string     $column     Optional.
     * @param string     $expression Optional.
     * @param mixed|null $value      Optional.
     * @return Query
     */
    public static function create(string $column = "", string $expression = "", mixed $value = null): Query {
        $query = new Query();
        if (!empty($column)) {
            $query->add($column, $expression, $value);
        }
        return $query;
    }

    /**
     * Creates a new Query with the given values
     * @param string     $column     Optional.
     * @param string     $expression Optional.
     * @param mixed|null $value      Optional.
     * @return Query
     */
    public static function createIf(string $column = "", string $expression = "", mixed $value = null): Query {
        $query = new Query();
        if (!empty($column) && !empty($value)) {
            $query->add($column, $expression, $value);
        }
        return $query;
    }

    /**
     * Creates a new Query with the given values
     * @param string[]|string|null $column          Optional.
     * @param mixed|null           $value           Optional.
     * @param string               $expression      Optional.
     * @param boolean              $caseInsensitive Optional.
     * @param boolean              $splitValue      Optional.
     * @return Query
     */
    public static function createSearch(array|string $column = null, mixed $value = null, string $expression = "LIKE", bool $caseInsensitive = true, bool $splitValue = false): Query {
        $query = new Query();
        if (!empty($column) && !empty($value)) {
            $query->search($column, $value, $expression, $caseInsensitive, $splitValue);
        }
        return $query;
    }

    /**
     * Creates a new Query with an Order
     * @param string  $column   Optional.
     * @param integer $fromTime Optional.
     * @param integer $toTime   Optional.
     * @return Query
     */
    public static function createBetween(string $column = "", int $fromTime = 0, int $toTime = 0): Query {
        $query = new Query();
        if (!empty($column)) {
            $query->betweenTimes($column, $fromTime, $toTime);
        }
        return $query;
    }

    /**
     * Creates a new Query with an Order
     * @param string  $column Optional.
     * @param boolean $isASC  Optional.
     * @return Query
     */
    public static function createOrderBy(string $column = "", bool $isASC = false): Query {
        $query = new Query();
        if (!empty($column)) {
            $query->orderBy($column, $isASC);
        }
        return $query;
    }



    /**
     * Creates a list of question marks for the given array
     * @param mixed[] $array
     * @return string
     */
    public static function createBinds(array $array): string {
        $bind = [];
        for ($i = 0; $i < count($array); $i++) {
            $bind[] = "?";
        }
        return "(" . Strings::join($bind, ",") . ")";
    }

    /**
     * Method generates equality between columns function call
     * @param string|null $column Optional.
     * @return array{}
     */
    public static function equal(?string $column = null): array {
        return [ "[E]" => $column ];
    }

    /**
     * Method generates incremental function call
     * @param integer $amount Optional.
     * @return array{}
     */
    public static function inc(int $amount = 1): array {
        return [ "[I]" => "+" . $amount ];
    }

    /**
     * Method generates decremental function call
     * @param integer $amount Optional.
     * @return array{}
     */
    public static function dec(int $amount = 1): array {
        return [ "[I]" => "-" . $amount ];
    }

    /**
     * Method generates change boolean function call
     * @param string|null $column Optional.
     * @return array{}
     */
    public static function not(?string $column = null): array {
        return [ "[N]" => $column ];
    }

    /**
     * Method generates user defined function call
     * @param string  $expression
     * @param array{} $params     Optional.
     * @return array{}
     */
    public static function func(string $expression, array $params = []): array {
        return [ "[F]" => [ $expression, $params ]];
    }

    /**
     * Method generates an REPLACE function call
     * @param string $column
     * @param string $value
     * @param string $replace
     * @return array{}
     */
    public static function replace(string $column, string $value, string $replace): array {
        return self::func("REPLACE($column, ?, ?)", [ $value, $replace ]);
    }
}
