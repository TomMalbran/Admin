<?php
namespace Admin\IO;

use Admin\IO\Request;
use Admin\IO\Errors;
use Admin\IO\Url;

/**
 * The Navigation wrapper
 */
class Navigation {

    private Request $request;
    private Errors  $errors;

    private int   $amount     = 0;
    private int   $total      = 0;
    private int   $page       = 0;
    private int   $from       = 0;
    private int   $to         = 0;
    private int   $totalPages = 0;
    private int   $fromTime   = 0;
    private int   $toTime     = 0;

    /** @var mixed[] */
    private array $list       = [];

    /** @var mixed[] */
    private array $pages      = [];

    /** @var array{} */
    private array $filters    = [];

    /** @var array{} */
    private array $extras     = [];


    /**
     * Creates a new Navigation instance
     * @param Request $request
     * @param integer $amount  Optional.
     */
    public function __construct(Request $request, int $amount = 50) {
        $this->request = $request;
        $this->amount  = $amount;
        $this->page    = max($this->request->getInt("page"), 1);
        $this->from    = ($this->page - 1) * $amount;
        $this->to      = $this->from + $amount - 1;
        $this->errors  = new Errors();
    }

    /**
     * Gets a Property
     * @param string $property
     * @return mixed
     */
    public function __get(string $property) {
        if (isset($this->filters[$property])) {
            return $this->filters[$property];
        }
        if (isset($this->{$property})) {
            return $this->{$property};
        }
        return null;
    }



    /**
     * Returns true if there are Errors
     * @return boolean
     */
    public function hasErrors(): bool {
        return $this->errors->has();
    }

    /**
     * Sets the Filter
     * @param string ...$filters
     * @return Navigation
     */
    public function setFilters(string ...$filters): Navigation {
        foreach ($filters as $key) {
            $this->filters[$key] = $this->request->get($key);
        }
        return $this;
    }

    /**
     * Sets the From and To Dates from the Request
     * @param boolean $useDefaults Optional.
     * @param integer $months      Optional.
     * @param boolean $inBetween   Optional.
     * @return Navigation
     */
    public function setDates(bool $useDefaults = false, int $months = 0, bool $inBetween = false): Navigation {
        // From and to Date
        if ($this->request->has("from") && $this->request->has("to")) {
            if (!$this->request->isValidDate("from") || !$this->request->isValidDate("to")) {
                $this->errors->add("dates");
            } elseif (!$this->request->isValidPeriod("from", "to")) {
                $this->errors->add("period");
            } else {
                $this->fromTime = $this->request->toDayStart("from");
                $this->toTime   = $this->request->toDayEnd("to");
            }

        // Just From Date
        } elseif ($this->request->has("from")) {
            if (!$this->request->isValidDate("from")) {
                $this->errors->add("dates");
            } else {
                $this->fromTime = $this->request->toDayStart("from");
            }

        // Just To Date
        } elseif ($this->request->has("to")) {
            if (!$this->request->isValidDate("to")) {
                $this->errors->add("dates");
            } else {
                $this->toTime = $this->request->toDayStart("to");
            }
        }

        // Use Default Values
        if ($useDefaults && empty($this->fromTime) && empty($this->toTime)) {
            if ($inBetween) {
                $months         = $months ?: 4;
                $this->fromTime = mktime(0, 0, 0, date("n") - $months, 1, date("Y"));
                $this->toTime   = mktime(0, 0, 0, date("n") + $months, 1, date("Y")) - 1;
            } else {
                $months         = $months ?: 6;
                $this->fromTime = mktime(0,   0,  0, date("n") - $months, 1, date("Y"));
                $this->toTime   = mktime(23, 59, 59, date("n"), date("j"), date("Y"));
            }
        }
        return $this;
    }



    /**
     * Adds Filters to the Query
     * @return Url
     */
    public function getQuery(): Url {
        $this->request->addFilter($this->filters);
        return $this->request->getQuery();
    }

    /**
     * Returns a Query string with a Filter
     * @param string $filterKey
     * @param mixed  $filterValue
     * @return string
     */
    public function getFilter(string $filterKey, mixed $filterValue): string {
        return $this->getQuery()->set($filterKey, $filterValue)->toString();
    }

    /**
     * Sets the Navigation List and Total and creates the Pagination
     * @param mixed[] $list
     * @param integer $total
     * @return Navigation
     */
    public function setData(array $list, int $total): Navigation {
        $this->list       = $list;
        $this->total      = $total;
        $this->totalPages = ceil($total / $this->amount);

        $totalPages = 5;
        $middlePage = ceil($totalPages / 2);
        $fromPad    = $this->page > $this->totalPages - $middlePage ? $totalPages - ($this->totalPages - $this->page) : $middlePage;
        $toPad      = $this->page < $middlePage + 1                 ? $totalPages + 1 - $this->page                   : $middlePage;
        $fromPage   = max(1,                 $this->page - $fromPad);
        $toPage     = min($this->totalPages, $this->page + $toPad);

        $this->pages = [];
        if ($this->totalPages > 1) {
            for ($i = $fromPage; $i <= $toPage; $i++) {
                $this->pages[] = [
                    "number"    => $i,
                    "isCurrent" => $i == $this->page,
                    "pageQuery" => $this->getFilter("page", $i),
                ];
            }
        }
        return $this;
    }

    /**
     * Creates a Response for the Admin
     * @return mixed[]
     */
    public function create(): array {
        $search   = !empty($this->filters["search"]) ? $this->filters["search"] : "";
        $prevPage = max(1, $this->page - 1);
        $nextPage = min($this->totalPages, $this->page + 1);

        $classes  = [ "table-container", "table-list" ];
        if (!empty($this->pages)) {
            $classes[] = "table-pagination";
        }

        return [
            "tableClasses" => implode(" ", $classes),
            "hasList"      => !empty($this->list),
            "list"         => $this->list,
            "page"         => $this->page,
            "pages"        => $this->pages,
            "hasPages"     => !empty($this->pages),
            "hasMany"      => empty($this->pages) && count($this->list) > 10,
            "hasPrev"      => $this->from > 0,
            "hasNext"      => $this->to   < $this->total,
            "firstQuery"   => $this->getFilter("page", 1),
            "prevQuery"    => $this->getFilter("page", $prevPage),
            "nextQuery"    => $this->getFilter("page", $nextPage),
            "lastQuery"    => $this->getFilter("page", $this->totalPages),
            "filterQuery"  => $this->getQuery()->remove("page")->toString(),
            "fromTimeDate" => !empty($this->fromTime) ? date("d-m-Y", $this->fromTime) : $this->request->from,
            "toTimeDate"   => !empty($this->toTime)   ? date("d-m-Y", $this->toTime)   : $this->request->to,
            "search"       => $search,
        ] + $this->extras;
    }
}
