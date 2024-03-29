<?php
namespace Admin\Utils;

use Admin\Utils\Strings;

/**
 * Several Date Time functions
 */
class DateTime {

    /** @var string[] */
    public static array $months     = [ "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre" ];

    public static int $serverDiff = -180;
    public static int $timeDiff   = 0;


    /**
     * Sets the Time Zone in minutes
     * @param integer $timezone
     * @return integer
     */
    public static function setTimezone(int $timezone): int {
        self::$timeDiff = (self::$serverDiff - $timezone) * 60;
        return self::$timeDiff;
    }

    /**
     * Returns the given time using the given Time Zone
     * @param integer $value
     * @param integer $timezone
     * @return integer
     */
    public static function toTimezone(int $value, int $timezone): int {
        if (!empty($value) && !empty($timezone)) {
            $timeDiff = (self::$serverDiff - $timezone) * 60;
            return $value - $timeDiff;
        }
        return $value;
    }

    /**
     * Returns the given time in the User Time Zone
     * @param integer $value
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public static function toUserTime(int $value, bool $useTimezone = true): int {
        if (!empty($value) && $useTimezone) {
            return $value - self::$timeDiff;
        }
        return $value;
    }

    /**
     * Returns the given time in the Server Time Zone
     * @param integer $value
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public static function toServerTime(int $value, bool $useTimezone = true): int {
        if (!empty($value) && $useTimezone) {
            return $value + self::$timeDiff;
        }
        return $value;
    }



    /**
     * Returns the Server Date
     * @return string
     */
    public static function getServerDate(): string {
        return date("d-m-Y @ H:i", time());
    }

    /**
     * Returns the User Date
     * @return string
     */
    public static function getUserDate(): string {
        return date("d-m-Y @ H:i", self::toUserTime(time()));
    }



    /**
     * Returns the given string as a time
     * @param string  $string
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public static function toTime(string $string, bool $useTimezone = true): int {
        $result = strtotime($string);
        if ($result !== false) {
            return self::toServerTime($result, $useTimezone);
        }
        return 0;
    }

    /**
     * Returns the given string as a time
     * @param string  $dateString
     * @param string  $hourString
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public static function toTimeHour(string $dateString, string $hourString, bool $useTimezone = true): int {
        $result = strtotime("$dateString $hourString");
        if ($result !== false) {
            return self::toServerTime($result, $useTimezone);
        }
        return 0;
    }

    /**
     * Returns the given string as a time
     * @param string  $string
     * @param string  $type        Optional.
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public static function toDay(string $string, string $type = "start", bool $useTimezone = true): int {
        return match ($type) {
            "start" => self::toDayStart($string, $useTimezone),
            "end"   => self::toDayEnd($string, $useTimezone),
            default => self::toDayMiddle($string, $useTimezone),
        };
    }

    /**
     * Returns the given string as a time of the start of the day
     * @param string  $string
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public static function toDayStart(string $string, bool $useTimezone = true): int {
        $result = strtotime($string);
        if ($result !== false) {
            return self::toServerTime($result, $useTimezone);
        }
        return 0;
    }

    /**
     * Returns the given string as a time of the middle of the day
     * @param string  $string
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public static function toDayMiddle(string $string, bool $useTimezone = true): int {
        $result = strtotime($string);
        if ($result !== false) {
            $result += 12 * 3600;
            return self::toServerTime($result, $useTimezone);
        }
        return 0;
    }

    /**
     * Returns the given string as a time of the end of the day
     * @param string  $string
     * @param boolean $useTimezone Optional.
     * @return integer
     */
    public static function toDayEnd(string $string, bool $useTimezone = true): int {
        $result = strtotime($string);
        if ($result !== false) {
            $result += 24 * 3600 - 1;
            return self::toServerTime($result, $useTimezone);
        }
        return 0;
    }



    /**
     * Returns true if the given date is Valid
     * @param string $string
     * @return boolean
     */
    public static function isValidDate(string $string): bool {
        return strtotime($string) !== false;
    }

    /**
     * Returns true if the given hour is Valid
     * @param string         $string
     * @param integer[]|null $minutes Optional.
     * @return boolean
     */
    public static function isValidHour(string $string, ?array $minutes = null): bool {
        $parts = Strings::split($string, ":");
        return (
            isset($parts[0]) && Numbers::isValid($parts[0], 0, 23) &&
            isset($parts[1]) && Numbers::isValid($parts[1], 0, 59) &&
            (empty($minutes) || Arrays::contains($minutes, $parts[1]))
        );
    }

    /**
     * Returns true if the given dates are a valid period
     * @param string  $fromDate
     * @param string  $toDate
     * @param boolean $useTimezone Optional.
     * @return boolean
     */
    public static function isValidPeriod(string $fromDate, string $toDate, bool $useTimezone = true): bool {
        $fromTime = self::toDayStart($fromDate, $useTimezone);
        $toTime   = self::toDayEnd($toDate, $useTimezone);

        return $fromTime !== null && $toTime !== null && $fromTime < $toTime;
    }

    /**
     * Returns true if the given hours are a valid period
     * @param string $fromHour
     * @param string $toHour
     * @return boolean
     */
    public static function isValidHourPeriod(string $fromHour, string $toHour): bool {
        $date     = date("d-m-Y");
        $fromTime = self::toTimeHour($date, $fromHour);
        $toTime   = self::toTimeHour($date, $toHour);

        return $fromTime !== 0 && $toTime !== 0 && $fromTime < $toTime;
    }

    /**
     * Returns true if the given dates with hours are a valid period
     * @param string  $fromDate
     * @param string  $fromHour
     * @param string  $toDate
     * @param string  $toHour
     * @param boolean $useTimezone Optional.
     * @return boolean
     */
    public static function isValidFullPeriod(
        string $fromDate,
        string $fromHour,
        string $toDate,
        string $toHour,
        bool $useTimezone = true
    ): bool {
        $fromTime = self::toTimeHour($fromDate, $fromHour, $useTimezone);
        $toTime   = self::toTimeHour($toDate, $toHour, $useTimezone);

        return $fromTime !== 0 && $toTime !== 0 && $fromTime < $toTime;
    }

    /**
     * Returns true if the given week day is valid
     * @param integer $weekDay
     * @return boolean
     */
    public static function isValidWeekDay(int $weekDay): bool {
        return Numbers::isValid($weekDay, 0, 6);
    }



    /**
     * Returns true if the given Date is in the future
     * @param string  $date
     * @param string  $type        Optional.
     * @param boolean $useTimezone Optional.
     * @return boolean
     */
    public static function isFutureDate(string $date, string $type = "middle", bool $useTimezone = true): bool {
        $time = self::toDay($date, $type, $useTimezone);
        return self::isFutureTime($time);
    }

    /**
     * Returns true if the given Time is in the future
     * @param integer $time
     * @return boolean
     */
    public static function isFutureTime(int $time): bool {
        return $time > time();
    }

    /**
     * Returns true if the given Time is between the from and to Times
     * @param integer $time
     * @param integer $fromTime
     * @param integer $toTime
     * @return boolean
     */
    public static function isBetween(int $time, int $fromTime, int $toTime): bool {
        return $time >= $fromTime && $time <= $toTime;
    }

    /**
     * Returns true if the current Time is between the from and to Times
     * @param integer $fromTime
     * @param integer $toTime
     * @return boolean
     */
    public static function isCurrentBetween(int $fromTime, int $toTime): bool {
        return self::isBetween(time(), $fromTime, $toTime);
    }



    /**
     * Formats the time using the given Time Zone
     * @param integer      $seconds
     * @param string       $format
     * @param integer|null $timezone Optional.
     * @return string
     */
    public static function format(int $seconds, string $format, ?int $timezone = null): string {
        if (!empty($timezone)) {
            $seconds = self::toTimezone($seconds, $timezone);
        }
        return date($format, $seconds);
    }

    /**
     * Returns the Seconds as a string
     * @param integer $seconds
     * @return string
     */
    public static function toTimeString(int $seconds): string {
        $secsInMinute = 60;
        $secsInHour   = 60 * $secsInMinute;
        $secsInDay    = 24 * $secsInHour;
        $secsInWeek   = 7  * $secsInDay;

        // Extract the Weeks
        $weeks       = floor($seconds / $secsInWeek);

        // Extract the Days
        $daySeconds  = $seconds % $secsInWeek;
        $days        = floor($daySeconds / $secsInDay);

        // Extract the Hours
        $hourSeconds = $daySeconds % $secsInDay;
        $hours       = floor($hourSeconds / $secsInHour);

        // Extract the Minutes
        $minSeconds  = $daySeconds % $secsInHour;
        $mins        = floor($minSeconds / $secsInMinute);

        // Generate the Result
        if ($mins == 0) {
            return "0";
        }
        if ($hours == 0) {
            return "{$mins}m";
        }
        if ($days == 0) {
            return "{$hours}h";
        }
        if ($weeks == 0) {
            return "{$days}d-{$hours}h";
        }
        return "{$weeks}w-{$days}d-{$hours}h";
    }

    /**
     * Returns the Seconds as a days string
     * @param integer $seconds
     * @return string
     */
    public static function toDayString(int $seconds): string {
        $secsInDay = 24 * 3600;
        $days      = floor($seconds / $secsInDay);
        return "{$days}d";
    }

    /**
     * Returns the Time as a Day string
     * @param integer $time
     * @return string
     */
    public static function getDayString(int $time): string {
        $result = date("d/m/Y", $time);
        if ($time >= strtotime("today")) {
            $result = "Hoy";
        } elseif ($time >= strtotime("yesterday")) {
            $result = "Ayer";
        } elseif (date("Y", $time) == date("Y")) {
            $result = date("d/m", $time);
        }
        return $result;
    }

    /**
     * Returns the Time as a Day string
     * @param integer $fromTime
     * @return string
     */
    public static function getWeekString(int $fromTime): string {
        $toTime = $fromTime + 6 * 24 * 3600;
        return "Del " . date("d/m/Y", $fromTime) . " al " . date("d/m/Y", $toTime);
    }



    /**
     * Returns the Month at the given month
     * @param integer $time
     * @return string
     */
    public static function getMonth(int $time): string {
        $month = date("n", $time);
        return self::$months[$month - 1];
    }

    /**
     * Returns a short version of the Month
     * @param integer $time
     * @return string
     */
    public static function getShortMonth(int $time): string {
        $result = self::getMonth($time);
        $result = Strings::substring($result, 0, 3);
        return Strings::toUpperCase($result);
    }

    /**
     * Returns the Month and Year at the given month
     * @param integer $time
     * @return string
     */
    public static function getMonthYear(int $time): string {
        return self::getMonth($time) . " " . date("Y", $time);
    }



    /**
     * Returns the difference between 2 dates in Months
     * @param integer      $time1
     * @param integer      $time2
     * @param integer|null $min   Optional.
     * @return integer
     */
    public static function getMonthsDiff(int $time1, int $time2, ?int $min = null): int {
        $diff = 12 * (date("Y", $time1) - date("Y", $time2)) + date("n", $time1) - date("n", $time2);
        return $min !== null ? max($diff, $min) : $diff;
    }

    /**
     * Returns the difference between 2 dates in Weeks
     * @param integer      $time1
     * @param integer      $time2
     * @param integer|null $min   Optional.
     * @return integer
     */
    public static function getWeeksDiff(int $time1, int $time2, ?int $min = null): int {
        $diff = floor(($time1 - $time2) / (7 * 24 * 3600));
        return $min !== null ? max($diff, $min) : $diff;
    }

    /**
     * Returns the difference between 2 dates in Days
     * @param integer      $time1
     * @param integer      $time2
     * @param integer|null $min   Optional.
     * @return integer
     */
    public static function getDaysDiff(int $time1, int $time2, ?int $min = null): int {
        $diff = floor(($time1 - $time2) / (24 * 3600));
        return $min !== null ? max($diff, $min) : $diff;
    }

    /**
     * Returns the difference between 2 dates in Hours
     * @param integer      $time1
     * @param integer      $time2
     * @param integer|null $min   Optional.
     * @return integer
     */
    public static function getHoursDiff(int $time1, int $time2, ?int $min = null): int {
        $diff = floor(($time1 - $time2) / 3600);
        return $min !== null ? max($diff, $min) : $diff;
    }

    /**
     * Returns the difference between 2 dates in Minutes
     * @param integer      $time1
     * @param integer      $time2
     * @param integer|null $min   Optional.
     * @return integer
     */
    public static function getMinsDiff(int $time1, int $time2, ?int $min = null): int {
        $diff = floor(($time1 - $time2) / 60);
        return $min !== null ? max($diff, $min) : $diff;
    }



    /**
     * Creates an Hour Select
     * @param string  $selected Optional.
     * @param boolean $withHalf Optional.
     * @return mixed[]
     */
    public static function getHourSelect(string $selected = "", bool $withHalf = false): array {
        $minutes = $withHalf ? [ "00", "30" ] : [ "00" ];
        $result  = [];

        for ($h = 7; $h < 24; $h++) {
            foreach ($minutes as $m) {
                $string   = str_pad($h, 2, "0", STR_PAD_LEFT) . ":$m";
                $result[] = [
                    "key"        => "$h:$m",
                    "value"      => $string,
                    "isSelected" => $string == $selected,
                ];
            }
        }
        return $result;
    }
}
