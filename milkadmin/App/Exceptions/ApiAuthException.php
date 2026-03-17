<?php
namespace App\Exceptions;

/**
 * Eccezione dedicata a tutti gli errori di sicurezza:
 * - autenticazione mancante o token invalido
 * - permessi insufficienti
 * - azione non autorizzata
 */
class ApiAuthException extends ApiException {}
