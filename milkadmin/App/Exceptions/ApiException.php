<?php

namespace App\Exceptions;

/**
 * Eccezione generica per errori API.
 * Usala quando qualcosa "non previsto" fallisce:
 * - modulo non trovato
 * - metodo mancante
 * - errore interno durante l'esecuzione dell'handler
 */
class ApiException extends \Exception {}
