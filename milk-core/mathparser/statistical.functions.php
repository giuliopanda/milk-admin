<?php
namespace MilkCore;
use MilkCore\FunctionProvider;
/**
 * Provider for advanced statistical functions
 * 
 * @package     MilkCore
 * @subpackage  MathParser
 * @ignore
 */

class StatisticalFunctions extends FunctionProvider {
    private $data_row_manager;
    
    /**
     * Constructor
     * 
     * @param DataRowManager $manager DataRowManager instance
     * @return void
     */
    public function __construct($manager = null) {
        $this->data_row_manager = $manager;
    }
    
    /**
     * Returns all implemented statistical functions
     * 
     * @return array Associative array of functions (name => callback)
     */
    public function get_functions() {
        return [
        
    
            // Central tendency measures
            'MEDIAN' => [$this, 'func_median'],
            'MODE' => [$this, 'func_mode'],
            
            // Dispersion measures
            'STDEV' => [$this, 'func_stdev'],
            'VAR' => [$this, 'func_variance'],
            'RANGE' => [$this, 'func_range'],
            'PERCENTILE' => [$this, 'func_percentile'],
            'QUARTILE' => [$this, 'func_quartile'],
            'IQR' => [$this, 'func_iqr'],
            
            // Distribution analysis
            'SKEW' => [$this, 'func_skewness'],
            'KURTOSIS' => [$this, 'func_kurtosis'],
            'FREQUENCY' => [$this, 'func_frequency'],
            
            // Correlation and regression
            'CORREL' => [$this, 'func_correlation'],
            'COVAR' => [$this, 'func_covariance'],
            'RSQUARED' => [$this, 'func_r_squared'],
            
            // Normalization and scores
            'ZSCORE' => [$this, 'func_zscore'],
            'STANDARDIZE' => [$this, 'func_standardize'],
            'NORMALIZE' => [$this, 'func_normalize'],
            
            // Intervals and tests
            'CONFIDENCE_INTERVAL' => [$this, 'func_confidence_interval'],
            'TTEST' => [$this, 'func_ttest'],
        ];
    }
    
    /**
     * Collects numeric values from a column
     * 
     * @param string $column_name Column name
     * @return array Array of numeric values
     */
    private function get_numeric_values($column_name) {
        $values = [];
        // get current index
       
        foreach ($this->data_row_manager->get_data() as $row) {
            if (isset($row[$column_name]) && is_numeric($row[$column_name])) {
                $values[] = (float)$row[$column_name];
            }
        }
       
        return $values;
    }
    
    /**
     * Calculates the median of a column
     * 
     * @param string $column_name Column name
     * @return float Median value
     */
    public function func_median($column_name) {
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values)) {
            return null;
        }
        
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);
        
        if ($count % 2 == 0) {
            // For even number of elements, average of the two central values
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            // For odd number of elements, central value
            return $values[$middle];
        }
    }
    
    /**
     * Calculates the mode (most frequent value) of a column
     * 
     * @param string $column_name Column name
     * @return mixed Most frequent value
     */
    public function func_mode($column_name) {
        $values = [];
        foreach ($this->data_row_manager->get_data() as $row) {
            if (isset($row[$column_name])) {
                $values[] = $row[$column_name];
            }
        }
        
        if (empty($values)) {
            return null;
        }
        
        $frequency = array_count_values($values);
        arsort($frequency);
        
        return key($frequency);
    }
    
    /**
     * Calculates the standard deviation of a column
     * 
     * @param string $column_name Column name
     * @param bool $population If true, calculates population standard deviation,
     *                         otherwise uses sample (n-1)
     * @return float Standard deviation
     */
    public function func_stdev($column_name, $population = false) {
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values) || count($values) < 2) {
            return null;
        }
        
        $variance = $this->func_variance($column_name, $population);
        return sqrt($variance);
    }
    
    /**
     * Calculates the variance of a column
     * 
     * @param string $column_name Column name
     * @param bool $population If true, calculates population variance,
     *                         otherwise uses sample (n-1)
     * @return float Variance
     */
    public function func_variance($column_name, $population = false) {
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values) || count($values) < 2) {
            return null;
        }
        
        $mean = array_sum($values) / count($values);
        $sum_squared_diff = 0;
        
        foreach ($values as $value) {
            $diff = $value - $mean;
            $sum_squared_diff += $diff * $diff;
        }
        
        $count = count($values);
        // Per il campione (n-1), per la popolazione (n)
        $divisor = $population ? $count : $count - 1;
        
        return $sum_squared_diff / $divisor;
    }
    
    /**
     * Calculates the range (maximum - minimum) of a column
     * 
     * @param string $column_name Column name
     * @return float Range
     */
    public function func_range($column_name) {
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values)) {
            return null;
        }
        
        return max($values) - min($values);
    }
    
    /**
     * Calculates a specific percentile of a column
     * 
     * @param string $column_name Column name
     * @param float $percentile Percentile (0-1)
     * @return float Value at the specified percentile
     */
    public function func_percentile($column_name, $percentile) {
        if ($percentile < 0 || $percentile > 1) {
            throw new \Exception("il percentile deve essere tra 0 e 1");
        }
        
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values)) {
            return null;
        }
        
        sort($values);
        $count = count($values);
        
        $index = $percentile * ($count - 1);
        $floor = floor($index);
        $fraction = $index - $floor;
        
        if ($floor + 1 < $count) {
            return $values[$floor] + $fraction * ($values[$floor + 1] - $values[$floor]);
        } else {
            return $values[$floor];
        }
    }
    
    /**
     * Calculates a specific quartile of a column
     * 
     * @param string $column_name Column name
     * @param int $quartile Quartile (0-4, where 0=min, 1=Q1, 2=Q2/median, 3=Q3, 4=max)
     * @return float Value at the specified quartile
     */
    public function func_quartile($column_name, $quartile) {
        if ($quartile < 0 || $quartile > 4) {
            throw new \Exception("il quartile deve essere tra 0 e 4");
        }
        
        if ($quartile == 0) {
            return min($this->get_numeric_values($column_name));
        } else if ($quartile == 4) {
            return max($this->get_numeric_values($column_name));
        } else {
            return $this->func_percentile($column_name, $quartile / 4);
        }
    }
    
    /**
     * Calculates the interquartile range (IQR) of a column
     * 
     * @param string $column_name Column name
     * @return float Interquartile range (Q3-Q1)
     */
    public function func_iqr($column_name) {
        $q1 = $this->func_quartile($column_name, 1);
        $q3 = $this->func_quartile($column_name, 3);
        
        return $q3 - $q1;
    }
    
    /**
     * Calculates the skewness of a distribution
     * 
     * @param string $column_name Column name
     * @return float Skewness
     */
    public function func_skewness($column_name) {
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values) || count($values) < 3) {
            return null;
        }
        
        $n = count($values);
        $mean = array_sum($values) / $n;
        $m3 = 0; // Terzo momento
        $m2 = 0; // Secondo momento (varianza)
        
        foreach ($values as $value) {
            $dev = $value - $mean;
            $m3 += pow($dev, 3);
            $m2 += pow($dev, 2);
        }
        
        $m3 /= $n;
        $m2 /= $n;
        
        $stdev = sqrt($m2);
        
        if ($stdev == 0) {
            return 0;
        }
        
        return $m3 / pow($stdev, 3);
    }
    
    /**
     * Calculates the kurtosis of a distribution
     * 
     * @param string $column_name Column name
     * @return float Kurtosis
     */
    public function func_kurtosis($column_name) {
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values) || count($values) < 4) {
            return null;
        }
        
        $n = count($values);
        $mean = array_sum($values) / $n;
        $m4 = 0; // Quarto momento
        $m2 = 0; // Secondo momento (varianza)
        
        foreach ($values as $value) {
            $dev = $value - $mean;
            $m4 += pow($dev, 4);
            $m2 += pow($dev, 2);
        }
        
        $m4 /= $n;
        $m2 /= $n;
        
        if ($m2 == 0) {
            return 0;
        }
        
        // Eccesso di curtosi (normal = 0)
        return $m4 / pow($m2, 2) - 3;
    }
    
    /**
     * Calculates the frequency of values in a column
     * 
     * @param string $column_name Column name
     * @return array Associative array with frequencies (value => count)
     */
    public function func_frequency($column_name) {
        $values = [];
        foreach ($this->data_row_manager->get_data() as $row) {
            if (isset($row[$column_name])) {
                $values[] = $row[$column_name];
            }
        }
        
        return array_count_values($values);
    }
    
    /**
     * Calculates the Pearson correlation coefficient between two columns
     * 
     * @param string $column_x Name of the first column
     * @param string $column_y Name of the second column
     * @return float Correlation coefficient (-1 to 1)
     */
    public function func_correlation($column_x, $column_y) {
        $data_x = [];
        $data_y = [];
        
        // Raccoglie coppie di valori validi
        foreach ($this->data_row_manager->get_data() as $row) {
            if (isset($row[$column_x]) && isset($row[$column_y]) &&
                is_numeric($row[$column_x]) && is_numeric($row[$column_y])) {
                $data_x[] = (float)$row[$column_x];
                $data_y[] = (float)$row[$column_y];
            }
        }
        
        $n = count($data_x);
        
        if ($n < 2) {
            return null;
        }
        
        // Calcola medie
        $mean_x = array_sum($data_x) / $n;
        $mean_y = array_sum($data_y) / $n;
        
        // Calcola covarianza e varianze
        $cov = 0;
        $var_x = 0;
        $var_y = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $diff_x = $data_x[$i] - $mean_x;
            $diff_y = $data_y[$i] - $mean_y;
            
            $cov += $diff_x * $diff_y;
            $var_x += $diff_x * $diff_x;
            $var_y += $diff_y * $diff_y;
        }
        
        if ($var_x == 0 || $var_y == 0) {
            return 0; // Non c'è correlazione se una delle variabili non varia
        }
        
        return $cov / (sqrt($var_x) * sqrt($var_y));
    }
    
    /**
     * Calculates the covariance between two columns
     * 
     * @param string $column_x Name of the first column
     * @param string $column_y Name of the second column
     * @param bool $population If true, calculates population covariance,
     *                         otherwise uses sample (n-1)
     * @return float Covariance
     */
    public function func_covariance($column_x, $column_y, $population = false) {
        $data_x = [];
        $data_y = [];
        
        // Raccoglie coppie di valori validi
        foreach ($this->data_row_manager->get_data() as $row) {
            if (isset($row[$column_x]) && isset($row[$column_y]) &&
                is_numeric($row[$column_x]) && is_numeric($row[$column_y])) {
                $data_x[] = (float)$row[$column_x];
                $data_y[] = (float)$row[$column_y];
            }
        }
        
        $n = count($data_x);
        
        if ($n < 2) {
            return null;
        }
        
        // Calcola medie
        $mean_x = array_sum($data_x) / $n;
        $mean_y = array_sum($data_y) / $n;
        
        // Calcola covarianza
        $cov = 0;
        for ($i = 0; $i < $n; $i++) {
            $cov += ($data_x[$i] - $mean_x) * ($data_y[$i] - $mean_y);
        }
        
        // Divisore: n per popolazione, n-1 per campione
        $divisor = $population ? $n : $n - 1;
        
        return $cov / $divisor;
    }
    
    /**
     * Calculate the coefficient of determination (R²) between two columns
     * 
     * @param string $column_x Name of the independent column
     * @param string $column_y Name of the dependent column
     * @return float Coefficient of determination (0-1)
     */
    public function func_r_squared($column_x, $column_y) {
        $corr = $this->func_correlation($column_x, $column_y);
        
        if ($corr === null) {
            return null;
        }
        
        return $corr * $corr;
    }
    
    /**
     * Calculates the z-score of a value relative to a column
     * 
     * @param float $value Value to standardize
     * @param string $column_name Reference column name
     * @return float Z-score (standard deviations from the mean)
     */
    public function func_zscore($value, $column_name) {
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values)) {
            return null;
        }
        
        $mean = array_sum($values) / count($values);
        $stdev = $this->func_stdev($column_name, true); // Deviazione standard della popolazione
        
        if ($stdev == 0) {
            return 0; // Evita divisione per zero
        }
        
        return ($value - $mean) / $stdev;
    }
    
    /**
     * Standardizes a value relative to specified mean and standard deviation
     * 
     * @param float $value Value to standardize
     * @param float $mean Mean
     * @param float $stdev Standard deviation
     * @return float Standardized value
     */
    public function func_standardize($value, $mean, $stdev) {
        if ($stdev == 0) {
            return 0; // Evita divisione per zero
        }
        
        return ($value - $mean) / $stdev;
    }
    
    /**
     * Normalizes a value to the range [0,1]
     * 
     * @param float $value Value to normalize
     * @param string $column_name Column name
     * @return float Normalized value (0-1)
     */
    public function func_normalize($value, $column_name) {
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values)) {
            return null;
        }
        
        $min = min($values);
        $max = max($values);
        
        if ($max == $min) {
            return 0.5; // If all values are equal, return 0.5
        }
        
        return ($value - $min) / ($max - $min);
    }
    
    /**
     * Calculates the confidence interval for the mean of a column
     * 
     * @param string $column_name Column name
     * @param float $alpha Significance level (default 0.05 for 95% confidence)
     * @return array Array with lower and upper limits of the interval
     */
    public function func_confidence_interval($column_name, $alpha = 0.05) {
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values) || count($values) < 2) {
            return null;
        }
        
        $n = count($values);
        $mean = array_sum($values) / $n;
        $stdev = $this->func_stdev($column_name, false); // Deviazione standard del campione
        
        // Z approximation for large samples
        // Should actually use t-student distribution for small samples
        $z = 1.96; // Per alpha = 0.05 (95% confidenza)
        if ($alpha == 0.01) $z = 2.576; // 99% confidenza
        if ($alpha == 0.1) $z = 1.645; // 90% confidenza
        
        $margin_of_error = $z * $stdev / sqrt($n);
        
        return [
            'lower' => $mean - $margin_of_error,
            'upper' => $mean + $margin_of_error,
            'mean' => $mean,
            'margin_of_error' => $margin_of_error
        ];
    }
    
    /**
     * Performs a t-test to compare the mean of a column with a value
     * 
     * @param string $column_name Column name
     * @param float $test_value Value to compare
     * @return array Array with t-statistic and approximate p-value
     */
    public function func_ttest($column_name, $test_value) {
        $values = $this->get_numeric_values($column_name);
        
        if (empty($values) || count($values) < 2) {
            return null;
        }
        
        $n = count($values);
        $mean = array_sum($values) / $n;
        $stdev = $this->func_stdev($column_name, false); // Deviazione standard del campione
        
        $t_statistic = ($mean - $test_value) / ($stdev / sqrt($n));
        
        // Approximation of the p-value (not exact)
        // For an exact p-value, probability density functions would be needed
        $df = $n - 1; // Gradi di libertà
        // This is a very rough approximation of the p-value
        $p_value = min(1, 2 * (1 - min(1, abs($t_statistic) / sqrt($df))));
        
        return [
            't_statistic' => $t_statistic,
            'p_value' => $p_value,
            'degrees_of_freedom' => $df
        ];
    }
}