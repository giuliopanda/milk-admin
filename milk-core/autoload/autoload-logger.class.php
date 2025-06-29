<?php
namespace MilkCore;

/**
 * Logger statico semplificato per l'autoloading
 */
class AutoloadLogger
{
    private static array $logs = [];
    
    /**
     * Registra un messaggio di log
     */
    public static function log(string $message): void
    {
        $timestamp = date('H:i:s');
        self::$logs[] = "[{$timestamp}] {$message}";
    }
    
    /**
     * Ritorna l'array completo dei log
     */
    public static function getLogs(): array
    {
        return self::$logs;
    }
    
    /**
     * Stampa tutti i log in CLI
     */
    public static function printCli(): void
    {
        foreach (self::$logs as $log) {
            echo $log . "\n";
        }
    }

    public static function showLogInFatalError(): void
    {
        // Catturo l'evento fatal error
        register_shutdown_function(function () {
            print "---------------";
            AutoloadLogger::showLog();
        });
    }
    

    public static function showLog(): void
    {
        print "<h2>Log</h2>";
        echo implode("\n", self::$logs);
    }
    
    /**
     * Pulisce i log
     */
    public static function clear(): void
    {
        self::$logs = [];
    }
}
// Show log in fatal error
//AutoloadLogger::showLogInFatalError();