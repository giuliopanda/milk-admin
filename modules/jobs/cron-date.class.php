<?php
namespace Modules\Jobs;
/**
 * Class for managing CRON timing
 * cron-date.class.php
 * 
 * Allows managing CRON timings both via standard string
 * and via a fluent API (chain).
 */
class CronDateManager
{
    // Default values for CRON fields (* means "all possible values")
    private $minutes = '*';      // 0-59
    private $hours = '*';        // 0-23
    private $day_of_month = '*'; // 1-31
    private $month = '*';        // 1-12
    private $day_of_week = '*';  // 0-6 (0 = Sunday)
    private $year = '*';         // Optional, * = every year

    // Value limits for each field
    private $limits = [
        'minutes' => ['min' => 0, 'max' => 59],
        'hours' => ['min' => 0, 'max' => 23],
        'day_of_month' => ['min' => 1, 'max' => 31],
        'month' => ['min' => 1, 'max' => 12],
        'day_of_week' => ['min' => 0, 'max' => 6],
        'year' => ['min' => 0, 'max' => 2099]
    ];

    // Month names for conversion
    private $month_names = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        // Add full names
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
        'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12
    ];

    // Day names for conversion
    private $day_names = [
        'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6,
        // Add full names
        'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6
    ];


     // Predefined interval aliases
     private static $predefined_intervals = [
        'yearly' => '0 0 1 1 *',           // At 00:00 on January 1st
        'annually' => '0 0 1 1 *',         // Same as yearly
        'monthly' => '0 0 1 * *',          // At 00:00 on day 1 of every month
        'weekly' => '0 0 * * 0',           // At 00:00 on Sunday
        'daily' => '0 0 * * *',            // At 00:00 every day
        'midnight' => '0 0 * * *',         // Same as daily
        'hourly' => '0 * * * *',           // At minute 0 of every hour
        'every_minute' => '* * * * *',     // Every minute
        'every_5_minutes' => '*/5 * * * *', // Every 5 minutes
        'every_10_minutes' => '*/10 * * * *', // Every 10 minutes
        'every_15_minutes' => '*/15 * * * *', // Every 15 minutes
        'every_30_minutes' => '*/30 * * * *', // Every 30 minutes
        'twice_daily' => '0 0,12 * * *',   // At 00:00 and 12:00
        'weekdays' => '0 0 * * 1-5',       // At 00:00 on weekdays
        'weekends' => '0 0 * * 0,6',       // At 00:00 on weekends
    ];

    /**
     * Constructor that optionally accepts a CRON string
     * 
     * @param string|null $cron_string Optional CRON string (e.g. "* * * * *")
     * @throws \InvalidArgumentException If the CRON string is invalid
     */
    public function __construct(?string $cron_string = null)
    {
        if ($cron_string !== null) {
            $this->from_cron_string($cron_string);
        }
    }

    /**
     * Sets values from a CRON string or predefined interval
     * 
     * @param string $cron_string CRON string (e.g. "* * * * *" or "* * * * * *") or predefined interval (e.g. "hourly")
     * @return $this To allow method chaining
     * @throws \InvalidArgumentException If the CRON string is invalid
     */
    public function from_cron_string(string $cron_string): self
{
    // Check if it's a predefined interval
    $cron_string_lower = strtolower(trim($cron_string));
    if (isset(self::$predefined_intervals[$cron_string_lower])) {
        $cron_string = self::$predefined_intervals[$cron_string_lower];
    }
    
    $parts = preg_split('/\s+/', trim($cron_string));
    $count = count($parts);

    if ($count < 5 || $count > 6) {
        throw new \InvalidArgumentException(
            "CRON string must contain 5 or 6 parts, found $count: '$cron_string'"
        );
    }

    $this->minutes = $parts[0];
    $this->hours = $parts[1];
    $this->day_of_month = $parts[2];
    
    // Pre-convert text-based month and day of week values
    $this->month = is_string($parts[3]) ? $this->convert_named_values($parts[3], $this->month_names) : $parts[3];
    $this->day_of_week = is_string($parts[4]) ? $this->convert_named_values($parts[4], $this->day_names) : $parts[4];
    
    // Year field is optional
    if ($count === 6) {
        $this->year = $parts[5];
    } else {
        $this->year = '*';
    }

    // Validate all fields
    $this->validate();

    return $this;
}

     /**
     * Get list of available predefined intervals
     * 
     * @return array Associative array of interval names and their CRON expressions
     */
    public static function get_predefined_intervals(): array
    {
        return self::$predefined_intervals;
    }

     /**
     * Check if a string is a predefined interval
     * 
     * @param string $interval The interval to check
     * @return bool True if it's a predefined interval
     */
    public static function is_predefined_interval(string $interval): bool
    {
        return isset(self::$predefined_intervals[strtolower(trim($interval))]);
    }

    /**
     * Convert a predefined interval to its CRON expression
     * 
     * @param string $interval The predefined interval
     * @return string|null The CRON expression or null if not found
     */
    public static function interval_to_cron(string $interval): ?string
    {
        $interval_lower = strtolower(trim($interval));
        return self::$predefined_intervals[$interval_lower] ?? null;
    }


    /**
     * Generates a CRON string from set values
     * 
     * @param bool $include_year Whether to include the 'year' field in the string
     * @return string CRON string in standard format
     */
    public function to_cron_string(bool $include_year = false): string
    {
        $parts = [
            $this->minutes,
            $this->hours,
            $this->day_of_month,
            $this->month,
            $this->day_of_week
        ];

        if ($include_year) {
            $parts[] = $this->year;
        }

        return implode(' ', $parts);
    }

    /**
     * Sets minutes (0-59)
     * 
     * @param string|int $minutes Value for minutes (numbers, ranges, lists, steps or *)
     * @return $this To allow method chaining
     * @throws \InvalidArgumentException If the value is invalid
     */
    public function set_minutes($minutes): self
    {
        $this->minutes = $this->format_and_validate_field($minutes, 'minutes');
        return $this;
    }

    /**
     * Sets hours (0-23)
     * 
     * @param string|int $hours Value for hours (numbers, ranges, lists, steps or *)
     * @return $this To allow method chaining
     * @throws \InvalidArgumentException If the value is invalid
     */
    public function set_hours($hours): self
    {
        $this->hours = $this->format_and_validate_field($hours, 'hours');
        return $this;
    }

    /**
     * Sets day of month (1-31)
     * 
     * @param string|int $day_of_month Value for day of month (numbers, ranges, lists, steps or *)
     * @return $this To allow method chaining
     * @throws \InvalidArgumentException If the value is invalid
     */
    public function set_day_of_month($day_of_month): self
    {
        $this->day_of_month = $this->format_and_validate_field($day_of_month, 'day_of_month');
        return $this;
    }

    /**
     * Sets month (1-12 or month names)
     * 
     * @param string|int $month Value for month (numbers, names, ranges, lists, steps or *)
     * @return $this To allow method chaining
     * @throws \InvalidArgumentException If the value is invalid
     */
    public function set_month($month): self
    {
        if (is_string($month)) {
            $month = $this->convert_named_values($month, $this->month_names);
        }
        $this->month = $this->format_and_validate_field($month, 'month');
        return $this;
    }

    /**
     * Sets day of week (0-6, where 0=Sunday)
     * 
     * @param string|int $day_of_week Value for day of week (numbers, names, ranges, lists, steps or *)
     * @return $this To allow method chaining
     * @throws \InvalidArgumentException If the value is invalid
     */
    public function set_day_of_week($day_of_week): self
    {
        if (is_string($day_of_week)) {
            $day_of_week = $this->convert_named_values($day_of_week, $this->day_names);
        }
        $this->day_of_week = $this->format_and_validate_field($day_of_week, 'day_of_week');
        return $this;
    }

    /**
     * Sets year (optional)
     * 
     * @param string|int $year Value for year (numbers, ranges, lists, steps or *)
     * @return $this To allow method chaining
     * @throws \InvalidArgumentException If the value is invalid
     */
    public function set_year($year): self
    {
        $this->year = $this->format_and_validate_field($year, 'year');
        return $this;
    }

    /**
     * Checks if a timestamp matches the CRON schedule
     * 
     * @param int|null $timestamp UNIX timestamp to check (null = current timestamp)
     * @return bool True if the timestamp matches the schedule
     */
    public function matches(?int $timestamp = null): bool
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $dt = new \DateTime("@$timestamp");
        
        // Check minutes field
        if (!$this->field_matches($this->minutes, (int) $dt->format('i'), 'minutes')) {
            return false;
        }
        
        // Check hours field
        if (!$this->field_matches($this->hours, (int) $dt->format('G'), 'hours')) {
            return false;
        }
        
        // Check day of month field
        if (!$this->field_matches($this->day_of_month, (int) $dt->format('j'), 'day_of_month')) {
            return false;
        }
        
        // Check month field
        if (!$this->field_matches($this->month, (int) $dt->format('n'), 'month')) {
            return false;
        }
        
        // Check day of week field (0=Sunday)
        if (!$this->field_matches($this->day_of_week, (int) $dt->format('w'), 'day_of_week')) {
            return false;
        }
        
        // Check year field (if not *)
        if ($this->year !== '*' && !$this->field_matches($this->year, (int) $dt->format('Y'), 'year')) {
            return false;
        }
        
        return true;
    }

    /**
     * Calculates the next timestamp that matches the CRON schedule
     * 
     * @param int|null $start_timestamp Starting UNIX timestamp (null = current timestamp)
     * @param int $max_iterations Maximum number of iterations to avoid infinite loops
     * @return int The next timestamp that matches the schedule
     * @throws \RuntimeException If no valid timestamp can be found
     */
    public function get_next_run_time(?int $start_timestamp = null, int $max_iterations = 10000): int
    {
        if ($start_timestamp === null) {
            $start_timestamp = time();
        }

        // Start from the next minute
        $dt = new \DateTime("@$start_timestamp");
        $dt->modify('+1 minute');
        $dt->setTime((int)$dt->format('H'), (int)$dt->format('i'), 0); // Reset seconds
        
        $iteration = 0;
        
        while ($iteration < $max_iterations) {
            $iteration++;
            
            // Extract current datetime components
            $year = (int) $dt->format('Y');
            $month = (int) $dt->format('n');
            $day = (int) $dt->format('j');
            $hour = (int) $dt->format('G');
            $minute = (int) $dt->format('i');
            $dayOfWeek = (int) $dt->format('w');
            
            // Check year first (if specified)
            if ($this->year !== '*' && !$this->field_matches($this->year, $year, 'year')) {
                // Jump to next valid year
                $nextYear = $this->get_next_valid_value($this->year, $year, 'year');
                if ($nextYear === false || $nextYear <= $year) {
                    throw new \RuntimeException("No valid year found after $year");
                }
                $dt->setDate($nextYear, 1, 1);
                $dt->setTime(0, 0, 0);
                continue;
            }
            
            // Check month
            if (!$this->field_matches($this->month, $month, 'month')) {
                // Jump to next valid month
                $nextMonth = $this->get_next_valid_value($this->month, $month, 'month');
                if ($nextMonth === false) {
                    // No valid month in this year, go to next year
                    $dt->modify('+1 year');
                    $dt->setDate((int)$dt->format('Y'), 1, 1);
                    $dt->setTime(0, 0, 0);
                    continue;
                }
                $dt->setDate($year, $nextMonth, 1);
                $dt->setTime(0, 0, 0);
                continue;
            }
            
            // Check day of month and day of week
            $dayMatches = $this->field_matches($this->day_of_month, $day, 'day_of_month');
            $dowMatches = $this->field_matches($this->day_of_week, $dayOfWeek, 'day_of_week');
            
            // Both day fields must match (if both are specified)
            if (!$dayMatches || !$dowMatches) {
                // Find next valid day
                $foundValidDay = false;
                $tempDt = clone $dt;
                $daysInMonth = (int) $tempDt->format('t');
                
                for ($d = $day + 1; $d <= $daysInMonth; $d++) {
                    $tempDt->setDate($year, $month, $d);
                    $tempDayOfWeek = (int) $tempDt->format('w');
                    
                    if ($this->field_matches($this->day_of_month, $d, 'day_of_month') &&
                        $this->field_matches($this->day_of_week, $tempDayOfWeek, 'day_of_week')) {
                        $dt->setDate($year, $month, $d);
                        $dt->setTime(0, 0, 0);
                        $foundValidDay = true;
                        break;
                    }
                }
                
                if (!$foundValidDay) {
                    // No valid day in this month, go to next month
                    $dt->modify('+1 month');
                    $dt->setDate((int)$dt->format('Y'), (int)$dt->format('n'), 1);
                    $dt->setTime(0, 0, 0);
                    continue;
                }
            }
            
            // Check hour
            if (!$this->field_matches($this->hours, $hour, 'hours')) {
                $nextHour = $this->get_next_valid_value($this->hours, $hour, 'hours');
                if ($nextHour === false) {
                    // No valid hour today, go to next day
                    $dt->modify('+1 day');
                    $dt->setTime(0, 0, 0);
                    continue;
                }
                $dt->setTime($nextHour, 0, 0);
                continue;
            }
            
            // Check minute
            if (!$this->field_matches($this->minutes, $minute, 'minutes')) {
                $nextMinute = $this->get_next_valid_value($this->minutes, $minute, 'minutes');
                if ($nextMinute === false) {
                    // No valid minute in this hour, go to next hour
                    $dt->modify('+1 hour');
                    $dt->setTime((int)$dt->format('H'), 0, 0);
                    continue;
                }
                $dt->setTime($hour, $nextMinute, 0);
                continue;
            }
            
            // All fields match!
            return $dt->getTimestamp();
        }
        
        throw new \RuntimeException("Could not find a valid timestamp after $max_iterations iterations");
    }

    /**
     * Gets the next valid value for a CRON field
     * 
     * @param string $field CRON field expression
     * @param int $current Current value
     * @param string $field_name Field name for limits
     * @return int|false Next valid value or false if none found in current cycle
     */
    private function get_next_valid_value(string $field, int $current, string $field_name)
    {
        $min = $this->limits[$field_name]['min'];
        $max = $this->limits[$field_name]['max'];
        
        // Generate all valid values for this field
        $validValues = $this->get_valid_values($field, $field_name);
        
        // Find the next valid value after current
        foreach ($validValues as $value) {
            if ($value > $current) {
                return $value;
            }
        }
        
        return false;
    }

    /**
     * Gets all valid values for a CRON field
     * 
     * @param string $field CRON field expression
     * @param string $field_name Field name for limits
     * @return array Array of valid values
     */
    private function get_valid_values(string $field, string $field_name): array
    {
        $min = $this->limits[$field_name]['min'];
        $max = $this->limits[$field_name]['max'];
        $values = [];
        
        // Handle wildcard
        if ($field === '*') {
            return range($min, $max);
        }
        
        // Handle comma-separated values
        if (strpos($field, ',') !== false) {
            $parts = explode(',', $field);
            foreach ($parts as $part) {
                $values = array_merge($values, $this->get_valid_values($part, $field_name));
            }
            return array_unique($values);
        }
        
        // Handle ranges
        if (preg_match('/^(\d+)-(\d+)$/', $field, $matches)) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];
            return range($start, $end);
        }
        
        // Handle steps
        if (strpos($field, '/') !== false) {
            list($range, $step) = explode('/', $field);
            $step = (int) $step;
            
            if ($range === '*') {
                for ($i = $min; $i <= $max; $i += $step) {
                    $values[] = $i;
                }
            } elseif ($range === '0') {
                for ($i = 0; $i <= $max; $i += $step) {
                    $values[] = $i;
                }
            } elseif (preg_match('/^(\d+)-(\d+)$/', $range, $matches)) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];
                for ($i = $start; $i <= $end; $i += $step) {
                    $values[] = $i;
                }
            }
            return $values;
        }
        
        // Handle single value
        if (is_numeric($field)) {
            return [(int) $field];
        }
        
        return [];
    }



    /**
     * Validates all CRON fields
     * 
     * @throws \InvalidArgumentException If any field is invalid
     */
    private function validate(): void
    {
        $this->validate_field($this->minutes, 'minutes');
        $this->validate_field($this->hours, 'hours');
        $this->validate_field($this->day_of_month, 'day_of_month');
        $this->validate_field($this->month, 'month');
        $this->validate_field($this->day_of_week, 'day_of_week');
        $this->validate_field($this->year, 'year');
    }

    /**
     * Formats and validates a single CRON field
     * 
     * @param string|int $value Value to format and validate
     * @param string $field Field name ('minutes', 'hours', etc.)
     * @return string Formatted value
     * @throws \InvalidArgumentException If the value is invalid
     */
    private function format_and_validate_field($value, string $field): string
    {
        // Convert numbers to strings
        if (is_numeric($value)) {
            $value = (string) $value;
        }
        
        $this->validate_field($value, $field);
        return $value;
    }

    /**
     * Validates a single CRON field with better error messages
     * 
     * @param string $value Value to validate
     * @param string $field Field name ('minutes', 'hours', etc.)
     * @throws \InvalidArgumentException If the value is invalid
     */
    private function validate_field(string $value, string $field): void
    {
        // If value is *, it's always valid
        if ($value === '*') {
            return;
        }
        
        // Check if field exists in limits
        if (!isset($this->limits[$field])) {
            throw new \InvalidArgumentException("Unknown field: '$field'");
        }
        
        $min = $this->limits[$field]['min'];
        $max = $this->limits[$field]['max'];
        
        // Handle multiple values separated by comma
        if (strpos($value, ',') !== false) {
            $parts = explode(',', $value);
            foreach ($parts as $part) {
                $this->validate_field($part, $field);
            }
            return;
        }
        
        // Handle ranges (e.g. 1-5)
        if (preg_match('/^(\d+)-(\d+)$/', $value, $matches)) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];
            
            if ($start < $min || $start > $max) {
                throw new \InvalidArgumentException(
                    "Invalid start value for field '$field': $start (min: $min, max: $max)"
                );
            }
            
            if ($end < $min || $end > $max) {
                throw new \InvalidArgumentException(
                    "Invalid end value for field '$field': $end (min: $min, max: $max)"
                );
            }
            
            if ($start > $end) {
                throw new \InvalidArgumentException(
                    "Start value ($start) cannot be greater than end value ($end) for field '$field'"
                );
            }
            
            return;
        }
        
        // Handle steps (e.g. */5 or 1-10/2)
        if (strpos($value, '/') !== false) {
            list($range, $step) = explode('/', $value, 2);
            
            if (!is_numeric($step) || (int) $step < 1) {
                throw new \InvalidArgumentException(
                    "Invalid step for field '$field': $step"
                );
            }
            
            // Check range validity
            if ($range === '*') {
                return;
            } else {
                $this->validate_field($range, $field);
            }
            
            return;
        }
        
        // Handle single values
        if (is_numeric($value)) {
            $num_value = (int) $value;
            if ($num_value < $min || $num_value > $max) {
                // Special error message for common mistakes
                $hint = '';
                if ($field === 'day_of_week' && $num_value >= 1 && $num_value <= 31) {
                    $hint = " Did you mean to use this value for day_of_month instead?";
                } elseif ($field === 'hours' && $num_value >= 0 && $num_value <= 59) {
                    $hint = " Did you mean to use this value for minutes instead?";
                }
                
                throw new \InvalidArgumentException(
                    "Invalid value for field '$field': $value (min: $min, max: $max)." . $hint
                );
            }
            return;
        }
        
        throw new \InvalidArgumentException(
            "Invalid format for field '$field': $value"
        );
    }

    /**
     * Get a user-friendly description of what each field represents
     * 
     * @return array
     */
    public static function get_field_descriptions(): array
    {
        return [
            'minutes' => 'Minutes (0-59)',
            'hours' => 'Hours (0-23)',
            'day_of_month' => 'Day of month (1-31)',
            'month' => 'Month (1-12 or names)',
            'day_of_week' => 'Day of week (0-6, where 0=Sunday)',
            'year' => 'Year (optional)'
        ];
    }

    /**
     * Validate a CRON string without creating an instance
     * Returns true if valid, or an error message if invalid
     * 
     * @param string $cron_string
     * @return bool|string True if valid, error message if invalid
     */
    public static function validate_cron_string(string $cron_string)
    {
        try {
            new self($cron_string);
            return true;
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Converts text names (e.g. "jan", "feb", "mon", "tue") to their numeric values
     * 
     * @param string $value Value to convert
     * @param array $dictionary Dictionary of names and corresponding values
     * @return string Converted value
     */
    private function convert_named_values(string $value, array $dictionary): string
    {
        // Handle multiple values separated by comma
        if (strpos($value, ',') !== false) {
            $parts = explode(',', $value);
            foreach ($parts as &$part) {
                $part = $this->convert_named_values($part, $dictionary);
            }
            return implode(',', $parts);
        }

        // Handle ranges (e.g. jan-mar)
        if (preg_match('/^([a-zA-Z]+)-([a-zA-Z]+)$/', $value, $matches)) {
            $start = strtolower($matches[1]);
            $end = strtolower($matches[2]);
            
            if (isset($dictionary[$start]) && isset($dictionary[$end])) {
                return $dictionary[$start] . '-' . $dictionary[$end];
            }
        }
        
        // Handle steps (e.g. jan-jun/2)
        if (strpos($value, '/') !== false) {
            list($range, $step) = explode('/', $value, 2);
            $converted_range = $this->convert_named_values($range, $dictionary);
            return $converted_range . '/' . $step;
        }
        
        // Convert single names
        $value_lower = strtolower($value);
        if (isset($dictionary[$value_lower])) {
            return (string) $dictionary[$value_lower];
        }
        
        // If not a known name, return the original value
        return $value;
    }

    /**
     * Checks if a value matches a CRON field
     * 
     * @param string $field CRON field (e.g. "* /5", "1,3,5", "1-5")
     * @param int $value Value to check
     * @param string $field_name Field name ('minutes', 'hours', etc.) for limits
     * @return bool True if the value matches the field
     */
    private function field_matches(string $field, int $value, string $field_name): bool
    {
        // If the field is *, it matches any value
        if ($field === '*') {
            return true;
        }
        
        // Handle multiple values separated by comma
        if (strpos($field, ',') !== false) {
            $parts = explode(',', $field);
            foreach ($parts as $part) {
                if ($this->field_matches($part, $value, $field_name)) {
                    return true;
                }
            }
            return false;
        }
        
        // Handle ranges (e.g. 1-5)
        if (preg_match('/^(\d+)-(\d+)$/', $field, $matches)) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];
            return $value >= $start && $value <= $end;
        }
        
        // Handle steps (e.g. */5 or 1-10/2 or 0/5)
        if (strpos($field, '/') !== false) {
            list($range, $step) = explode('/', $field);
            $step = (int) $step;
            
            // If the range is '*', check if the value is divisible by the step
            if ($range === '*') {
                $min = $this->limits[$field_name]['min'];
                return ($value - $min) % $step === 0;
            } 
            // Handle the 0/5 case (Quartz style) 
            else if ($range === '0') {
                // If the value is divisible by the step, it's a match
                return $value % $step === 0;
            }
            // Handle ranges with steps (e.g. 1-10/2)
            else if (preg_match('/^(\d+)-(\d+)$/', $range, $matches)) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];
                return $value >= $start && $value <= $end && ($value - $start) % $step === 0;
            }
        }
        
        // Handle single values
        if (is_numeric($field)) {
            return $value === (int) $field;
        }
        
        return false;
    }
}