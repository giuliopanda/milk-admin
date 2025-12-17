<?php
namespace Modules\Db2tables;

use App\Database\Query;
use App\Get;

!defined('MILK_DIR') && die(); // Evita accesso diretto

/**
 * Classe SQLParser per analizzare una query SQL e convertirla in un'istanza di Query.
 * 
 * Questa classe prende una stringa SQL (che può contenere più query separate da punto e virgola),
 * ne effettua il parsing e crea istanze della classe Query con le varie parti compilate
 * (SELECT, FROM, WHERE, ecc.).
 * 
 * Esempio di utilizzo:
 * ```
 * $parser = new SQLParser('SELECT campo1, campo2 FROM tabella WHERE campo1 = ? ORDER BY campo1 DESC LIMIT 0, 10');
 * $queries = $parser->parse();
 * ```
 * 
 * Le funzioni pubbliche sono:
 * - parse(): analizza le query SQL e restituisce un array di istanze di Query compilate.
 * - parseSingle($index): analizza una singola query SQL e restituisce un'istanza di Query compilata.
 * - getQueryCount(): restituisce il numero di query valide trovate.
 * - getQueries(): restituisce l'array delle query originali.
 */
class SQLParser
{
    /**
     * @var string Query SQL da analizzare
     */
    private $sql_string = '';
    
    /**
     * @var object Istanza del database
     */
    private $db = null;
    
    /**
     * @var array Array di stringhe SQL separate
     */
    private $queries = [];
    
    /**
     * Costruttore della classe SQLParser.
     * 
     * @param string $sql_string Query SQL da analizzare (può contenere query multiple separate da ;)
     * @param object $db Istanza del database (opzionale)
     */
    public function __construct($sql_string) {
        $this->sql_string = trim($sql_string);
        $this->db = Db2tablesServices::getDb();
        
        // Suddivide il SQL in più query
        $this->queries = $this->splitMultipleQueries($this->sql_string);
    }

     /**
     * Analizza le query SQL e restituisce un array di istanze di Query compilate.
     * Le subquery (contenuto tra parentesi tonde) non vengono analizzate internamente,
     * ma vengono trattate come entità singole.
     * 
     * @return array Array di istanze di Query compilate con le parti estratte dalle query SQL o stringhe
     * @throws \Exception se nessuna query valida è presente
     */
    public function parse(): array {
        $results = [];
    
        if (empty($this->queries)) {
            throw new \Exception('Nessuna query SQL valida trovata');
        }
        
        foreach ($this->queries as $query_string) {
            // Verifica che la query inizi con SELECT o PRAGMA
            if (preg_match('/^SELECT\s+/i', trim($query_string))) {
            
                // Salva temporaneamente la query corrente
                $original_sql = $this->sql_string;
                $this->sql_string = $query_string;
                
                try {
                    // Estrae le diverse parti della query
                    $parts = $this->extractParts();
                    if (is_string($parts)) {
                        $results[] = $parts;
                        continue;
                    }
                    // Estrae la tabella principale dal FROM
                    list($table, $other_tables) = $this->extractTables($parts['from']);
                    
                    // Crea un'istanza di Query
                    $query = new Query($table, $this->db);
                    
                    // Compila la parte SELECT
                    $this->parseSelect($query, $parts['select']);
                    
                    // Compila la parte FROM (JOIN, ecc.)
                    $this->parseFrom($query, $other_tables);
                    
                    // Compila la parte WHERE
                    $this->parseWhere($query, $parts['where']);
                    
                    // Compila la parte GROUP BY
                    if (!empty($parts['group'])) {
                        $query->group($this->removeBackticks($parts['group']));
                    }
                    
                    // Compila la parte HAVING (se supportata)
                    if (!empty($parts['having'])) {
                        $this->parseHaving($query, $parts['having']);
                    }
                    
                    // Compila la parte ORDER BY
                    $this->parseOrder($query, $parts['order']);
                    
                    // Compila la parte LIMIT
                    $this->parseLimit($query, $parts['limit']);
                    
                    // Aggiungi la query all'array dei risultati
                    $results[] = $query;
                } catch (\Exception $e) {
                    // Salta le query che generano eccezioni durante il parsing
                    continue;
                }

                   // Ripristina la query originale
            $this->sql_string = $original_sql;
            } else {
                // Per le query non-SELECT, restituirle come stringhe
                $results[] = $query_string;
            }
            
        }

        if (empty($results)) {
            throw new \Exception('Nessuna query SQL valida analizzata');
        }
        
        return $results;
    }
    
    /**
     * Analizza una singola query SQL e restituisce un'istanza di Query compilata.
     * Utile quando si vuole elaborare solo una specifica query dell'insieme.
     * 
     * @param int $index Indice della query da analizzare (0 per la prima)
     * @return Query|string Istanza di Query compilata con le parti estratte dalla query SQL
     * @throws \Exception se l'indice non è valido
     */
    public function parseSingle($index = 0): Query|string {
        if (!isset($this->queries[$index])) {
            throw new \Exception("Query con indice $index non trovata");
        }
        
        // Salva temporaneamente la query originale
        $original_sql = $this->sql_string;
        $this->sql_string = $this->queries[$index];
        
        try {
            // Verifica che la query inizi con SELECT
            if (!preg_match('/^SELECT\s+/i', $this->sql_string)) {
                return $this->sql_string;
            }
            
            // Estrae le diverse parti della query
            $parts = $this->extractParts();
            
            // Estrae la tabella principale dal FROM
            list($table, $other_tables) = $this->extractTables($parts['from']);
            
            // Crea un'istanza di Query
            $query = new Query($table, $this->db);
            
            // Compila la parte SELECT
            $this->parseSelect($query, $parts['select']);
            
            // Compila la parte FROM (JOIN, ecc.)
            $this->parseFrom($query, $other_tables);
            
            // Compila la parte WHERE
            $this->parseWhere($query, $parts['where']);
            
            // Compila la parte GROUP BY
            if (!empty($parts['group'])) {
                $query->group($this->removeBackticks($parts['group']));
            }
            
            // Compila la parte HAVING (se supportata)
            if (!empty($parts['having'])) {
                $this->parseHaving($query, $parts['having']);
            }
            
            // Compila la parte ORDER BY
            $this->parseOrder($query, $parts['order']);
            
            // Compila la parte LIMIT
            $this->parseLimit($query, $parts['limit']);
            
            // Ripristina la query originale
            $this->sql_string = $original_sql;
            
            return $query;
        } catch (\Exception $e) {
            // Ripristina la query originale in caso di eccezione
            $this->sql_string = $original_sql;
            throw $e;
        }
    }
    
    /**
     * Restituisce il numero di query valide trovate.
     * 
     * @return int Numero di query valide
     */
    public function getQueryCount(): int {
        return count($this->queries);
    }
    
    /**
     * Restituisce l'array delle query originali.
     * 
     * @return array Array di stringhe SQL
     */
    public function getQueries(): array {
        return $this->queries;
    }

    /**
     * Suddivide una stringa SQL in più query separate dai punti e virgola,
     * ignorando i separatori all'interno di stringhe virgolettate e commenti.
     * 
     * @param string $sql_string Stringa SQL da suddividere
     * @return array Array di query SQL valide
     */
    private function splitMultipleQueries($sql_string): array {
        $queries = [];
        $current_query = '';
        $length = strlen($sql_string);
        $in_single_quote = false;
        $in_double_quote = false;
        $in_line_comment = false;
        $in_block_comment = false;
        
        for ($i = 0; $i < $length; $i++) {
            $char = $sql_string[$i];
            $next_char = ($i < $length - 1) ? $sql_string[$i + 1] : '';
            
            // Gestione dei commenti su una singola linea (-- o #)
            if (!$in_single_quote && !$in_double_quote && !$in_block_comment && 
                (($char === '-' && $next_char === '-') || $char === '#')) {
                $in_line_comment = true;
                $current_query .= $char;
                if ($char === '-') {
                    $current_query .= $next_char;
                    $i++;
                }
                continue;
            }
            
            // Fine del commento su una singola linea
            if ($in_line_comment && ($char === "\n" || $char === "\r")) {
                $in_line_comment = false;
                $current_query .= $char;
                continue;
            }
            
            // Ignora i caratteri nei commenti su una singola linea
            if ($in_line_comment) {
                $current_query .= $char;
                continue;
            }
            
            // Gestione dei commenti multilinea (/* */)
            if (!$in_single_quote && !$in_double_quote && $char === '/' && $next_char === '*') {
                $in_block_comment = true;
                $current_query .= $char . $next_char;
                $i++;
                continue;
            }
            
            // Fine del commento multilinea
            if ($in_block_comment && $char === '*' && $next_char === '/') {
                $in_block_comment = false;
                $current_query .= $char . $next_char;
                $i++;
                continue;
            }
            
            // Ignora i caratteri nei commenti multilinea
            if ($in_block_comment) {
                $current_query .= $char;
                continue;
            }
            
            // Gestione delle stringhe tra virgolette singole
            if ($char === "'" && !$in_double_quote) {
                $in_single_quote = !$in_single_quote;
                $current_query .= $char;
                continue;
            }
            
            // Gestione delle stringhe tra virgolette doppie
            if ($char === '"' && !$in_single_quote) {
                $in_double_quote = !$in_double_quote;
                $current_query .= $char;
                continue;
            }
            
            // Gestione del carattere di escape nelle stringhe
            if (($in_single_quote || $in_double_quote) && $char === '\\' && $next_char !== '') {
                $current_query .= $char . $next_char;
                $i++;
                continue;
            }
            
            // Separatore di query (punto e virgola fuori da stringhe e commenti)
            if ($char === ';' && !$in_single_quote && !$in_double_quote) {
                $current_query = $this->cleanQuery($current_query);
                // Aggiungi la query solo se contiene qualcosa oltre a spazi/commenti
                if ($this->isValidQuery($current_query)) {
                    $queries[] = $current_query;
                }
                $current_query = '';
                continue;
            }
            
            // Aggiungi il carattere corrente alla query
            $current_query .= $char;
        }
        
        // Aggiungi l'ultima query se non è vuota
        $current_query = $this->cleanQuery($current_query);
        if ($this->isValidQuery($current_query)) {
            $queries[] = $current_query;
        }
        
        return $queries;
    }
    
    /**
     * Verifica se una query è valida (non vuota e non contiene solo commenti o spazi).
     * 
     * @param string $query Query da verificare
     * @return bool True se la query è valida, false altrimenti
     */
    private function isValidQuery($query): bool {
        $query = trim($query);
        if (empty($query)) {
            return false;
        }
        
        // Rimuovi i commenti su singola linea (-- o #)
        $query = preg_replace('/(--|\#).*?(\r\n|\n|$)/', '$2', $query);
        
        // Rimuovi i commenti multilinea (/* */)
        $query = preg_replace('/\/\*.*?\*\//s', '', $query);
        
        // Rimuovi gli spazi bianchi
        $query = trim($query);
        
        // Verifica se è rimasto qualcosa dopo la rimozione di commenti e spazi
        return !empty($query);
    }

    /**
     * Pulisce una query rimuovendo i commenti e gli spazi bianchi.
     * 
     * @param string $query Query da pulire
     * @return string Query pulita
     */
    private function cleanQuery($query): string {
        $query = trim($query);
        // Rimuovi i commenti su singola linea (-- o #)
        $query = preg_replace('/(--|\#).*?(\r\n|\n|$)/', '$2', $query);
        
        // Rimuovi i commenti multilinea (/* */)
        $query = preg_replace('/\/\*.*?\*\//s', '', $query);
        
        // Rimuovi gli spazi bianchi
        $query = trim($query);
        // rimuove eventuali spazi bianci, accapi tab e newline prima dell'inizio della query
        
        while(in_array(substr($query, 0, 1), array(' ', '\t', '\n', '\r')) && strlen($query) > 0) {
            $query = substr($query, 1);
        } 
        return $query;
    }

    /**
     * Estrae le diverse parti della query SQL, tenendo conto delle subquery.
     * Le subquery all'interno di ogni parte non verranno analizzate internamente.
     * 
     * @return array|string Array associativo contenente le diverse parti della query o stringa sql
     */
    private function extractParts(): array|string {
        $sql_string = $this->sql_string;
        $parts = [
            'select' => '',
            'from' => '',
            'where' => '',
            'group' => '',
            'having' => '',
            'order' => '',
            'limit' => ''
        ];
    
        // Identifica le sezioni della query analizzando manualmente i token
        $current_position = 0;
        $length = strlen($sql_string);
        $current_clause = null;
        $parenthesis_level = 0;
        $in_quote = false;
        $quote_char = '';
        $buffer = '';
        
        // Prima trova l'inizio della clausola SELECT
        if (preg_match('/^SELECT\s+/i', trim($sql_string), $matches)) {
            $current_position = strlen($matches[0]);
            $current_clause = 'select';
        } else {
            return $sql_string; // Non è una SELECT query
        }
        
        for ($i = $current_position; $i < $length; $i++) {
            $char = $sql_string[$i];
            $next_char = ($i < $length - 1) ? $sql_string[$i + 1] : '';
            
            // Gestione delle stringhe tra virgolette
            if (($char === "'" || $char === '"') && ($i == 0 || $sql_string[$i-1] !== '\\')) {
                if (!$in_quote) {
                    $in_quote = true;
                    $quote_char = $char;
                } elseif ($char === $quote_char) {
                    $in_quote = false;
                }
                $buffer .= $char;
                continue;
            }
            
            // Se siamo in una stringa tra virgolette, aggiungi semplicemente il carattere
            if ($in_quote) {
                $buffer .= $char;
                continue;
            }
            
            // Gestione delle parentesi per le subquery
            if ($char === '(') {
                $parenthesis_level++;
                $buffer .= $char;
                continue;
            }
            
            if ($char === ')') {
                $parenthesis_level--;
                $buffer .= $char;
                continue;
            }
            
            // Cambia la clausola corrente solo se non siamo in una subquery
            if ($parenthesis_level === 0) {
                // Trova l'inizio di una nuova clausola
                $remaining_sql = substr($sql_string, $i);
                
                if (preg_match('/^\s+FROM\s+/i', $remaining_sql, $matches) && $current_clause === 'select') {
                    $parts[$current_clause] = trim($buffer);
                    $buffer = '';
                    $i += strlen($matches[0]) - 1;
                    $current_clause = 'from';
                    continue;
                }
                
                if (preg_match('/^\s+WHERE\s+/i', $remaining_sql, $matches) && ($current_clause === 'from')) {
                    $parts[$current_clause] = trim($buffer);
                    $buffer = '';
                    $i += strlen($matches[0]) - 1;
                    $current_clause = 'where';
                    continue;
                }
                
                if (preg_match('/^\s+GROUP\s+BY\s+/i', $remaining_sql, $matches) && ($current_clause === 'from' || $current_clause === 'where')) {
                    $parts[$current_clause] = trim($buffer);
                    $buffer = '';
                    $i += strlen($matches[0]) - 1;
                    $current_clause = 'group';
                    continue;
                }
                
                if (preg_match('/^\s+HAVING\s+/i', $remaining_sql, $matches) && ($current_clause === 'from' || $current_clause === 'where' || $current_clause === 'group')) {
                    $parts[$current_clause] = trim($buffer);
                    $buffer = '';
                    $i += strlen($matches[0]) - 1;
                    $current_clause = 'having';
                    continue;
                }
                
                if (preg_match('/^\s+ORDER\s+BY\s+/i', $remaining_sql, $matches)) {
                    $parts[$current_clause] = trim($buffer);
                    $buffer = '';
                    $i += strlen($matches[0]) - 1;
                    $current_clause = 'order';
                    continue;
                }
                
                if (preg_match('/^\s+LIMIT\s+/i', $remaining_sql, $matches)) {
                    $parts[$current_clause] = trim($buffer);
                    $buffer = '';
                    $i += strlen($matches[0]) - 1;
                    $current_clause = 'limit';
                    continue;
                }
            }
            
            // Accumula il carattere nel buffer della clausola corrente
            $buffer .= $char;
        }
        
        // Aggiungi l'ultima parte bufferizzata
        if ($current_clause !== null && !empty($buffer)) {
            $parts[$current_clause] = trim($buffer);
        }
        
        return $parts;
    }

    /**
     * Estrae il nome delle tabelle dalla parte FROM.
     * 
     * @param string $from_part Parte FROM della query
     * @return array Array con il nome della tabella principale e le altre tabelle/JOIN
     */
    private function extractTables($from_part): array {
        $from_part = trim($from_part);
        $main_table = '';
        $other_tables = [];
        
        // Gestisce il caso di JOIN
        if (preg_match('/\b(INNER|LEFT|RIGHT|FULL|CROSS|NATURAL|STRAIGHT|JOIN)\b/i', $from_part)) {
            // Cerca la tabella principale prima di un JOIN
            if (preg_match('/^([^\s]+)(?:\s+AS\s+|\s+)?([^\s]+)?\s+(?:INNER|LEFT|RIGHT|FULL|CROSS|NATURAL|STRAIGHT|JOIN)/i', $from_part, $matches)) {
                $main_table = $this->removeBackticks($matches[1]);
                
                // Estrae i JOIN statements
                preg_match_all('/\b(INNER|LEFT|RIGHT|FULL|CROSS|NATURAL|STRAIGHT)?\s*JOIN\s+.*?(?=(?:\bINNER|LEFT|RIGHT|FULL|CROSS|NATURAL|STRAIGHT)?\s*JOIN\b|$)/is', $from_part, $join_matches);
                
                if (!empty($join_matches[0])) {
                    foreach ($join_matches[0] as $join_statement) {
                        $other_tables[] = trim($join_statement);
                    }
                }
            } else {
                // Non è riuscito a trovare la tabella principale, prova con un approccio semplice
                $parts = explode(' ', $from_part, 2);
                $main_table = $this->removeBackticks($parts[0]);
                
                if (isset($parts[1])) {
                    $other_tables[] = $parts[1];
                }
            }
        } else {
            // Caso senza JOIN, solo tabelle separate da virgole
            $tables = explode(',', $from_part);
            
            if (!empty($tables)) {
                // La prima è la tabella principale
                $main_table_parts = preg_split('/\s+AS\s+|\s+/i', trim($tables[0]), 2);
                $main_table = $this->removeBackticks($main_table_parts[0]);
                
                // Le altre sono tabelle aggiuntive
                for ($i = 1; $i < count($tables); $i++) {
                    $other_tables[] = trim($tables[$i]);
                }
            }
        }
        
        return [$main_table, $other_tables];
    }
    
    /**
     * Rimuove i backtick da un nome di tabella o campo.
     * 
     * @param string $name Nome di tabella o campo
     * @return string Nome pulito
     */
    private function removeBackticks($name): string {
        return trim($name, '` ');
    }
    
    /**
     * Compila la parte SELECT dell'oggetto Query.
     * 
     * @param Query $query Istanza di Query da compilare
     * @param string $select_part Parte SELECT della query
     */
    private function parseSelect($query, $select_part): void {
        // Suddivide i campi separati da virgola, tenendo conto delle funzioni
        $fields = $this->splitSelectFields($select_part);
        $query->select($fields);
    }
    
    /**
     * Suddivide i campi SELECT, tenendo conto delle funzioni e delle sottoquery.
     * Le subquery (tutto ciò che è tra parentesi tonde) vengono trattate come blocchi singoli
     * senza analizzarne il contenuto interno.
     * 
     * @param string $select_part Parte SELECT della query
     * @return array Array di campi SELECT
     */
    private function splitSelectFields($select_part): array {
        $fields = [];
        $current = '';
        $parenthesis_level = 0;
        
        for ($i = 0; $i < strlen($select_part); $i++) {
            $char = $select_part[$i];
            
            if ($char === '(') {
                $parenthesis_level++;
                $current .= $char;
            } elseif ($char === ')') {
                $parenthesis_level--;
                $current .= $char;
            } elseif ($char === ',' && $parenthesis_level === 0) {
                // Separa solo quando la virgola non è all'interno di parentesi
                $fields[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        // Aggiunge l'ultimo campo
        if (!empty($current)) {
            $fields[] = trim($current);
        }
        
        return $fields;
    }
    
    /**
     * Compila la parte FROM dell'oggetto Query.
     * 
     * @param Query $query Istanza di Query da compilare
     * @param array $other_tables Array di tabelle/JOIN aggiuntive
     */
    private function parseFrom($query, $other_tables): void {
        foreach ($other_tables as $table) {
            $query->from($table);
        }
    }
    
    /**
     * Compila la parte WHERE dell'oggetto Query.
     * Le subquery all'interno della clausola WHERE vengono trattate come blocchi singoli
     * senza analizzarne il contenuto interno.
     * 
     * @param Query $query Istanza di Query da compilare
     * @param string $where_part Parte WHERE della query
     */
    private function parseWhere($query, $where_part): void {
        if (empty($where_part)) {
            return;
        }
        
        // Trova i placeholder ? nella clausola WHERE, ma ignora quelli all'interno di subquery
        $param_count = 0;
        $in_subquery = 0;
        
        for ($i = 0; $i < strlen($where_part); $i++) {
            $char = $where_part[$i];
            
            if ($char === '(') {
                $in_subquery++;
            } elseif ($char === ')') {
                $in_subquery--;
            } elseif ($char === '?' && $in_subquery === 0) {
                // Conta solo i placeholder non all'interno di una subquery
                $param_count++;
            }
        }
        
        $params = array_fill(0, $param_count, null);
        
        // Per semplificare, consideriamo tutta la clausola WHERE come un'unica condizione
        $query->where($where_part, $params);
    }
    
    /**
     * Compila la parte HAVING dell'oggetto Query.
     * Poiché la classe Query originale potrebbe non supportare HAVING,
     * viene emesso un avviso e la condizione viene ignorata.
     * 
     * @param Query $query Istanza di Query da compilare
     * @param string $having_part Parte HAVING della query
     */
    private function parseHaving($query, $having_part): void {
        if (empty($having_part)) {
            return;
        }
      
        // Nota: se la classe Query è stata estesa con il supporto HAVING,
        // il codice sottostante può essere decommentato
        
        // Trova i placeholder ? nella clausola HAVING, ma ignora quelli all'interno di subquery
        $param_count = 0;
        $in_subquery = 0;
        
        for ($i = 0; $i < strlen($having_part); $i++) {
            $char = $having_part[$i];
            
            if ($char === '(') {
                $in_subquery++;
            } elseif ($char === ')') {
                $in_subquery--;
            } elseif ($char === '?' && $in_subquery === 0) {
                // Conta solo i placeholder non all'interno di una subquery
                $param_count++;
            }
        }
        
        $params = array_fill(0, $param_count, null);
        
        // Applica la condizione HAVING
        if (method_exists($query, 'having')) {
            $query->having($having_part, $params);
        }
        
    }
    
    /**
     * Compila la parte ORDER BY dell'oggetto Query.
     * 
     * @param Query $query Istanza di Query da compilare
     * @param string $order_part Parte ORDER BY della query
     */
    private function parseOrder($query, $order_part): void {
        if (empty($order_part)) {
            return;
        }
        
        $orders = $this->splitOrderFields($order_part);
        
        foreach ($orders as $order) {
            $order = trim($order);
            
            // Gestisce il caso in cui ci siano funzioni o espressioni
            if (preg_match('/^(.*?)\s+(ASC|DESC)$/i', $order, $matches)) {
                $field = trim($matches[1]);
                $direction = strtolower($matches[2]);
            } else {
                $field = $order;
                $direction = 'asc';
            }
            
            // Rimuove eventuali backtick
            $field = $this->removeBackticks($field);
            
            $query->order($field, $direction, false);
        }
    }
    
    /**
     * Suddivide i campi ORDER BY, tenendo conto delle funzioni e delle espressioni.
     * Le subquery (tutto ciò che è tra parentesi tonde) vengono trattate come blocchi singoli
     * senza analizzarne il contenuto interno.
     * 
     * @param string $order_part Parte ORDER BY della query
     * @return array Array di campi ORDER BY
     */
    private function splitOrderFields($order_part): array {
        $fields = [];
        $current = '';
        $parenthesis_level = 0;
        
        for ($i = 0; $i < strlen($order_part); $i++) {
            $char = $order_part[$i];
            
            if ($char === '(') {
                $parenthesis_level++;
                $current .= $char;
            } elseif ($char === ')') {
                $parenthesis_level--;
                $current .= $char;
            } elseif ($char === ',' && $parenthesis_level === 0) {
                // Separa solo quando la virgola non è all'interno di parentesi
                $fields[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        // Aggiunge l'ultimo campo
        if (!empty($current)) {
            $fields[] = trim($current);
        }
        
        return $fields;
    }
    
    /**
     * Compila la parte LIMIT dell'oggetto Query.
     * 
     * @param Query $query Istanza di Query da compilare
     * @param string $limit_part Parte LIMIT della query
     */
    private function parseLimit($query, $limit_part): void {
        if (empty($limit_part)) {
            return;
        }
        
        $limits = explode(',', $limit_part);
        
        if (count($limits) === 1) {
            // LIMIT x (equivale a LIMIT 0, x)
            $query->limit(0, (int) $limits[0]);
        } elseif (count($limits) === 2) {
            // LIMIT x, y
            $query->limit((int) $limits[0], (int) $limits[1]);
        }
    }
}