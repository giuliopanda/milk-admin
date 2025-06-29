<?php
namespace Modules\ApiRegistry;

use MilkCore\Get;
use MilkCore\Token;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * ApiRegistryServices Class
 * 
 * Service class for handling API registry operations.
 * Provides methods for displaying and managing APIs and their logs.
 * 
 * @package     Modules\ApiRegistry
 */
class ApiRegistryServices {
    /**
     * Model instance
     */
    protected static $model = null;
    
    /**
     * Set the model instance to use for database operations
     * 
     * @param ApiRegistryLogModel $model The model instance
     * @return void
     */
    public static function set_model($model) {
        self::$model = $model;
    }
  
    
    /**
     * Format duration in seconds to human-readable format
     * 
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    public static function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . ' sec';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return $minutes . ' min ' . $secs . ' sec';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;
            return $hours . ' hr ' . $minutes . ' min ' . $secs . ' sec';
        }
    }
    
}
