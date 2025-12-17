<?php
namespace Modules\Db2tables;

use App\Get;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Import services for DB2Tables module - Multi-database version
 * Handles CSV import functionality for both MySQL and SQLite
 */
class Db2tablesImportServices
{
    public static function sanitizeTableName($new_table_name) {
        $new_table_name = strtolower($new_table_name); // Converti in minuscolo
        $new_table_name = preg_replace('/[^a-z0-9_]/', '_', $new_table_name); // Sostituisci caratteri non validi con underscore
        $new_table_name = preg_replace('/_+/', '_', $new_table_name); // Riduci underscore multipli a uno solo
        $new_table_name = trim($new_table_name, '_'); // Rimuovi underscore all'inizio e alla fine
        
        // Tronca il nome a 60 caratteri (limite comune per i nomi di tabelle in MySQL)
        if (strlen($new_table_name) > 60) {
            $new_table_name = substr($new_table_name, 0, 60);
            // Assicurati che non finisca con un underscore
            $new_table_name = rtrim($new_table_name, '_');
        }
        
        // Assicurati che il nome non inizi con un numero
        if (preg_match('/^[0-9]/', $new_table_name)) {
            $new_table_name = 'table_' . $new_table_name;
        }
        
        // Assicurati che il nome non sia vuoto dopo la trasformazione
        if (empty($new_table_name)) {
            $new_table_name = 'table_' . time();
        }
         // Verifica se la tabella esiste già e crea una versione incrementale se necessario
         $db2 = Db2tablesServices::getDb();
         $base_name = $new_table_name;
         $counter = 1;
         
        while ($counter <= 99) {
            $exists = $db2->getTables();
            
            if (!in_array($new_table_name, $exists)) {
                // La tabella non esiste, possiamo usare questo nome
                break;
            }
            
            // La tabella esiste, incrementa il contatore e prova un nuovo nome
            $new_table_name = $base_name . '_' . sprintf('%02d', $counter);
            $counter++;
            
            // Evita loop infiniti
           
        }
        if ($counter == 99) {
            $new_table_name = $base_name . '_' . time();
        }
        return $new_table_name;
    }

}