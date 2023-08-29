<?php
namespace Admin\Schema;

use Admin\Schema\Field;
use Admin\Utils\Arrays;

/**
 * The Database Join
 */
class Join {

    public string $key       = "";
    public string $table     = "";
    public string $asTable   = "";
    public string $onTable   = "";
    public string $leftKey   = "";
    public string $rightKey  = "";
    public string $and       = "";

    public bool   $hasPrefix = false;
    public string $prefix    = "";

    /** @var Field[] */
    public array  $fields    = [];

    /** @var object[] */
    public array  $merges    = [];



    /**
     * Creates a new Join instance
     * @param string  $key
     * @param array{} $data
     */
    public function __construct(string $key, array $data) {
        $this->key       = $key;
        $this->table     = $data["table"];
        $this->asTable   = !empty($data["asTable"])   ? $data["asTable"]      : null;
        $this->onTable   = !empty($data["onTable"])   ? $data["onTable"]      : null;
        $this->leftKey   = !empty($data["leftKey"])   ? $data["leftKey"]      : $key;
        $this->rightKey  = !empty($data["rightKey"])  ? $data["rightKey"]     : $key;
        $this->and       = !empty($data["and"])       ? "AND " . $data["and"] : "";

        $this->hasPrefix = !empty($data["prefix"]);
        $this->prefix    = !empty($data["prefix"])    ? $data["prefix"]       : "";


        // Creates the Fields
        if (!empty($data["fields"])) {
            foreach ($data["fields"] as $key => $value) {
                $this->fields[] = new Field($key, $value, $this->prefix);
            }
        } elseif (!empty($data["fieldKeys"])) {
            foreach ($data["fieldKeys"] as $key) {
                $this->fields[] = new Field($key, [ "type" => Field::String ], $this->prefix);
            }
        }

        // Creates the Merges
        foreach ($this->fields as $field) {
            if ($field->hasMerge) {
                if (empty($this->merges[$field->mergeTo])) {
                    $this->merges[$field->mergeTo] = (object)[
                        "key"    => $this->hasPrefix ? $this->prefix . ucfirst($field->mergeTo) : $field->mergeTo,
                        "glue"   => !empty($data["mergeGlue"]) ? $data["mergeGlue"] : " ",
                        "fields" => [],
                    ];
                }
                $this->merges[$field->mergeTo]->fields[] = $field->prefixName;
            }
        }
    }



    /**
     * Returns the Values for the given Field
     * @param array{} $data
     * @return array{}
     */
    public function toValues(array $data): array {
        $result = [];
        foreach ($this->fields as $field) {
            $values = $field->toValues($data);
            $result = array_merge($result, $values);
        }
        foreach ($this->merges as $merge) {
            $result[$merge->key] = Arrays::getValue($data, $merge->fields, $merge->glue);
        }
        return $result;
    }
}
