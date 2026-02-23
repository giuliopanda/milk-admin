<?php
namespace App\ExpressionParser;

/**
 * BuiltinFunctions - Implementazione di tutte le funzioni builtin del linguaggio
 */
class BuiltinFunctions
{
    /** @var string[] Lista delle funzioni disponibili */
    private array $registry = [
        'NOW', 'AGE', 'ROUND', 'ABS', 'IFNULL',
        'UPPER', 'LOWER', 'CONCAT', 'TRIM', 'ISEMPTY',
        'PRECISION', 'DATEONLY', 'TIMEADD', 'ADDMINUTES',
        'USERID',
        'COUNT', 'SUM', 'MIN', 'MAX',
        'FIND', 'CONTAINS', 'FIRST', 'LAST'
    ];

    /**
     * Restituisce la lista delle funzioni registrate
     * @return string[]
     */
    public function getRegistry(): array
    {
        return $this->registry;
    }

    /**
     * Registra una nuova funzione builtin (per estensibilità futura)
     * @param string $name Nome funzione (maiuscolo)
     */
    public function register(string $name): void
    {
        $name = strtoupper($name);
        if (!in_array($name, $this->registry)) {
            $this->registry[] = $name;
        }
    }

    /**
     * Esegue una funzione builtin
     * @param string $funcName Nome funzione
     * @param array $args Argomenti già valutati
     * @return mixed Risultato
     */
    public function execute(string $funcName, array $args): mixed
    {
        $funcName = strtoupper($funcName);

        if (!in_array($funcName, $this->registry)) {
            throw new \Exception("Funzione non riconosciuta: {$funcName}");
        }

        $methodName = 'func_' . $funcName;

        if (!method_exists($this, $methodName)) {
            throw new \Exception("Funzione {$funcName} non implementata");
        }

        return $this->$methodName($args);
    }

    // ==================== IMPLEMENTAZIONI FUNZIONI ====================

    /**
     * NOW() - Restituisce data e ora corrente
     */
    protected function func_NOW(array $args): \DateTime
    {
        if (!empty($args)) {
            throw new \Exception("NOW() non accetta argomenti");
        }
        return new \DateTime();
    }

    /**
     * AGE(birthdate) - Calcola l'età in anni da una data di nascita
     */
    protected function func_AGE(array $args): int
    {
        if (count($args) !== 1) {
            throw new \Exception("AGE() richiede esattamente 1 argomento (data di nascita)");
        }

        $birthdate = $args[0];
        if (!($birthdate instanceof \DateTime)) {
            throw new \Exception("AGE() richiede una data come argomento");
        }

        $now = new \DateTime();
        $age = $now->diff($birthdate);
        return (int)$age->y;
    }

    /**
     * ROUND(number, decimals=0) - Arrotonda un numero
     */
    protected function func_ROUND(array $args): float|int
    {
        if (count($args) < 1 || count($args) > 2) {
            throw new \Exception("ROUND() richiede 1 o 2 argomenti (numero, decimali opzionali)");
        }

        $number = $args[0];
        if (!is_numeric($number)) {
            throw new \Exception("ROUND() richiede un numero come primo argomento");
        }

        $decimals = isset($args[1]) ? (int)$args[1] : 0;

        $result = round((float)$number, $decimals);

        return $decimals === 0 ? (int)$result : $result;
    }

    /**
     * ABS(number) - Valore assoluto
     */
    protected function func_ABS(array $args): float|int
    {
        if (count($args) !== 1) {
            throw new \Exception("ABS() richiede esattamente 1 argomento");
        }

        $number = $args[0];
        if (!is_numeric($number)) {
            throw new \Exception("ABS() richiede un numero come argomento");
        }

        return abs($number);
    }

    /**
     * IFNULL(value, default) - Restituisce default se value è null
     */
    protected function func_IFNULL(array $args): mixed
    {
        if (count($args) !== 2) {
            throw new \Exception("IFNULL() richiede esattamente 2 argomenti (valore, default)");
        }

        return $args[0] === null ? $args[1] : $args[0];
    }

    /**
     * UPPER(string) - Converte in maiuscolo
     */
    protected function func_UPPER(array $args): string
    {
        if (count($args) !== 1) {
            throw new \Exception("UPPER() richiede esattamente 1 argomento");
        }

        $str = $args[0];
        if ($str === null) {
            return '';
        }

        return mb_strtoupper((string)$str, 'UTF-8');
    }

    /**
     * LOWER(string) - Converte in minuscolo
     */
    protected function func_LOWER(array $args): string
    {
        if (count($args) !== 1) {
            throw new \Exception("LOWER() richiede esattamente 1 argomento");
        }

        $str = $args[0];
        if ($str === null) {
            return '';
        }

        return mb_strtolower((string)$str, 'UTF-8');
    }

    /**
     * CONCAT(str1, str2, ...) - Concatena stringhe
     */
    protected function func_CONCAT(array $args): string
    {
        if (empty($args)) {
            return '';
        }

        $result = '';
        foreach ($args as $arg) {
            if ($arg === null) {
                continue;
            }
            if ($arg instanceof \DateTime) {
                $result .= $arg->format('Y-m-d H:i:s');
            } else {
                $result .= (string)$arg;
            }
        }

        return $result;
    }

    /**
     * TRIM(string) - Rimuove spazi iniziali e finali
     */
    protected function func_TRIM(array $args): string
    {
        if (count($args) !== 1) {
            throw new \Exception("TRIM() richiede esattamente 1 argomento");
        }

        $str = $args[0];
        if ($str === null) {
            return '';
        }

        return trim((string)$str);
    }

    /**
     * ISEMPTY(value) - Verifica se vuoto o null
     */
    protected function func_ISEMPTY(array $args): bool
    {
        if (count($args) !== 1) {
            throw new \Exception("ISEMPTY() richiede esattamente 1 argomento");
        }

        $value = $args[0];

        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return false;
    }

    /**
     * PRECISION(number, decimals) - Forza un numero ad avere N decimali
     */
    protected function func_PRECISION(array $args): float|int
    {
        if (count($args) !== 2) {
            throw new \Exception("PRECISION() richiede esattamente 2 argomenti (numero, decimali)");
        }

        $number = $args[0];
        if (!is_numeric($number)) {
            throw new \Exception("PRECISION() richiede un numero come primo argomento");
        }

        $decimals = (int)$args[1];

        if ($decimals === 0) {
            return (int)floor((float)$number);
        }

        return (float)number_format((float)$number, $decimals, '.', '');
    }

    /**
     * DATEONLY(datetime) - Rimuove ore, minuti, secondi da una data
     */
    protected function func_DATEONLY(array $args): \DateTime
    {
        if (count($args) !== 1) {
            throw new \Exception("DATEONLY() richiede esattamente 1 argomento");
        }

        $date = $args[0];
        if (!($date instanceof \DateTime)) {
            throw new \Exception("DATEONLY() richiede una data come argomento");
        }

        $result = clone $date;
        $result->setTime(0, 0, 0, 0);
        return $result;
    }

    /**
     * TIMEADD(time, minutes) - Somma minuti ad un orario (HH:MM o HH:MM:SS)
     */
    protected function func_TIMEADD(array $args): string
    {
        if (count($args) !== 2) {
            throw new \Exception("TIMEADD() richiede esattamente 2 argomenti (orario, minuti)");
        }

        [$hours, $minutes, $seconds, $hasSeconds] = $this->parseTimeValue($args[0]);

        $deltaMinutes = $args[1];
        if (!is_numeric($deltaMinutes)) {
            throw new \Exception("TIMEADD() richiede minuti numerici come secondo argomento");
        }

        $deltaMinutes = (int)round((float)$deltaMinutes);
        [$hours, $minutes, $seconds] = $this->addMinutesToTime($hours, $minutes, $seconds, $deltaMinutes);

        return $this->formatTimeValue($hours, $minutes, $seconds, $hasSeconds);
    }

    /**
     * ADDMINUTES(time, minutes) - Alias di TIMEADD
     */
    protected function func_ADDMINUTES(array $args): string
    {
        return $this->func_TIMEADD($args);
    }

    /**
     * USERID() - Restituisce l'id dell'utente loggato o 0
     */
    protected function func_USERID(array $args): int
    {
        if (!empty($args)) {
            throw new \Exception("USERID() non accetta argomenti");
        }

        try {
            $auth = \App\Get::make('Auth');
            if (!is_object($auth) || !method_exists($auth, 'getUser')) {
                return 0;
            }

            $user = $auth->getUser();
            if (!is_object($user)) {
                return 0;
            }

            return (int)($user->id ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    // ==================== HELPER INTERNI PER TIME ====================

    /**
     * Parse orario (HH:MM o HH:MM:SS) o DateTime
     * @return array [hours, minutes, seconds, hasSeconds]
     */
    private function parseTimeValue(mixed $value): array
    {
        if ($value instanceof \DateTime) {
            return [
                (int)$value->format('H'),
                (int)$value->format('i'),
                (int)$value->format('s'),
                true
            ];
        }

        if (!is_string($value)) {
            throw new \Exception("TIMEADD() richiede un orario come stringa o DateTime");
        }

        $trimmed = trim($value);
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $trimmed, $matches)) {
            throw new \Exception("TIMEADD() richiede orario nel formato HH:MM o HH:MM:SS");
        }

        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];
        $seconds = isset($matches[3]) ? (int)$matches[3] : 0;

        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59 || $seconds < 0 || $seconds > 59) {
            throw new \Exception("TIMEADD(): orario fuori range");
        }

        return [$hours, $minutes, $seconds, isset($matches[3])];
    }

    private function addMinutesToTime(int $hours, int $minutes, int $seconds, int $deltaMinutes): array
    {
        $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds + ($deltaMinutes * 60);
        $daySeconds = 24 * 3600;
        $normalized = (($totalSeconds % $daySeconds) + $daySeconds) % $daySeconds;

        $hours = intdiv($normalized, 3600);
        $minutes = intdiv($normalized % 3600, 60);
        $seconds = $normalized % 60;

        return [$hours, $minutes, $seconds];
    }

    private function formatTimeValue(int $hours, int $minutes, int $seconds, bool $hasSeconds): string
    {
        if ($hasSeconds) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    // ==================== ARRAY FUNCTIONS ====================

    /**
     * Helper: estrae un campo da un elemento (oggetto o array).
     * Supporta dot-notation nel field (es. "address.city").
     *
     * @param mixed $item L'elemento
     * @param string $field Il nome del campo (o path con punti)
     * @return mixed Il valore del campo, o null se non trovato
     */
    private function getField(mixed $item, string $field): mixed
    {
        if ($item === null) {
            return null;
        }

        $segments = explode('.', $field);
        $current = $item;

        foreach ($segments as $seg) {
            if ($current === null) {
                return null;
            }

            if (is_array($current)) {
                if (array_key_exists($seg, $current)) {
                    $current = $current[$seg];
                } elseif (ctype_digit($seg) && array_key_exists((int)$seg, $current)) {
                    $current = $current[(int)$seg];
                } else {
                    return null;
                }
            } elseif (is_object($current)) {
                if (isset($current->$seg) || property_exists($current, $seg)) {
                    $current = $current->$seg;
                } elseif ($current instanceof \ArrayAccess && $current->offsetExists($seg)) {
                    $current = $current->offsetGet($seg);
                } elseif (ctype_digit($seg) && $current instanceof \ArrayAccess && $current->offsetExists((int)$seg)) {
                    $current = $current->offsetGet((int)$seg);
                } else {
                    // Prova getter
                    $getter = 'get' . ucfirst($seg);
                    if (method_exists($current, $getter)) {
                        $current = $current->$getter();
                    } else {
                        return null;
                    }
                }
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Helper: valida che il primo argomento sia un array o Traversable
     */
    private function ensureArray(string $funcName, mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        // Supporta oggetti iterabili (es. Collection)
        if ($value instanceof \Traversable) {
            return iterator_to_array($value);
        }

        $type = gettype($value);
        throw new \Exception("{$funcName}() richiede un array come primo argomento, ricevuto {$type}");
    }

    /**
     * COUNT(array) - Restituisce la lunghezza dell'array
     * COUNT(array, "field") - Conta gli elementi dove field non è null
     */
    protected function func_COUNT(array $args): int
    {
        if (count($args) < 1 || count($args) > 2) {
            throw new \Exception("COUNT() richiede 1 o 2 argomenti (array, campo opzionale)");
        }

        $arr = $this->ensureArray('COUNT', $args[0]);

        if (count($args) === 1) {
            return count($arr);
        }

        $field = (string)$args[1];
        $count = 0;
        foreach ($arr as $item) {
            $val = $this->getField($item, $field);
            if ($val !== null) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * SUM(array, "field") - Somma i valori numerici di un campo
     */
    protected function func_SUM(array $args): int|float
    {
        if (count($args) !== 2) {
            throw new \Exception("SUM() richiede esattamente 2 argomenti (array, campo)");
        }

        $arr = $this->ensureArray('SUM', $args[0]);
        $field = (string)$args[1];

        $sum = 0;
        foreach ($arr as $item) {
            $val = $this->getField($item, $field);
            if ($val !== null && is_numeric($val)) {
                $sum += $val;
            }
        }
        return $sum;
    }

    /**
     * MIN(array, "field") - Restituisce il valore minimo di un campo numerico
     * Restituisce null se l'array è vuoto o non ci sono valori numerici
     */
    protected function func_MIN(array $args): int|float|null
    {
        if (count($args) !== 2) {
            throw new \Exception("MIN() richiede esattamente 2 argomenti (array, campo)");
        }

        $arr = $this->ensureArray('MIN', $args[0]);
        $field = (string)$args[1];

        $min = null;
        foreach ($arr as $item) {
            $val = $this->getField($item, $field);
            if ($val !== null && is_numeric($val)) {
                $num = $val + 0; // cast a numero
                if ($min === null || $num < $min) {
                    $min = $num;
                }
            }
        }
        return $min;
    }

    /**
     * MAX(array, "field") - Restituisce il valore massimo di un campo numerico
     * Restituisce null se l'array è vuoto o non ci sono valori numerici
     */
    protected function func_MAX(array $args): int|float|null
    {
        if (count($args) !== 2) {
            throw new \Exception("MAX() richiede esattamente 2 argomenti (array, campo)");
        }

        $arr = $this->ensureArray('MAX', $args[0]);
        $field = (string)$args[1];

        $max = null;
        foreach ($arr as $item) {
            $val = $this->getField($item, $field);
            if ($val !== null && is_numeric($val)) {
                $num = $val + 0;
                if ($max === null || $num > $max) {
                    $max = $num;
                }
            }
        }
        return $max;
    }

    /**
     * FIND(array, "field", value) - Trova il primo elemento dove field == value
     * Restituisce l'elemento trovato o null
     */
    protected function func_FIND(array $args): mixed
    {
        if (count($args) !== 3) {
            throw new \Exception("FIND() richiede esattamente 3 argomenti (array, campo, valore)");
        }

        $arr = $this->ensureArray('FIND', $args[0]);
        $field = (string)$args[1];
        $searchValue = $args[2];

        foreach ($arr as $item) {
            $val = $this->getField($item, $field);
            // Confronto strict
            if ($val === $searchValue) {
                return $item;
            }
            // Confronto loose via stringa (per compatibilità JS: "2" == 2)
            if ($val !== null && $searchValue !== null &&
                (string)$val === (string)$searchValue) {
                return $item;
            }
        }
        return null;
    }

    /**
     * CONTAINS(array, "field", value) - Verifica se esiste un elemento dove field == value
     * Restituisce true/false
     */
    protected function func_CONTAINS(array $args): bool
    {
        if (count($args) !== 3) {
            throw new \Exception("CONTAINS() richiede esattamente 3 argomenti (array, campo, valore)");
        }

        return $this->func_FIND($args) !== null;
    }

    /**
     * FIRST(array) - Restituisce il primo elemento
     * FIRST(array, "field") - Restituisce il campo del primo elemento
     * FIRST(array, "field", default) - Come sopra, con valore default se array vuoto
     */
    protected function func_FIRST(array $args): mixed
    {
        if (count($args) < 1 || count($args) > 3) {
            throw new \Exception("FIRST() richiede da 1 a 3 argomenti (array, campo opzionale, default opzionale)");
        }

        $arr = $this->ensureArray('FIRST', $args[0]);
        $field = count($args) >= 2 ? (string)$args[1] : null;
        $defaultVal = $args[2] ?? null;

        if (empty($arr)) {
            return $defaultVal;
        }

        $item = reset($arr); // primo elemento

        if ($field === null) {
            return $item;
        }

        $val = $this->getField($item, $field);
        return $val === null ? $defaultVal : $val;
    }

    /**
     * LAST(array) - Restituisce l'ultimo elemento
     * LAST(array, "field") - Restituisce il campo dell'ultimo elemento
     * LAST(array, "field", default) - Come sopra, con valore default se array vuoto
     */
    protected function func_LAST(array $args): mixed
    {
        if (count($args) < 1 || count($args) > 3) {
            throw new \Exception("LAST() richiede da 1 a 3 argomenti (array, campo opzionale, default opzionale)");
        }

        $arr = $this->ensureArray('LAST', $args[0]);
        $field = count($args) >= 2 ? (string)$args[1] : null;
        $defaultVal = $args[2] ?? null;

        if (empty($arr)) {
            return $defaultVal;
        }

        $item = end($arr); // ultimo elemento

        if ($field === null) {
            return $item;
        }

        $val = $this->getField($item, $field);
        return $val === null ? $defaultVal : $val;
    }
}
