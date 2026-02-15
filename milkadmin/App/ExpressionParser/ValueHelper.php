<?php
namespace App\ExpressionParser;

/**
 * Trait ValueHelper - Utility per conversione, normalizzazione e formattazione valori
 */
trait ValueHelper
{
    /**
     * Converte un valore nel tipo appropriato
     */
    private function parseValue(mixed $value): mixed
    {
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return $value;
        }

        // Oggetti generici (stdClass, AbstractModel, ecc.) → passali così come sono
        if (is_object($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        // Prova a convertire in numero
        if (is_numeric($trimmed)) {
            return strpos($trimmed, '.') !== false ? (float)$trimmed : (int)$trimmed;
        }

        // Prova a convertire in data ISO (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            $date = \DateTime::createFromFormat('Y-m-d', $trimmed);
            if ($date !== false) {
                $date->setTime(0, 0, 0);
                return $date;
            }
        }

        // Prova a convertire in data europea (DD/MM/YYYY)
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $trimmed)) {
            $date = \DateTime::createFromFormat('d/m/Y', $trimmed);
            if ($date !== false) {
                $date->setTime(0, 0, 0);
                return $date;
            }
        }

        return $trimmed;
    }

    /**
     * Normalizza un valore per checkbox
     * @param mixed $value
     * @return bool
     */
    public function normalizeCheckboxValue(mixed $value): bool
    {
        if ($value === true) {
            return true;
        }
        if ($value === false || $value === null) {
            return false;
        }

        if (is_numeric($value)) {
            return (string)$value === '1';
        }

        if (is_string($value)) {
            $trimmed = strtolower(trim($value));
            if ($trimmed === '' || $trimmed === '0' || $trimmed === 'false') {
                return false;
            }
            if ($trimmed === '1' || $trimmed === 'true' || $trimmed === 'on' || $trimmed === 'yes') {
                return true;
            }
            return $trimmed !== '';
        }

        return (bool)$value;
    }

    /**
     * Restituisce il valore coerente per checkbox (checked/unchecked)
     * @param mixed $value
     * @param mixed $checkedValue
     * @param mixed $uncheckedValue
     * @param bool $nullable
     * @param string|null $type
     * @return bool
     */
    public function formatCheckboxValue(
        mixed $value,
        mixed $checkedValue = null,
        mixed $uncheckedValue = null,
        bool $nullable = false,
        ?string $type = null
    ): bool {
        // NOTE: Allineato al comportamento JS (expression-parser.js):
        // per i checkbox il calc_expr deve produrre SEMPRE un boolean.
        // Eventuali mapping (es. S/N) vanno gestiti dal chiamante.
        return $this->normalizeCheckboxValue($value);
    }

    /**
     * Formatta il risultato per output leggibile
     * @param mixed $value Valore da formattare
     * @return string Valore formattato
     */
    public function formatResult(mixed $value): string
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        return (string)$value;
    }
}