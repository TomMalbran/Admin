<?php
namespace Admin\Schema;

use Admin\IO\Request;
use Admin\Schema\Database;
use Admin\Schema\Structure;
use Admin\Schema\Query;
use Admin\Utils\Arrays;

/**
 * The Modification Wrapper
 */
class Modification {

    private Database  $db;
    private Structure $structure;

    /** @var array{} */
    private array $fields;
    private int   $credentialID;


    /**
     * Creates a new Modification instance
     * @param Database  $db
     * @param Structure $structure
     */
    public function __construct(Database $db, Structure $structure) {
        $this->db        = $db;
        $this->structure = $structure;
    }



    /**
     * Adds all the Fields
     * @param Request|array{}      $fields
     * @param array{}|integer|null $extra        Optional.
     * @param integer              $credentialID Optional.
     * @return Modification
     */
    public function addFields(Request|array $fields, array|int $extra = null, int $credentialID = 0): Modification {
        if ($fields instanceof Request) {
            $this->fields = $this->parseFields($fields);
        } else {
            $this->fields = $fields;
        }
        if (!empty($extra)) {
            if (Arrays::isArray($extra)) {
                $this->fields = array_merge($this->fields, $extra);
            } else {
                $this->credentialID = $extra;
            }
        }
        if (!empty($credentialID)) {
            $this->credentialID = $credentialID;
        }
        return $this;
    }

    /**
     * Parses the data and returns the fields
     * @param Request $request
     * @return array{}
     */
    private function parseFields(Request $request): array {
        $result = [];
        foreach ($this->structure->fields as $field) {
            if ($field->canEdit) {
                $value = $field->fromRequest($request);

                if ($field->noEmpty) {
                    if (!empty($value)) {
                        $result[$field->key] = $value;
                    }
                } elseif ($value !== null) {
                    $result[$field->key] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * Adds the Creation Fields
     * @return Modification
     */
    public function addCreation(): Modification {
        if ($this->structure->canDelete && empty($this->fields["isDeleted"])) {
            $this->fields["isDeleted"] = 0;
        }
        if ($this->structure->hasTimestamps && empty($this->fields["createdTime"])) {
            $this->fields["createdTime"] = time();
        }
        if ($this->structure->hasUsers && !empty($this->credentialID)) {
            $this->fields["createdUser"] = $this->credentialID;
        }
        return $this;
    }

    /**
     * Adds the Modification Fields
     * @return Modification
     */
    public function addModification(): Modification {
        if ($this->structure->canEdit && $this->structure->hasTimestamps) {
            $this->fields["modifiedTime"] = time();
        }
        if ($this->structure->canEdit && $this->structure->hasUsers && !empty($this->credentialID)) {
            $this->fields["modifiedUser"] = $this->credentialID;
        }
        return $this;
    }



    /**
     * Inserts the Fields into the Database
     * @return integer
     */
    public function insert(): int {
        return $this->db->insert($this->structure->table, $this->fields);
    }

    /**
     * Replaces the Fields into the Database
     * @return integer
     */
    public function replace(): int {
        return $this->db->insert($this->structure->table, $this->fields, "REPLACE");
    }

    /**
     * Updates the Fields in the Database
     * @param Query $query
     * @return boolean
     */
    public function update(Query $query): bool {
        return $this->db->update($this->structure->table, $this->fields, $query);
    }
}
