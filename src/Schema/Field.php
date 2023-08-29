<?php
namespace Admin\Schema;

use Admin\IO\Request;
use Admin\IO\Status;
use Admin\File\Path;
use Admin\Utils\JSON;
use Admin\Utils\Arrays;
use Admin\Utils\Numbers;
use Admin\Utils\Strings;

/**
 * The Database Field
 */
class Field {

    // The Types
    const ID        = "id";
    const Boolean   = "boolean";
    const Binary    = "binary";
    const Number    = "number";
    const Float     = "float";
    const Price     = "price";
    const Date      = "date";
    const Hour      = "hour";
    const String    = "string";
    const JSON      = "json";
    const CSV       = "csv";
    const Text      = "text";
    const File      = "file";
    const Image     = "image";
    const Status    = "status";
    const FemStatus = "fstatus";

    // The Data
    public string $key        = "";
    public string $type       = "";
    public int    $length     = 0;
    public int    $decimals   = 3;
    public string $dateType   = "";
    public string $date       = "";
    public string $hour       = "";
    public mixed  $default    = null;

    public bool   $isPrimary  = false;
    public bool   $isKey      = false;
    public bool   $isName     = false;
    public bool   $noEmpty    = false;
    public bool   $isSigned   = false;
    public bool   $noPrefix   = false;
    public bool   $canEdit    = false;

    public bool   $hasMerge   = false;
    public string $mergeTo    = "";

    public bool   $hasName    = false;
    public string $name       = "";
    public string $prefix     = "";
    public string $prefixName = "";


    /**
     * Creates a new Field instance
     * @param string  $key
     * @param array{} $data
     * @param string  $prefix Optional.
     */
    public function __construct(string $key, array $data, string $prefix = "") {
        $this->key        = $key;

        $this->type       = !empty($data["type"])     ? $data["type"]          : Field::String;
        $this->length     = !empty($data["length"])   ? (int)$data["length"]   : 0;
        $this->decimals   = !empty($data["decimals"]) ? (int)$data["decimals"] : 3;
        $this->dateType   = !empty($data["dateType"]) ? $data["dateType"]      : "middle";
        $this->date       = !empty($data["date"])     ? $data["date"]          : null;
        $this->hour       = !empty($data["hour"])     ? $data["hour"]          : null;
        $this->default    = isset($data["default"])   ? $data["default"]       : null;

        $this->isPrimary  = !empty($data["isPrimary"]);
        $this->isKey      = !empty($data["isKey"]);
        $this->isName     = !empty($data["isName"]);
        $this->noEmpty    = !empty($data["noEmpty"]);
        $this->isSigned   = !empty($data["isSigned"]);
        $this->noPrefix   = !empty($data["noPrefix"]);
        $this->canEdit    = empty($data["cantEdit"]);

        $this->hasMerge   = !empty($data["mergeTo"]);
        $this->mergeTo    = !empty($data["mergeTo"]) ? $data["mergeTo"] : "";

        $this->hasName    = !empty($data["name"]);
        $this->name       = !empty($data["name"]) ? $data["name"] : $key;
        $this->prefix     = $prefix;
        $this->prefixName = $this->getFieldKey();
    }

    /**
     * Creates a Field with a Prefix
     * @return string
     */
    public function getFieldKey(): string {
        $result = $this->name;
        if (!empty($this->prefix) && !$this->noPrefix) {
            $result = $this->prefix . ucfirst($this->name);
        }
        return $result;
    }



    /**
     * Returns the Field Type from the Data
     * @return string
     */
    public function getType(): string {
        $result = "unknown";

        switch ($this->type) {
        case self::ID:
            $result = "int(10) unsigned NOT NULL AUTO_INCREMENT";
            break;
        case self::Binary:
        case self::Boolean:
            $result = "tinyint(1) unsigned NOT NULL";
            break;
        case self::Number:
        case self::Float:
        case self::Price:
        case self::Date:
        case self::Hour:
            $length = $this->length ?: 10;
            $sign   = $this->isSigned ? "" : " unsigned";
            $type   = "int";
            if ($length < 3) {
                $type = "tinyint";
            } elseif ($length < 5) {
                $type = "smallint";
            } elseif ($length < 8) {
                $type = "mediumint";
            } elseif ($length > 10) {
                $type = "bigint";
            }
            $result = "$type($length)$sign NOT NULL";
            break;
        case self::String:
        case self::File:
        case self::Image:
            $length = $this->length ?: 255;
            $result = "varchar($length) NOT NULL";
            break;
        case self::JSON:
        case self::CSV:
        case self::Text:
            $result = "text NOT NULL";
            break;
        case self::Status:
        case self::FemStatus:
            $result = "tinyint(1) unsigned NOT NULL";
            break;
        }

        if ($result != "unknown" && $this->default !== null) {
            $result .= " DEFAULT '{$this->default}'";
        }
        return $result;
    }

    /**
     * Returns the Field Value from the given Request
     * @param Request $request
     * @return mixed
     */
    public function fromRequest(Request $request): mixed {
        $result = null;

        switch ($this->type) {
        case self::ID:
            break;
        case self::Boolean:
        case self::Binary:
            $result = $request->toBinary($this->name);
            break;
        case self::Number:
            $result = $request->getInt($this->name);
            break;
        case self::Float:
            $result = $request->toInt($this->name, $this->decimals);
            break;
        case self::Price:
            $result = $request->toCents($this->name);
            break;
        case self::Date:
            if (!empty($this->date)) {
                $result = $request->toDay($this->date, $this->dateType, true);
            } elseif ($request->has("{$this->name}Date")) {
                $result = $request->toDay("{$this->name}Date", $this->dateType, true);
            } else {
                $result = $request->toDay($this->name, $this->dateType, true);
            }
            break;
        case self::Hour:
            if (!empty($this->date) && !empty($this->hour)) {
                $result = $request->toTimeHour($this->date, $this->hour, true);
            } elseif ($request->has("{$this->name}Date") && $request->has("{$this->name}Hour")) {
                $result = $request->toTimeHour("{$this->name}Date", "{$this->name}Hour", true);
            }
            break;
        case self::JSON:
        case self::CSV:
            $result = $request->toJSON($this->name);
            break;
        case self::Status:
        case self::FemStatus:
            $result = $request->getInt($this->name);
            break;
        default:
            $result = $request->get($this->name);
        }
        return $result;
    }

    /**
     * Returns the Field Values from the given Data
     * @param array{} $data
     * @return array{}
     */
    public function toValues(array $data): array {
        $key    = $this->prefixName;
        $text   = isset($data[$key]) ? (string)$data[$key] : "";
        $number = isset($data[$key]) ? (int)$data[$key]    : 0;
        $result = [];

        switch ($this->type) {
        case self::Boolean:
            $result[$key]           = !empty($data[$key]);
            break;
        case self::ID:
        case self::Binary:
        case self::Number:
            $result[$key]           = $number;
            break;
        case self::Float:
            $result[$key]           = Numbers::toFloat($number, $this->decimals);
            $result["{$key}Format"] = Numbers::formatFloat($number, $this->decimals);
            $result["{$key}Int"]    = $number;
            break;
        case self::Price:
            $result[$key]           = Numbers::fromCents($number);
            $result["{$key}Format"] = Numbers::formatCents($number);
            $result["{$key}Cents"]  = $number;
            break;
        case self::Date:
        case self::Hour:
            $result[$key]           = $number;
            $result["{$key}Date"]   = !empty($number) ? date("d-m-Y",       $number) : "";
            $result["{$key}Full"]   = !empty($number) ? date("d-m-Y @ H:i", $number) : "";
            $result["{$key}ISO"]    = !empty($number) ? date("Y-m-d H:i",   $number) : "";
            break;
        case self::JSON:
            $result[$key]           = JSON::decode($text, true);
            break;
        case self::CSV:
            $result[$key]           = $text;
            $result["{$key}Parts"]  = JSON::decode($text, true);
            $result["{$key}Count"]  = Arrays::length($result["{$key}Parts"]);
            break;
        case self::Text:
            $result[$key]           = $text;
            $result["{$key}Html"]   = Strings::toHtml($text);
            break;
        case self::File:
            $result[$key]           = $text;
            $result["{$key}Url"]    = !empty($text) ? Path::getUrl(Path::Source, $text) : "";
            break;
        case self::Image:
            $result[$key]           = $text;
            $result["{$key}Url"]    = !empty($text) ? Path::getUrl(Path::Source, $text) : "";
            $result["{$key}Large"]  = !empty($text) ? Path::getUrl(Path::Large,  $text) : "";
            $result["{$key}Medium"] = !empty($text) ? Path::getUrl(Path::Medium, $text) : "";
            $result["{$key}Small"]  = !empty($text) ? Path::getUrl(Path::Small,  $text) : "";
            $result["{$key}Thumb"]  = !empty($text) ? Path::getUrl(Path::Thumb,  $text) : "";
            break;
        case self::Status:
            $result[$key]           = $number;
            $result["isActive"]     = $number == Status::Active;
            $result["{$key}Name"]   = Status::getName($number);
            break;
        case self::FemStatus:
            $result[$key]           = $number;
            $result["isActive"]     = $number == Status::Active;
            $result["{$key}Name"]   = Status::getFemName($number);
            break;
        default:
            $result[$key]           = $text;
        }
        return $result;
    }
}
