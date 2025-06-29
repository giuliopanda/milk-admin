<?php
namespace MilkCore;
use DateTime;
use DateInterval;
use DatePeriod;
use Exception;

/**
 * Date functions provider for date and time operations
 * Handles dates in MySQL format (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
 * 
 * @package     MilkCore
 * @subpackage  MathParser
 * @ignore
 */

class DateFunctions extends FunctionProvider {
    private $data_row_manager;
    
    /**
     * Constructor
     * 
     * @param DataRowManager $manager Optional DataRowManager instance
     * @return void
     */
    public function __construct($manager = null) {
        $this->data_row_manager = $manager;
    }
    
    /**
     * Returns all implemented functions
     * 
     * @return array Associative array of functions (name => callback)
     */
    public function get_functions() {
        return [
            // Date creation and formatting
            'DATE' => [$this, 'func_date'],
            'DATE_CREATE' => [$this, 'func_date_create'],
            'NOW' => [$this, 'func_now'],
            'TODAY' => [$this, 'func_today'],
            'FORMAT_DATE' => [$this, 'func_format_date'],
            'DATE_PART' => [$this, 'func_date_part'],

            'MIN_DATE' => [$this, 'func_min_date'],
            'MAX_DATE' => [$this, 'func_max_date'],
            
            // Date validation
            'IS_DATE' => [$this, 'func_is_date'],
            'IS_VALID_DATE' => [$this, 'func_is_valid_date'],
            'VALID_DATE' => [$this, 'func_is_valid_date'], // Alias
            
            // Date manipulation
            'DATE_ADD' => [$this, 'func_date_add'],
            'DATE_SUB' => [$this, 'func_date_sub'],
            'ADD_DAYS' => [$this, 'func_add_days'],
            'ADD_MONTHS' => [$this, 'func_add_months'],
            'ADD_YEARS' => [$this, 'func_add_years'],
            
            // Date comparison
            'DATE_DIFF' => [$this, 'func_date_diff'],
            'DAYS_DIFF' => [$this, 'func_days_diff'],
            'MONTHS_DIFF' => [$this, 'func_months_diff'],
            'YEARS_DIFF' => [$this, 'func_years_diff'],
            'HOURS_DIFF' => [$this, 'func_hours_diff'],
            'MINUTES_DIFF' => [$this, 'func_minutes_diff'],
            'SECONDS_DIFF' => [$this, 'func_seconds_diff'],
            
            // Date analysis
            'YEAR' => [$this, 'func_year'],
            'MONTH' => [$this, 'func_month'],
            'DAY' => [$this, 'func_day'],
            'HOUR' => [$this, 'func_hour'],
            'MINUTE' => [$this, 'func_minute'],
            'SECOND' => [$this, 'func_second'],
            'WEEKDAY' => [$this, 'func_weekday'],
            'WEEK_NUMBER' => [$this, 'func_week_number'],
            'DAY_OF_YEAR' => [$this, 'func_day_of_year'],
            'QUARTER' => [$this, 'func_quarter'],
            
            // Special operations
            'LAST_DAY' => [$this, 'func_last_day'],
            'FIRST_DAY' => [$this, 'func_first_day'],
            'DATE_TRUNCATE' => [$this, 'func_date_truncate'],
            'BUSINESS_DAYS' => [$this, 'func_business_days'],
            'IS_BUSINESS_DAY' => [$this, 'func_is_business_day'],
            'NEXT_BUSINESS_DAY' => [$this, 'func_next_business_day'],
            'PREV_BUSINESS_DAY' => [$this, 'func_prev_business_day'],
            'AGE' => [$this, 'func_age'],
        ];
    }
    
    /**
     * Converts various date representations to DateTime object
     * 
     * @param mixed $date Date as MySQL formatted string, timestamp, or DateTime object
     * @return DateTime|null DateTime object or null if invalid
     */
    private function parse_date($date) {
        if ($date === null) {
            return null;
        }
        
        if ($date instanceof DateTime) {
            return $date;
        }
        
        if (is_numeric($date)) {
            // Unix timestamp
            $dt = new DateTime();
            $dt->setTimestamp((int)$date);
            return $dt;
        }
        
        try {
            // Try to convert the date string to a DateTime object
            $dt = new DateTime($date);
            return $dt;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Checks if a string represents a valid date
     * 
     * @param mixed $date The date to validate
     * @return bool True if it's a valid date
     */
    public function func_is_date($date) {
        return $this->parse_date($date) !== null;
    }
    
    /**
     * Checks if a string represents a valid date in MySQL format
     * 
     * @param string $date_str The date to validate
     * @param string $format Expected format (default: Y-m-d)
     * @return bool True if it's a valid date in the specified format
     */
    public function func_is_valid_date($date_str, $format = 'Y-m-d') {
        if (!is_string($date_str)) {
            return false;
        }
        
        // Verifica formati comuni
        if ($format === 'Y-m-d') {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str) && $this->func_is_date($date_str);
        } elseif ($format === 'Y-m-d H:i:s') {
            return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date_str) && $this->func_is_date($date_str);
        } else {
            // Verifica con DateTime::createFromFormat
            $dt = DateTime::createFromFormat($format, $date_str);
            return $dt !== false && !array_sum($dt->getLastErrors());
        }
    }
    
    /**
     * Creates a date from year, month, and day
     * 
     * @param int $year Year (4 digits)
     * @param int $month Month (1-12)
     * @param int $day Day (1-31)
     * @param int $hour Hour (0-23, optional)
     * @param int $minute Minute (0-59, optional)
     * @param int $second Second (0-59, optional)
     * @return string Date in MySQL format
     * @throws Exception if the date is invalid
     */
    public function func_date($year, $month, $day, $hour = 0, $minute = 0, $second = 0) {
        try {
            $date = new DateTime();
            $date->setDate((int)$year, (int)$month, (int)$day);
            $date->setTime((int)$hour, (int)$minute, (int)$second);
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw new Exception("Data non valida: $year-$month-$day $hour:$minute:$second");
        }
    }
    
    /**
     * Creates a date from a string
     * 
     * @param string $date_str Date string (various formats supported)
     * @return string Date in MySQL format or null if invalid
     */
    public function func_date_create($date_str) {
        $date = $this->parse_date($date_str);
        if ($date === null) {
            return null;
        }
        return $date->format('Y-m-d H:i:s');
    }
    
    /**
     * Returns the current date and time
     * 
     * @return string Current date and time in MySQL datetime format
     */
    public function func_now() {
        return (new DateTime())->format('Y-m-d H:i:s');
    }
    
    /**
     * Returns the current date (date only, without time)
     * 
     * @return string Current date in MySQL date format
     */
    public function func_today() {
        return (new DateTime())->format('Y-m-d');
    }
    
    /**
     * Formats a date according to a specified pattern
     * 
     * @param mixed $date Date to format
     * @param string $format Desired format (date() syntax)
     * @return string Formatted date
     */
    public function func_format_date($date, $format = 'Y-m-d') {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        return $dt->format($format);
    }
    
    /**
     * Extracts a specific part from a date
     * 
     * @param mixed $date Date to analyze
     * @param string $part Part to extract (year|month|day|hour|minute|second|weekday)
     * @return int|null Value of the requested part
     */
    public function func_date_part($date, $part) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        $part = strtolower($part);
        switch ($part) {
            case 'year':
                return (int)$dt->format('Y');
            case 'month':
                return (int)$dt->format('m');
            case 'day':
                return (int)$dt->format('d');
            case 'hour':
                return (int)$dt->format('H');
            case 'minute':
                return (int)$dt->format('i');
            case 'second':
                return (int)$dt->format('s');
            case 'weekday':
                return (int)$dt->format('N'); // 1 (lunedì) a 7 (domenica)
            default:
                throw new Exception("Parte di data non riconosciuta: $part");
        }
    }
    
    /**
     * Adds an interval to a date
     * 
     * @param mixed $date Starting date
     * @param string $interval Interval string (e.g. '1 day', '2 months', etc.)
     * @return string Resulting date in MySQL format
     */
    public function func_date_add($date, $interval) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        // Converti l'intervallo in un formato riconosciuto da DateInterval
        // Ad esempio, '1 day' diventa 'P1D', '2 months' diventa 'P2M'
        if (preg_match('/^(\d+)\s+([a-z]+)$/i', $interval, $matches)) {
            $amount = (int)$matches[1];
            $unit = strtolower(rtrim($matches[2], 's')); // Rimuovi eventuale 's' plurale
            
            switch ($unit) {
                case 'year':
                    $interval_str = "P{$amount}Y";
                    break;
                case 'month':
                    $interval_str = "P{$amount}M";
                    break;
                case 'week':
                    $amount *= 7;
                    $interval_str = "P{$amount}D";
                    break;
                case 'day':
                    $interval_str = "P{$amount}D";
                    break;
                case 'hour':
                    $interval_str = "PT{$amount}H";
                    break;
                case 'minute':
                    $interval_str = "PT{$amount}M";
                    break;
                case 'second':
                    $interval_str = "PT{$amount}S";
                    break;
                default:
                    throw new Exception("Unità di tempo non riconosciuta: $unit");
            }
            
            $dt->add(new DateInterval($interval_str));
            return $dt->format('Y-m-d H:i:s');
        } else {
            throw new Exception("Formato intervallo non valido: $interval. Usare '1 day', '2 months', ecc.");
        }
    }
    
    /**
     * Subtracts an interval from a date
     * 
     * @param mixed $date Starting date
     * @param string $interval Interval string (e.g. '1 day', '2 months', etc.)
     * @return string Resulting date in MySQL format
     */
    public function func_date_sub($date, $interval) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        // Converti l'intervallo in un formato riconosciuto da DateInterval
        if (preg_match('/^(\d+)\s+([a-z]+)$/i', $interval, $matches)) {
            $amount = (int)$matches[1];
            $unit = strtolower(rtrim($matches[2], 's')); // Rimuovi eventuale 's' plurale
            
            switch ($unit) {
                case 'year':
                    $interval_str = "P{$amount}Y";
                    break;
                case 'month':
                    $interval_str = "P{$amount}M";
                    break;
                case 'week':
                    $amount *= 7;
                    $interval_str = "P{$amount}D";
                    break;
                case 'day':
                    $interval_str = "P{$amount}D";
                    break;
                case 'hour':
                    $interval_str = "PT{$amount}H";
                    break;
                case 'minute':
                    $interval_str = "PT{$amount}M";
                    break;
                case 'second':
                    $interval_str = "PT{$amount}S";
                    break;
                default:
                    throw new Exception("Unità di tempo non riconosciuta: $unit");
            }
            
            $dt->sub(new DateInterval($interval_str));
            return $dt->format('Y-m-d H:i:s');
        } else {
            throw new Exception("Formato intervallo non valido: $interval. Usare '1 day', '2 months', ecc.");
        }
    }
    
    /**
     * Adds days to a date
     * 
     * @param mixed $date Starting date
     * @param int $days Number of days to add (can be negative)
     * @return string Resulting date in MySQL format
     */
    public function func_add_days($date, $days) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        $days = (int)$days;
        if ($days >= 0) {
            $dt->add(new DateInterval("P{$days}D"));
        } else {
            $dt->sub(new DateInterval("P" . abs($days) . "D"));
        }
        
        return $dt->format('Y-m-d H:i:s');
    }
    
    /**
     * Adds months to a date
     * 
     * @param mixed $date Starting date
     * @param int $months Number of months to add (can be negative)
     * @return string Resulting date in MySQL format
     */
    public function func_add_months($date, $months) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        $months = (int)$months;
        if ($months >= 0) {
            $dt->add(new DateInterval("P{$months}M"));
        } else {
            $dt->sub(new DateInterval("P" . abs($months) . "M"));
        }
        
        return $dt->format('Y-m-d H:i:s');
    }
    
    /**
     * Adds years to a date
     * 
     * @param mixed $date Starting date
     * @param int $years Number of years to add (can be negative)
     * @return string Resulting date in MySQL format
     */
    public function func_add_years($date, $years) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        $years = (int)$years;
        if ($years >= 0) {
            $dt->add(new DateInterval("P{$years}Y"));
        } else {
            $dt->sub(new DateInterval("P" . abs($years) . "Y"));
        }
        
        return $dt->format('Y-m-d H:i:s');
    }
    
    /**
     * Calculates the complete difference between two dates
     * 
     * @param mixed $date1 First date
     * @param mixed $date2 Second date
     * @return array Difference in various intervals (years, months, days, hours, minutes, seconds)
     */
    public function func_date_diff($date1, $date2) {
        $dt1 = $this->parse_date($date1);
        $dt2 = $this->parse_date($date2);
        
        if ($dt1 === null || $dt2 === null) {
            return null;
        }
        
        $diff = $dt1->diff($dt2);
        
        return [
            'years' => $diff->y,
            'months' => $diff->m,
            'days' => $diff->d,
            'hours' => $diff->h,
            'minutes' => $diff->i,
            'seconds' => $diff->s,
            'invert' => $diff->invert, // 1 se $dt1 > $dt2, altrimenti 0
            'total_days' => $diff->days, // Differenza totale in giorni
        ];
    }
    
    /**
     * Calculates the difference in days between two dates
     * 
     * @param mixed $date1 First date
     * @param mixed $date2 Second date
     * @param bool $absolute If true, returns the absolute value
     * @return int|null Difference in days
     */
    public function func_days_diff($date1, $date2, $absolute = true) {
        $dt1 = $this->parse_date($date1);
        $dt2 = $this->parse_date($date2);
        
        if ($dt1 === null || $dt2 === null) {
            return null;
        }
        
        $diff = $dt1->diff($dt2, $absolute);
        return $diff->days;
    }
    
    /**
     * Calculates the difference in months between two dates
     * 
     * @param mixed $date1 First date
     * @param mixed $date2 Second date
     * @param bool $absolute If true, returns the absolute value
     * @return float|null Difference in months (approximated)
     */
    public function func_months_diff($date1, $date2, $absolute = true) {
        $dt1 = $this->parse_date($date1);
        $dt2 = $this->parse_date($date2);
        
        if ($dt1 === null || $dt2 === null) {
            return null;
        }
        
        $diff = $dt1->diff($dt2, $absolute);
        return $diff->y * 12 + $diff->m + $diff->d / 30;
    }
    
    /**
     * Calculates the difference in years between two dates
     * 
     * @param mixed $date1 First date
     * @param mixed $date2 Second date
     * @param bool $absolute If true, returns the absolute value
     * @return float|null Difference in years (approximated)
     */
    public function func_years_diff($date1, $date2, $absolute = true) {
        $dt1 = $this->parse_date($date1);
        $dt2 = $this->parse_date($date2);
        
        if ($dt1 === null || $dt2 === null) {
            return null;
        }
        
        $diff = $dt1->diff($dt2, $absolute);
        return $diff->y + $diff->m / 12 + $diff->d / 365.25;
    }
    
    /**
     * Calculates the difference in hours between two dates
     * 
     * @param mixed $date1 First date
     * @param mixed $date2 Second date
     * @param bool $absolute If true, returns the absolute value
     * @return float|null Difference in hours
     */
    public function func_hours_diff($date1, $date2, $absolute = true) {
        $dt1 = $this->parse_date($date1);
        $dt2 = $this->parse_date($date2);
        
        if ($dt1 === null || $dt2 === null) {
            return null;
        }
        
        $diff = $dt1->diff($dt2, $absolute);
        return $diff->days * 24 + $diff->h + $diff->i / 60 + $diff->s / 3600;
    }
    
    /**
     * Calculates the difference in minutes between two dates
     * 
     * @param mixed $date1 First date
     * @param mixed $date2 Second date
     * @param bool $absolute If true, returns the absolute value
     * @return float|null Difference in minutes
     */
    public function func_minutes_diff($date1, $date2, $absolute = true) {
        $dt1 = $this->parse_date($date1);
        $dt2 = $this->parse_date($date2);
        
        if ($dt1 === null || $dt2 === null) {
            return null;
        }
        
        $diff = $dt1->diff($dt2, $absolute);
        return $diff->days * 1440 + $diff->h * 60 + $diff->i + $diff->s / 60;
    }
    
    /**
     * Calculates the difference in seconds between two dates
     * 
     * @param mixed $date1 First date
     * @param mixed $date2 Second date
     * @return int|null Difference in seconds
     */
    public function func_seconds_diff($date1, $date2) {
        $dt1 = $this->parse_date($date1);
        $dt2 = $this->parse_date($date2);
        
        if ($dt1 === null || $dt2 === null) {
            return null;
        }
        
        return $dt2->getTimestamp() - $dt1->getTimestamp();
    }
    
    /**
     * Estrae l'anno da una data
     * 
     * @param mixed $date Data da analizzare
     * @return int|null Anno
     */
    public function func_year($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        return (int)$dt->format('Y');
    }
    
    /**
     * Estrae il mese da una data
     * 
     * @param mixed $date Data da analizzare
     * @return int|null Mese (1-12)
     */
    public function func_month($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        return (int)$dt->format('m');
    }
    
    /**
     * Estrae il giorno da una data
     * 
     * @param mixed $date Data da analizzare
     * @return int|null Giorno (1-31)
     */
    public function func_day($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        return (int)$dt->format('d');
    }
    
    /**
     * Estrae l'ora da una data
     * 
     * @param mixed $date Data da analizzare
     * @return int|null Ora (0-23)
     */
    public function func_hour($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        return (int)$dt->format('H');
    }
    
    /**
     * Estrae i minuti da una data
     * 
     * @param mixed $date Data da analizzare
     * @return int|null Minuti (0-59)
     */
    public function func_minute($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        return (int)$dt->format('i');
    }
    
    /**
     * Estrae i secondi da una data
     * 
     * @param mixed $date Data da analizzare
     * @return int|null Secondi (0-59)
     */
    public function func_second($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        return (int)$dt->format('s');
    }
    
    /**
     * Restituisce il giorno della settimana
     * 
     * @param mixed $date Data da analizzare
     * @param bool $as_text Se true, restituisce il nome del giorno
     * @param string $language Lingua per il nome del giorno ('it', 'en')
     * @return mixed|null Numero (1=lunedì, 7=domenica) o nome del giorno
     */
    public function func_weekday($date, $as_text = false, $language = 'it') {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        $weekday_num = (int)$dt->format('N'); // 1 (lunedì) a 7 (domenica)
        
        if (!$as_text) {
            return $weekday_num;
        }
        
        // Nomi dei giorni della settimana in diverse lingue
        $weekday_names = [
            'it' => ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'],
            'en' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        ];
        
        // Seleziona la lingua o usa l'italiano come default
        $language = strtolower($language);
        if (!isset($weekday_names[$language])) {
            $language = 'it';
        }
        
        return $weekday_names[$language][$weekday_num - 1];
    }
    
    /**
     * Restituisce il numero della settimana nell'anno
     * 
     * @param mixed $date Data da analizzare
     * @return int|null Numero della settimana (1-53)
     */
    public function func_week_number($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        return (int)$dt->format('W');
    }
    
    /**
     * Restituisce il giorno dell'anno
     * 
     * @param mixed $date Data da analizzare
     * @return int|null Giorno dell'anno (1-366)
     */
    public function func_day_of_year($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        return (int)$dt->format('z') + 1; // z è 0-indexed
    }
    
    /**
     * Restituisce il trimestre dell'anno
     * 
     * @param mixed $date Data da analizzare
     * @return int|null Trimestre (1-4)
     */
    public function func_quarter($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        $month = (int)$dt->format('m');
        return ceil($month / 3);
    }
    
    /**
     * Restituisce l'ultimo giorno del mese
     * 
     * @param mixed $date Data da analizzare
     * @return string|null Ultimo giorno del mese in formato MySQL
     */
    public function func_last_day($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        $year = (int)$dt->format('Y');
        $month = (int)$dt->format('m');
        
        // Crea una data per il primo giorno del mese successivo e sottrai 1 giorno
        $last_day = new DateTime("$year-$month-01");
        $last_day->add(new DateInterval('P1M'));
        $last_day->sub(new DateInterval('P1D'));
        
        return $last_day->format('Y-m-d');
    }
    
    /**
     * Restituisce il primo giorno del mese
     * 
     * @param mixed $date Data da analizzare
     * @return string|null Primo giorno del mese in formato MySQL
     */
    public function func_first_day($date) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        $year = (int)$dt->format('Y');
        $month = (int)$dt->format('m');
        
        return "$year-$month-01";
    }
    
    /**
     * Tronca una data a un livello specifico (anno, mese, giorno, ora, minuto)
     * 
     * @param mixed $date Data da troncare
     * @param string $level Livello di troncamento (year|month|day|hour|minute)
     * @return string|null Data troncata in formato MySQL
     */
    public function func_date_truncate($date, $level = 'day') {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        $year = (int)$dt->format('Y');
        $month = (int)$dt->format('m');
        $day = (int)$dt->format('d');
        $hour = (int)$dt->format('H');
        $minute = (int)$dt->format('i');
        
        $level = strtolower($level);
        switch ($level) {
            case 'year':
                return "$year-01-01 00:00:00";
            case 'month':
                return "$year-$month-01 00:00:00";
            case 'day':
                return "$year-$month-$day 00:00:00";
            case 'hour':
                return "$year-$month-$day $hour:00:00";
            case 'minute':
                return "$year-$month-$day $hour:$minute:00";
            default:
                throw new Exception("Livello di troncamento non valido: $level");
        }
    }
    
    /**
     * Calculates the number of business days between two dates
     * 
     * @param mixed $start_date Start date
     * @param mixed $end_date End date
     * @param array $holidays Array of holiday dates (optional)
     * @return int|null Number of business days
     */
    public function func_business_days($date1, $date2, $holidays = []) {
        $dt1 = $this->parse_date($date1);
        $dt2 = $this->parse_date($date2);
        
        if ($dt1 === null || $dt2 === null) {
            return null;
        }
        
        // Assicurati che $dt1 sia sempre la data precedente
        if ($dt1 > $dt2) {
            list($dt1, $dt2) = [$dt2, $dt1];
        }
        
        // Converti le date festive in oggetti DateTime
        $holiday_dates = [];
        foreach ($holidays as $holiday) {
            $holiday_dt = $this->parse_date($holiday);
            if ($holiday_dt !== null) {
                $holiday_dates[] = $holiday_dt->format('Y-m-d');
            }
        }
        
        // Clona $dt1 per non modificare l'originale
        $current = clone $dt1;
        $current->setTime(0, 0, 0);
        
        // Clona $dt2 e imposta l'ora a mezzanotte
        $dt2_midnight = clone $dt2;
        $dt2_midnight->setTime(0, 0, 0);
        
        $business_days = 0;
        
        // Itera fino a raggiungere o superare la data finale
        while ($current <= $dt2_midnight) {
            // Controlla se è un giorno lavorativo (non weekend e non festivo)
            $weekday = (int)$current->format('N');
            if ($weekday <= 5 && !in_array($current->format('Y-m-d'), $holiday_dates)) {
                $business_days++;
            }
            
            // Passa al giorno successivo
            $current->add(new DateInterval('P1D'));
        }
        
        return $business_days;
    }
    
    /**
     * Checks if a date is a business day
     * 
     * @param mixed $date Date to check
     * @param array $holidays Array of holiday dates (optional)
     * @return bool|null True if it's a business day
     */
    public function func_is_business_day($date, $holidays = []) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        // Converti le date festive in oggetti DateTime
        $holiday_dates = [];
        foreach ($holidays as $holiday) {
            $holiday_dt = $this->parse_date($holiday);
            if ($holiday_dt !== null) {
                $holiday_dates[] = $holiday_dt->format('Y-m-d');
            }
        }
        
        // Controlla se è weekend (6 = sabato, 7 = domenica)
        $weekday = (int)$dt->format('N');
        if ($weekday > 5) {
            return false;
        }
        
        // Controlla se è una festività
        return !in_array($dt->format('Y-m-d'), $holiday_dates);
    }
    
    /**
     * Finds the next business day
     * 
     * @param mixed $date Starting date
     * @param array $holidays Array of holiday dates (optional)
     * @return string|null Next business day in MySQL format
     */
    public function func_next_business_day($date, $holidays = []) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        // Clona $dt per non modificare l'originale
        $current = clone $dt;
        
        // Vai al giorno successivo
        $current->add(new DateInterval('P1D'));
        
        // Cerca il prossimo giorno lavorativo
        while (!$this->func_is_business_day($current, $holidays)) {
            $current->add(new DateInterval('P1D'));
        }
        
        return $current->format('Y-m-d');
    }
    
    /**
     * Finds the previous business day
     * 
     * @param mixed $date Starting date
     * @param array $holidays Array of holiday dates (optional)
     * @return string|null Previous business day in MySQL format
     */
    public function func_prev_business_day($date, $holidays = []) {
        $dt = $this->parse_date($date);
        if ($dt === null) {
            return null;
        }
        
        // Clona $dt per non modificare l'originale
        $current = clone $dt;
        
        // Vai al giorno precedente
        $current->sub(new DateInterval('P1D'));
        
        // Cerca il precedente giorno lavorativo
        while (!$this->func_is_business_day($current, $holidays)) {
            $current->sub(new DateInterval('P1D'));
        }
        
        return $current->format('Y-m-d');
    }
    
    /**
     * Calculates age in years from a birth date
     * 
     * @param mixed $birth_date Birth date
     * @param mixed $reference_date Reference date (default: today)
     * @return int|null Age in years
     */
    public function func_age($birth_date, $reference_date = null) {
        $birth_dt = $this->parse_date($birth_date);
        if ($birth_dt === null) {
            return null;
        }
        
        // Se non è specificata una data di riferimento, usa oggi
        if ($reference_date === null) {
            $ref_dt = new DateTime();
        } else {
            $ref_dt = $this->parse_date($reference_date);
            if ($ref_dt === null) {
                return null;
            }
        }
        
        // Calculate the age difference
        $diff = $birth_dt->diff($ref_dt);
        
        // If the birth date is in the future relative to the reference date
        if ($diff->invert === 1) {
            return 0;
        }
        
        return $diff->y;
    }

    /**
     * Finds the oldest date (smallest) from an array of dates
     * 
     * @param array $dates Array of dates to compare
     * @return string|null Oldest date in MySQL format or null if the array is empty
     */
    public function func_min_date($dates) {
        if (!is_array($dates) || empty($dates)) {
            return null;
        }
        
        $min_date = null;
        
        foreach ($dates as $date) {
            $dt = $this->parse_date($date);
            if ($dt === null) {
                continue; // Skip invalid dates
            }
            
            if ($min_date === null || $dt < $min_date) {
                $min_date = $dt;
            }
        }
        
        return $min_date ? $min_date->format('Y-m-d H:i:s') : null;
    }

    /**
     * Finds the most recent date (largest) from an array of dates
     * 
     * @param array $dates Array of dates to compare
     * @return string|null Most recent date in MySQL format or null if the array is empty
     */
    public function func_max_date($dates) {
        if (!is_array($dates) || empty($dates)) {
            return null;
        }
        
        $max_date = null;
        
        foreach ($dates as $date) {
            $dt = $this->parse_date($date);
            if ($dt === null) {
                continue; // Skip invalid dates
            }
            
            if ($max_date === null || $dt > $max_date) {
                $max_date = $dt;
            }
        }
        
        return $max_date ? $max_date->format('Y-m-d H:i:s') : null;
    }
}