<?php
namespace Admin\Schema;

use Admin\Schema\Field;
use Admin\Schema\Join;
use Admin\Schema\Count;
use Admin\Utils\Strings;

/**
 * The Database Structure
 */
class Structure {

    public string $table       = "";
    public string $idKey       = "";
    public string $idName      = "";
    public string $name        = "";

    /** @var Field[] */
    public array $fields       = [];

    /** @var Join[] */
    public array $joins        = [];

    /** @var Count[] */
    public array $counts       = [];

    public bool $hasStatus     = false;
    public bool $hasFemStatus  = false;
    public bool $hasPosition   = false;
    public bool $hasTimestamps = false;
    public bool $hasUsers      = false;
    public bool $canCreate     = false;
    public bool $canEdit       = false;
    public bool $canDelete     = false;


    /**
     * Creates a new Structure instance
     * @param array{} $data
     */
    public function __construct(array $data) {
        $this->table         = $data["table"];
        $this->hasStatus     = !empty($data["hasStatus"])     && $data["hasStatus"];
        $this->hasFemStatus  = !empty($data["hasFemStatus"])  && $data["hasFemStatus"];
        $this->hasPosition   = !empty($data["hasPosition"])   && $data["hasPosition"];
        $this->hasTimestamps = !empty($data["hasTimestamps"]) && $data["hasTimestamps"];
        $this->hasUsers      = !empty($data["hasUsers"])      && $data["hasUsers"];
        $this->canCreate     = $data["canCreate"];
        $this->canEdit       = $data["canEdit"];
        $this->canDelete     = $data["canDelete"];

        // Add additional Fields
        if ($this->hasStatus) {
            $data["fields"]["status"] = [
                "type"    => Field::Status,
                "default" => 0,
            ];
        }
        if ($this->hasFemStatus) {
            $data["fields"]["status"] = [
                "type"    => Field::FemStatus,
                "default" => 0,
            ];
        }
        if ($this->hasPosition) {
            $data["fields"]["position"] = [
                "type"    => Field::Number,
                "default" => 0,
            ];
        }
        if ($this->canCreate && $this->hasTimestamps) {
            $data["fields"]["createdTime"] = [
                "type"     => Field::Date,
                "cantEdit" => true,
                "default"  => 0,
            ];
        }
        if ($this->canCreate && $this->hasUsers) {
            $data["fields"]["createdUser"] = [
                "type"     => Field::Number,
                "cantEdit" => true,
                "default"  => "",
            ];
        }
        if ($this->canEdit && $this->hasTimestamps) {
            $data["fields"]["modifiedTime"] = [
                "type"     => Field::Date,
                "cantEdit" => true,
                "default"  => 0,
            ];
        }
        if ($this->canEdit && $this->hasUsers) {
            $data["fields"]["modifiedUser"] = [
                "type"     => Field::Number,
                "cantEdit" => true,
                "default"  => "",
            ];
        }
        if ($this->canDelete) {
            $data["fields"]["isDeleted"] = [
                "type"     => Field::Boolean,
                "cantEdit" => true,
                "default"  => 0,
            ];
        }

        // Parse the Fields
        $idKey        = "";
        $primaryCount = 0;

        foreach ($data["fields"] as $key => $value) {
            if ($value["type"] == Field::ID) {
                $data["fields"][$key]["isPrimary"] = true;
                $idKey = $key;
            }
            if (!empty($value["isPrimary"])) {
                $primaryCount += 1;
            }
            if (empty($idKey) && !empty($value["isPrimary"])) {
                $idKey = $key;
            }
        }
        if ($primaryCount > 1) {
            $idKey = "";
        }

        // Create the Fields
        foreach ($data["fields"] as $key => $value) {
            $field = new Field($key, $value);
            if ($key == $idKey) {
                $this->idKey  = $field->key;
                $this->idName = $field->name;
            }
            if ($field->isName) {
                $this->name = $field->type == Field::Text ? "{$field->key}Short" : $field->key;
            }
            $this->fields[] = $field;
        }

        // Create the Joins
        if (!empty($data["joins"])) {
            foreach ($data["joins"] as $key => $value) {
                $this->joins[] = new Join($key, $value);
            }
        }

        // Create the Counts
        if (!empty($data["counts"])) {
            foreach ($data["counts"] as $key => $value) {
                $this->counts[] = new Count($key, $value);
            }
        }
    }



    /**
     * Returns the Key adding the table as the prefix
     * @param string $key
     * @return string
     */
    public function getKey(string $key): string {
        if (!Strings::contains($key, ".")) {
            $mainKey = $this->table;
            return "{$mainKey}.{$key}";
        }
        return $key;
    }

    /**
     * Returns the Order Field
     * @param string|null $field Optional.
     * @return string
     */
    public function getOrder(?string $field = null): string {
        if (!empty($field)) {
            return $field;
        }
        return $this->hasPosition ? "position" : $this->name;
    }
}
