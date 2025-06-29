<?php
namespace Modules\Jobs;

/**
 * Helper class for generating human-readable descriptions of CRON expressions
 */
class CronDescriptionHelper
{
    // Month names for conversion
    private static $month_names = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
    ];

    // Day names for conversion
    private static $day_names = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday'
    ];

    /**
     * Returns a human-readable description of a CRON expression
     * 
     * @param string $cron_string CRON expression (e.g. "30 9 * 1,4,7,10 1-5 *")
     * @return string Description of when the job will run
     * @throws \InvalidArgumentException If the CRON string is invalid
     */
    public static function get_description(string $cron_string): string
    {
        $parts = preg_split('/\s+/', trim($cron_string));
        $count = count($parts);

        if ($count < 5 || $count > 6) {
            throw new \InvalidArgumentException(
                "CRON string must contain 5 or 6 parts, found $count: '$cron_string'"
            );
        }

        $minutes = $parts[0];
        $hours = $parts[1];
        $day_of_month = $parts[2];
        $month = $parts[3];
        $day_of_week = $parts[4];
        $year = $count === 6 ? $parts[5] : '*';

        return self::build_description($minutes, $hours, $day_of_month, $month, $day_of_week, $year);
    }

    /**
     * Returns a human-readable description from CronDateManager instance
     * 
     * @param CronDateManager $cron_manager
     * @return string Description of when the job will run
     */
    public static function get_description_from_manager(CronDateManager $cron_manager): string
    {
        // Get the CRON string from the manager and process it
        $cron_string = $cron_manager->to_cron_string(true);
        return self::get_description($cron_string);
    }

    /**
     * Build the complete description
     * 
     * @param string $minutes
     * @param string $hours
     * @param string $day_of_month
     * @param string $month
     * @param string $day_of_week
     * @param string $year
     * @return string
     */
    private static function build_description(string $minutes, string $hours, string $day_of_month, 
                                              string $month, string $day_of_week, string $year): string
    {
        $parts = [];
        
        // Special case: every minute
        if ($minutes === '*' && $hours === '*' && $day_of_month === '*' && 
            $month === '*' && $day_of_week === '*') {
            return _rt('every minute');
        }
        
        // Build time part
        $timePart = self::get_time_description($minutes, $hours);
        if ($timePart) {
            $parts[] = $timePart;
        }
        
        // Build day part
        $dayPart = self::get_day_description($day_of_month, $day_of_week);
        if ($dayPart) {
            $parts[] = $dayPart;
        }
        
        // Build month part
        $monthPart = self::get_month_description($month);
        if ($monthPart) {
            $parts[] = $monthPart;
        }
        
        // Build year part
        if ($year !== '*') {
            $yearPart = self::get_year_description($year);
            if ($yearPart) {
                $parts[] = $yearPart;
            }
        }
        
        return ucfirst(implode(' ', $parts));
    }

    /**
     * Get description for time (hours and minutes)
     * 
     * @param string $minutes
     * @param string $hours
     * @return string
     */
    private static function get_time_description(string $minutes, string $hours): string
    {
        $parts = [];
        
        // Minutes
        if ($minutes === '*') {
            $parts[] = _rt('every minute');
        } elseif (strpos($minutes, '*/') === 0) {
            $interval = substr($minutes, 2);
            $parts[] = sprintf(_rt('every %s minutes'), $interval);
        } elseif (strpos($minutes, ',') !== false) {
            $parts[] = sprintf(_rt('at minutes %s'), $minutes);
        } elseif (strpos($minutes, '-') !== false) {
            $parts[] = sprintf(_rt('at minutes %s'), $minutes);
        } else {
            // Specific minute - will be combined with hour
        }
        
        // Hours
        if ($hours === '*' && $minutes !== '*') {
            if (empty($parts)) {
                $parts[] = sprintf(_rt('at %s minutes past every hour'), $minutes);
            }
        } elseif ($hours !== '*') {
            if (strpos($hours, '*/') === 0) {
                $interval = substr($hours, 2);
                $hourPart = sprintf(_rt('every %s hours'), $interval);
                if ($minutes !== '*' && !strpos($minutes, '*/') && !strpos($minutes, ',')) {
                    $hourPart .= sprintf(_rt(' at %s minutes past the hour'), $minutes);
                }
                $parts = [$hourPart];
            } elseif (strpos($hours, ',') !== false || strpos($hours, '-') !== false) {
                // Multiple hours or range
                $hourPart = sprintf(_rt('at %s'), self::format_hours_for_display($hours));
                if ($minutes !== '*' && !strpos($minutes, '*/') && !strpos($minutes, ',')) {
                    $hourPart = sprintf(_rt('at %s:%02d'), $hours, (int)$minutes);
                }
                $parts = [$hourPart];
            } else {
                // Specific hour
                if ($minutes === '*') {
                    $parts = [sprintf(_rt('every minute of %s:00'), $hours)];
                } elseif (!strpos($minutes, '*/') && !strpos($minutes, ',') && !strpos($minutes, '-')) {
                    $parts = [sprintf(_rt('at %s:%02d'), $hours, (int)$minutes)];
                } else {
                    $parts = [sprintf(_rt('at %s:00'), $hours)];
                }
            }
        }
        
        return implode(' ', $parts);
    }

    /**
     * Get description for days (day of month and day of week)
     * 
     * @param string $day_of_month
     * @param string $day_of_week
     * @return string
     */
    private static function get_day_description(string $day_of_month, string $day_of_week): string
    {
        $parts = [];
        
        $hasDayOfMonth = $day_of_month !== '*';
        $hasDayOfWeek = $day_of_week !== '*';
        
        if ($hasDayOfMonth && $hasDayOfWeek) {
            // Both specified
            $parts[] = sprintf(_rt('on %s and %s'), 
                self::format_day_of_month_for_display($day_of_month),
                self::format_day_of_week_for_display($day_of_week)
            );
        } elseif ($hasDayOfMonth) {
            $parts[] = sprintf(_rt('on %s'), self::format_day_of_month_for_display($day_of_month));
        } elseif ($hasDayOfWeek) {
            $parts[] = sprintf(_rt('on %s'), self::format_day_of_week_for_display($day_of_week));
        }
        
        return implode(' ', $parts);
    }

    /**
     * Get description for months
     * 
     * @param string $month
     * @return string
     */
    private static function get_month_description(string $month): string
    {
        if ($month === '*') {
            return '';
        }
        
        return sprintf(_rt('in %s'), self::format_month_for_display($month));
    }

    /**
     * Get description for year
     * 
     * @param string $year
     * @return string
     */
    private static function get_year_description(string $year): string
    {
        if ($year === '*') {
            return '';
        }
        
        if (strpos($year, ',') !== false) {
            return sprintf(_rt('in years %s'), $year);
        } elseif (strpos($year, '-') !== false) {
            return sprintf(_rt('from year %s'), $year);
        } elseif (strpos($year, '*/') === 0) {
            $interval = substr($year, 2);
            return sprintf(_rt('every %s years'), $interval);
        } else {
            return sprintf(_rt('in %s'), $year);
        }
    }

    /**
     * Format day of month for display
     * 
     * @param string $day_of_month
     * @return string
     */
    private static function format_day_of_month_for_display(string $day_of_month): string
    {
        if (strpos($day_of_month, '*/') === 0) {
            $interval = substr($day_of_month, 2);
            return sprintf(_rt('every %s days'), $interval);
        } elseif (strpos($day_of_month, ',') !== false) {
            $days = explode(',', $day_of_month);
            $formatted = array_map(function($d) { return self::add_ordinal_suffix($d); }, $days);
            return sprintf(_rt('the %s'), implode(', ', $formatted));
        } elseif (strpos($day_of_month, '-') !== false) {
            list($start, $end) = explode('-', $day_of_month);
            return sprintf(_rt('the %s through %s'), 
                self::add_ordinal_suffix($start), 
                self::add_ordinal_suffix($end)
            );
        } else {
            return sprintf(_rt('the %s'), self::add_ordinal_suffix($day_of_month));
        }
    }

    /**
     * Format day of week for display
     * 
     * @param string $day_of_week
     * @return string
     */
    private static function format_day_of_week_for_display(string $day_of_week): string
    {
        if (strpos($day_of_week, ',') !== false) {
            $days = explode(',', $day_of_week);
            $formatted = array_map(function($d) { 
                return _rt(self::$day_names[(int)$d] ?? $d); 
            }, $days);
            return implode(', ', $formatted);
        } elseif (strpos($day_of_week, '-') !== false) {
            list($start, $end) = explode('-', $day_of_week);
            
            // Special case for Monday-Friday
            if ($start == '1' && $end == '5') {
                return _rt('weekdays');
            }
            // Special case for Saturday-Sunday
            if (($start == '6' && $end == '0') || ($start == '0' && $end == '6')) {
                return _rt('weekends');
            }
            
            return sprintf(_rt('%s through %s'), 
                _rt(self::$day_names[(int)$start] ?? $start),
                _rt(self::$day_names[(int)$end] ?? $end)
            );
        } else {
            return _rt(self::$day_names[(int)$day_of_week] ?? $day_of_week);
        }
    }

    /**
     * Format month for display
     * 
     * @param string $month
     * @return string
     */
    private static function format_month_for_display(string $month): string
    {
        if (strpos($month, ',') !== false) {
            $months = explode(',', $month);
            $formatted = array_map(function($m) { 
                return _rt(self::$month_names[(int)$m] ?? $m); 
            }, $months);
            return implode(', ', $formatted);
        } elseif (strpos($month, '-') !== false) {
            list($start, $end) = explode('-', $month);
            return sprintf(_rt('%s through %s'), 
                _rt(self::$month_names[(int)$start] ?? $start),
                _rt(self::$month_names[(int)$end] ?? $end)
            );
        } elseif (strpos($month, '*/') === 0) {
            $interval = substr($month, 2);
            return sprintf(_rt('every %s months'), $interval);
        } else {
            return _rt(self::$month_names[(int)$month] ?? $month);
        }
    }

    /**
     * Format hours for display
     * 
     * @param string $hours
     * @return string
     */
    private static function format_hours_for_display(string $hours): string
    {
        if (strpos($hours, ',') !== false) {
            $hours_array = explode(',', $hours);
            $formatted = array_map(function($h) { 
                return sprintf('%02d:00', (int)$h); 
            }, $hours_array);
            return implode(', ', $formatted);
        } elseif (strpos($hours, '-') !== false) {
            list($start, $end) = explode('-', $hours);
            return sprintf('%02d:00-%02d:00', (int)$start, (int)$end);
        } else {
            return sprintf('%02d:00', (int)$hours);
        }
    }

    /**
     * Add ordinal suffix to a number (1st, 2nd, 3rd, etc.)
     * 
     * @param int|string $number
     * @return string
     */
    private static function add_ordinal_suffix($number): string
    {
        $number = (int)$number;
        $suffix = 'th';
        
        if (!in_array($number % 100, [11, 12, 13])) {
            switch ($number % 10) {
                case 1:
                    $suffix = 'st';
                    break;
                case 2:
                    $suffix = 'nd';
                    break;
                case 3:
                    $suffix = 'rd';
                    break;
            }
        }
        
        return $number . $suffix;
    }
}


/*

// Uso diretto con una stringa CRON
$description = CronDescriptionHelper::get_description('30 9 * 1,4,7,10 1-5 *');
echo $description; 
// Output: "At 9:30 on weekdays in January, April, July, October"

// Uso con un'istanza di CronDateManager
$cron = new CronDateManager('30 9 * 1,4,7,10 1-5 *');
$description = CronDescriptionHelper::get_description_from_manager($cron);
echo $description;
// Output: "At 9:30 on weekdays in January, April, July, October"

// Altri esempi
echo CronDescriptionHelper::get_description('*\/5 * * * * *');
// Output: "Every 5 minutes"

echo CronDescriptionHelper::get_description('0 12 * * * *');
// Output: "At 12:00"

echo CronDescriptionHelper::get_description('0 0 1 * * *');
// Output: "At 0:00 on the 1st"

*/