<?php
namespace Admin\Schema;

use Admin\IO\Request;
use Admin\Schema\Database;
use Admin\Schema\Structure;
use Admin\Schema\SubRequest;
use Admin\Schema\Selection;
use Admin\Schema\Modification;
use Admin\Schema\Field;
use Admin\Schema\Query;
use Admin\Schema\Model;
use Admin\Utils\Arrays;
use Admin\Utils\Strings;

/**
 * The Database Schema
 */
class Schema {

    private Database $db;
    private Structure $structure;

    /** @var SubRequest[] */
    private array     $subRequests;


    /**
     * Creates a new Schema instance
     * @param Database     $db
     * @param Structure    $structure
     * @param SubRequest[] $subRequests Optional.
     */
    public function __construct(Database $db, Structure $structure, array $subRequests = []) {
        $this->db          = $db;
        $this->structure   = $structure;
        $this->subRequests = $subRequests;
    }

    /**
     * Returns the Schema Fields
     * @return Field[]
     */
    public function getFields(): array {
        return $this->structure->fields;
    }



    /**
     * Returns the Model with the given Where
     * @param Query|integer $query
     * @param boolean       $withDeleted Optional.
     * @return Model
     */
    public function getOne(Query|int $query, bool $withDeleted = true): Model {
        $query   = $this->generateQueryID($query, $withDeleted)->limit(1);
        $request = $this->request($query);
        return $this->getModel($request);
    }

    /**
     * Creates a new Model using the given Data
     * @param array{}|null $request Optional.
     * @return Model
     */
    public function getModel(?array $request = null): Model {
        if (!empty($request[0])) {
            return new Model($this->structure->idName, $request[0]);
        }
        return new Model($this->structure->idName);
    }

    /**
     * Returns true if there is a Schema with the given ID
     * @param Query|integer $query
     * @param boolean       $withDeleted Optional.
     * @return boolean
     */
    public function exists(Query|int $query, bool $withDeleted = true): bool {
        $query = $this->generateQueryID($query, $withDeleted);
        return $this->getTotal($query) == 1;
    }



    /**
     * Returns the first line of the given query
     * @param string  $expression
     * @param array{} $params     Optional.
     * @return mixed[]
     */
    public function getQuery(string $expression, array $params = []): array {
        $expression = Strings::replace($expression, "{table}", $this->structure->table);
        $request    = $this->db->query($expression, $params);
        return $request;
    }

    /**
     * Returns an array of Schemas
     * @param Query|null   $query Optional.
     * @param Request|null $sort  Optional.
     * @return mixed[]
     */
    public function getAll(?Query $query = null, ?Request $sort = null): array {
        $query   = $this->generateQuerySort($query, $sort);
        $request = $this->request($query);
        return $request;
    }

    /**
     * Returns a map of Schemas
     * @param Query|null   $query Optional.
     * @param Request|null $sort  Optional.
     * @return array{}
     */
    public function getMap(?Query $query = null, ?Request $sort = null): array {
        $query   = $this->generateQuerySort($query, $sort);
        $request = $this->request($query);
        return Arrays::createMap($request, $this->structure->idName);
    }

    /**
     * Requests data to the database
     * @param Query|null $query Optional.
     * @return mixed[]
     */
    private function request(?Query $query = null): array {
        $selection = new Selection($this->db, $this->structure);
        $selection->addFields();
        $selection->addJoins();
        $selection->addCounts();
        $selection->request($query);

        $result = $selection->resolve();
        foreach ($this->subRequests as $subRequest) {
            $result = $subRequest->request($result);
        }
        return $result;
    }




    /**
     * Selects the given column from a single table and returns the entire column
     * @param Query   $query
     * @param array{} $selects
     * @param boolean $withSubs Optional.
     * @return mixed[]
     */
    public function getColumns(Query $query, array $selects, bool $withSubs = false): array {
        $query     = $this->generateQuery($query);
        $selection = new Selection($this->db, $this->structure);
        $selection->addFields();
        $selection->addSelects(array_values($selects));
        $selection->addJoins();
        $selection->request($query);

        $result = $selection->resolve(array_keys($selects));
        if ($withSubs) {
            foreach ($this->subRequests as $subRequest) {
                $result = $subRequest->request($result);
            }
        }
        return $result;
    }

    /**
     * Selects the given Data
     * @param Query    $query
     * @param string[] $selects
     * @param boolean  $withFields Optional.
     * @return mixed[]
     */
    public function getSome(Query $query, array $selects, bool $withFields = false): array {
        $query     = $this->generateQuery($query);
        $selection = new Selection($this->db, $this->structure);
        $selection->addSelects($selects);
        $selection->addJoins();
        $selection->addCounts();
        if ($withFields) {
            $selection->addFields();
        }
        $selection->request($query);
        return $selection->resolve();
    }

    /**
     * Gets a Total using the Joins
     * @param Query|null $query       Optional.
     * @param string     $column      Optional.
     * @param boolean    $withDeleted Optional.
     * @return integer
     */
    public function getTotal(?Query $query = null, string $column = "*", bool $withDeleted = true): int {
        $query     = $this->generateQuery($query, $withDeleted);
        $selection = new Selection($this->db, $this->structure);
        $selection->addSelects("COUNT($column) AS cnt");
        $selection->addJoins();

        $request = $selection->request($query);
        if (isset($request[0]["cnt"])) {
            return (int)$request[0]["cnt"];
        }
        return 0;
    }

    /**
     * Gets a Sum using the Joins
     * @param Query  $query
     * @param string $column
     * @return integer
     */
    public function getSum(Query $query, string $column): int {
        $query     = $this->generateQuery($query);
        $selection = new Selection($this->db, $this->structure);
        $selection->addSelects("SUM($column) AS cnt");
        $selection->addJoins();

        $request = $selection->request($query);
        if (isset($request[0]["cnt"])) {
            return (int)$request[0]["cnt"];
        }
        return 0;
    }

    /**
     * Returns the first line of the given query
     * @param Query  $query
     * @param string $select
     * @return array{}
     */
    public function getStats(Query $query, string $select): array {
        $select  = Strings::replace($select, "{table}", $this->structure->table);
        $request = $this->db->query("$select " . $query->get(), $query);

        if (!empty($request[0])) {
            return $request[0];
        }
        return [];
    }

    /**
     * Returns the search results
     * @param Query                $query
     * @param string[]|string|null $name  Optional.
     * @return mixed[]
     */
    public function getSearch(Query $query, array|string $name = null): array {
        $query   = $this->generateQuery($query);
        $request = $this->request($query);
        $result  = [];

        foreach ($request as $row) {
            $result[] = [
                "id"    => $row[$this->structure->idName],
                "title" => Arrays::getValue($row, $name ?: $this->structure->name),
                "data"  => $row,
            ];
        }
        return $result;
    }

    /**
     * Selects the given column from a single table and returns the entire column
     * @param Query  $query
     * @param string $column
     * @return string[]
     */
    public function getColumn(Query $query, string $column): array {
        $columnName = Strings::substringAfter($column, ".");
        $query      = $this->generateQuery($query);
        $selection  = new Selection($this->db, $this->structure);
        $selection->addSelects($column, true);
        $selection->addJoins();

        $request = $selection->request($query);
        $result  = [];
        foreach ($request as $row) {
            if (!empty($row[$columnName]) && !Arrays::contains($result, $row[$columnName])) {
                $result[] = $row[$columnName];
            }
        }
        return $result;
    }

    /**
     * Gets the Next Position
     * @param Query|null $query       Optional.
     * @param boolean    $withDeleted Optional.
     * @return integer
     */
    public function getNextPosition(?Query $query = null, bool $withDeleted = true): int {
        if (!$this->structure->hasPosition) {
            return 0;
        }
        $selection = new Selection($this->db, $this->structure);
        $selection->addSelects("position", true);
        $selection->addJoins(false);

        $query = $this->generateQuery($query, $withDeleted);
        $query->orderBy("position", false);
        $query->limit(1);

        $request = $selection->request($query);
        if (!empty($request[0])) {
            return (int)$request[0]["position"] + 1;
        }
        return 1;
    }

    /**
     * Selects the given column from a single table and returns a single value
     * @param Query  $query
     * @param string $column
     * @return mixed
     */
    public function getValue(Query $query, string $column): mixed {
        return $this->db->getValue($this->structure->table, $column, $query);
    }



    /**
     * Returns all the Sorted Names
     * @param string|null            $order      Optional.
     * @param boolean                $orderAsc   Optional.
     * @param string[]|string|null   $name       Optional.
     * @param integer[]|integer|null $selectedID Optional.
     * @param string|null            $extra      Optional.
     * @return mixed[]
     */
    public function getSortedNames(?string $order = null, bool $orderAsc = true, array|string $name = null, array|int $selectedID = null, ?string $extra = null): array {
        $field = $this->structure->getOrder($order);
        $query = Query::createOrderBy($field, $orderAsc);
        return $this->getSelect($query, $name, $selectedID, $extra);
    }

    /**
     * Returns all the Sorted Names using the given Query
     * @param Query                  $query
     * @param string|null            $order      Optional.
     * @param boolean                $orderAsc   Optional.
     * @param string[]|string|null   $name       Optional.
     * @param integer[]|integer|null $selectedID Optional.
     * @param string|null            $extra      Optional.
     * @return mixed[]
     */
    public function getSortedSelect(Query $query, ?string $order = null, bool $orderAsc = true, array|string $name = null, array|int $selectedID = null, ?string $extra = null): array {
        $field = $this->structure->getOrder($order);
        $query->orderBy($field, $orderAsc);
        return $this->getSelect($query, $name, $selectedID, $extra);
    }

    /**
     * Returns a select of Schemas
     * @param Query                  $query
     * @param string[]|string|null   $name       Optional.
     * @param integer[]|integer|null $selectedID Optional.
     * @param string|null            $extra      Optional.
     * @return mixed[]
     */
    public function getSelect(Query $query, array|string $name = null, array|int $selectedID = null, ?string $extra = null): array {
        $query     = $this->generateQuery($query);
        $selection = new Selection($this->db, $this->structure);
        $selection->addFields();
        $selection->addJoins();
        $selection->request($query);
        $request   = $selection->resolve();
        return Arrays::createSelect($request, $this->structure->idName, $name ?: $this->structure->name, $selectedID, $extra);
    }



    /**
     * Creates a new Schema
     * @param Request|array{}      $fields
     * @param array{}|integer|null $extra        Optional.
     * @param integer              $credentialID Optional.
     * @return integer
     */
    public function create(Request|array $fields, array|int $extra = null, int $credentialID = 0): int {
        $modification = new Modification($this->db, $this->structure);
        $modification->addFields($fields, $extra, $credentialID);
        $modification->addCreation();
        $modification->addModification();
        return $modification->insert();
    }

    /**
     * Replaces the Schema
     * @param Request|array{}      $fields
     * @param array{}|integer|null $extra        Optional.
     * @param integer              $credentialID Optional.
     * @return integer
     */
    public function replace(Request|array $fields, array|int $extra = null, int $credentialID = 0): int {
        $modification = new Modification($this->db, $this->structure);
        $modification->addFields($fields, $extra, $credentialID);
        $modification->addModification();
        return $modification->replace();
    }

    /**
     * Edits the Schema
     * @param Query|integer        $query
     * @param Request|array{}      $fields
     * @param array{}|integer|null $extra        Optional.
     * @param integer              $credentialID Optional.
     * @return boolean
     */
    public function edit(Query|int $query, Request|array $fields, array|int $extra = null, int $credentialID = 0): bool {
        $query        = $this->generateQueryID($query, false);
        $modification = new Modification($this->db, $this->structure);
        $modification->addFields($fields, $extra, $credentialID);
        $modification->addModification();
        return $modification->update($query);
    }



    /**
     * Updates a single value increasing it by the given amount
     * @param Query|integer $query
     * @param string        $column
     * @param integer       $amount
     * @return boolean
     */
    public function increase(Query|int $query, string $column, int $amount): bool {
        $query = $this->generateQueryID($query, false);
        return $this->db->increase($this->structure->table, $column, $amount, $query);
    }

    /**
     * Batches the Schema
     * @param array{} $fields
     * @return boolean
     */
    public function batch(array $fields): bool {
        return $this->db->batch($this->structure->table, $fields);
    }

    /**
     * Deletes the given Schema
     * @param Query|integer $query
     * @param integer       $credentialID Optional.
     * @return boolean
     */
    public function delete(Query|int $query, int $credentialID = 0): bool {
        $query = $this->generateQueryID($query, false);
        if ($this->structure->canDelete && $this->exists($query)) {
            $this->edit($query, [ "isDeleted" => 1 ], $credentialID);
            return true;
        }
        return false;
    }

    /**
     * Removes the given Schema
     * @param Query|integer $query
     * @return boolean
     */
    public function remove(Query|int $query): bool {
        $query = $this->generateQueryID($query, false);
        return $this->db->delete($this->structure->table, $query);
    }

    /**
     * Truncates the given Schema
     * @return boolean
     */
    public function truncate(): bool {
        return $this->db->truncate($this->structure->table);
    }



    /**
     * Creates and ensures the Order
     * @param Request      $request
     * @param array{}|null $extra        Optional.
     * @param Query|null   $queryOrder   Optional.
     * @param integer      $credentialID Optional.
     * @return integer
     */
    public function createWithOrder(Request $request, ?array $extra = null, ?Query $queryOrder = null, int $credentialID = 0): int {
        $this->ensurePosOrder(null, $request, $queryOrder);
        return $this->create($request, $extra, $credentialID);
    }

    /**
     * Edits and ensures the Order
     * @param Query|integer $query
     * @param Request       $request
     * @param array{}|null  $extra        Optional.
     * @param Query|null    $queryOrder   Optional.
     * @param integer       $credentialID Optional.
     * @return boolean
     */
    public function editWithOrder(Query|int $query, Request $request, ?array $extra = null, ?Query $queryOrder = null, int $credentialID = 0): bool {
        $model = $this->getOne($query);
        $this->ensurePosOrder($model, $request, $queryOrder);
        return $this->edit($query, $request, $extra, $credentialID);
    }

   /**
     * Deletes and ensures the Order
     * @param Query|integer $query
     * @param Query|null    $queryOrder   Optional.
     * @param integer       $credentialID Optional.
     * @return boolean
     */
    public function deleteWithOrder(Query|int $query, ?Query $queryOrder = null, int $credentialID = 0): bool {
        $model = $this->getOne($query);
        if ($this->delete($query, $credentialID)) {
            $this->ensurePosOrder($model, null, $queryOrder);
            return true;
        }
        return false;
    }

    /**
     * Ensures that the order of the Elements is correct
     * @param Model|null   $model   Optional.
     * @param Request|null $request Optional.
     * @param Query|null   $query   Optional.
     * @return boolean
     */
    public function ensurePosOrder(?Model $model = null, ?Request $request = null, ?Query $query = null): bool {
        $oldPosition = !empty($model)   ? $model->position             : 0;
        $newPosition = !empty($request) ? $request->getInt("position") : 0;
        $updPosition = $this->ensureOrder($oldPosition, $newPosition, $query);
        if (!empty($request)) {
            $request->position = $updPosition;
        }
        return true;
    }

    /**
     * Ensures that the order of the Elements is correct on Create/Edit
     * @param integer    $oldPosition
     * @param integer    $newPosition
     * @param Query|null $query       Optional.
     * @return integer
     */
    public function ensureOrder(int $oldPosition, int $newPosition, ?Query $query = null): int {
        $isEdit          = !empty($oldPosition);
        $nextPosition    = $this->getNextPosition($query);
        $oldPosition     = $isEdit ? $oldPosition : $nextPosition;
        $newPosition     = !empty($newPosition) ? $newPosition : $nextPosition;
        $updatedPosition = $newPosition;

        if (!$isEdit && (empty($newPosition) || $newPosition > $nextPosition)) {
            return $nextPosition;
        }
        if ($oldPosition == $newPosition) {
            return $newPosition;
        }

        if ($isEdit && $newPosition > $nextPosition) {
            $updatedPosition = $nextPosition - 1;
        }
        if ($newPosition > $oldPosition) {
            $newQuery = new Query($query);
            $newQuery->add("position",  ">",  $oldPosition);
            $newQuery->add("position",  "<=", $newPosition);
            $newQuery->add("isDeleted", "=",  0);
            $this->increase($newQuery, "position", -1);
        } else {
            $newQuery = new Query($query);
            $newQuery->add("position",  ">=", $newPosition);
            $newQuery->add("position",  "<",  $oldPosition);
            $newQuery->add("isDeleted", "=",  0);
            $this->increase($newQuery, "position", 1);
        }
        return $updatedPosition;
    }

    /**
     * Ensures that only one Element has the Unique field set
     * @param string     $field
     * @param integer    $id
     * @param integer    $oldValue
     * @param integer    $newValue
     * @param Query|null $query    Optional.
     * @return boolean
     */
    public function ensureUnique(string $field, int $id, int $oldValue, int $newValue, ?Query $query = null): bool {
        if (!empty($newValue) && empty($oldValue)) {
            $newQuery = new Query($query);
            $newQuery->add($this->structure->idKey, "<>", $id);
            $newQuery->add($field, "=", 1);
            $this->edit($newQuery, [ $field => 0 ]);
        }
        if (empty($newValue) && !empty($oldValue)) {
            $newQuery = new Query($query);
            $newQuery->orderBy($this->structure->getOrder(), true)->limit(1);
            $this->edit($newQuery, [ $field => 1 ]);
        }
        return true;
    }



    /**
     * Generates a Query
     * @param Query|null $query       Optional.
     * @param boolean    $withDeleted Optional.
     * @return Query
     */
    private function generateQuery(?Query $query = null, bool $withDeleted = true): Query {
        $query     = new Query($query);
        $isDeleted = $this->structure->getKey("isDeleted");

        if ($withDeleted && $this->structure->canDelete && !$query->hasColumn($isDeleted)) {
            $query->add($isDeleted, "=", 0);
        }
        return $query;
    }

    /**
     * Generates a Query with the ID or returns the Query
     * @param Query|integer $query
     * @param boolean       $withDeleted Optional.
     * @return Query
     */
    private function generateQueryID(Query|int $query, bool $withDeleted = true): Query {
        if (!($query instanceof Query)) {
            $query = Query::create($this->structure->idKey, "=", $query);
        }
        return $this->generateQuery($query, $withDeleted);
    }

    /**
     * Generates a Query with Sorting
     * @param Query|null   $query Optional.
     * @param Request|null $sort  Optional.
     * @return Query
     */
    private function generateQuerySort(?Query $query = null, ?Request $sort = null): Query {
        $query = $this->generateQuery($query);

        if (!empty($sort)) {
            if ($sort->has("orderBy")) {
                $query->orderBy($sort->orderBy, !empty($sort->orderAsc));
            }
            if ($sort->exists("page")) {
                $query->paginate($sort->page, $sort->amount);
            }
        } elseif (!$query->hasOrder() && !empty($this->structure->idKey)) {
            $query->orderBy($this->structure->idKey, true);
        }
        return $query;
    }
}
