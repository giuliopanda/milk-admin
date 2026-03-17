<?php

namespace App;

function _r($var): string
{
    return function_exists('\_r') ? (string) \_r($var) : (is_scalar($var) ? (string) $var : '');
}

function _rh($var): string
{
    return function_exists('\_rh') ? (string) \_rh($var) : (is_scalar($var) ? (string) $var : '');
}

function _rt($var, ...$args): string
{
    return function_exists('\_rt') ? (string) \_rt($var, ...$args) : (is_scalar($var) ? (string) $var : '');
}

function _raz($var): string
{
    return function_exists('\_raz') ? (string) \_raz($var) : (is_scalar($var) ? (string) $var : '');
}

function _absint($var): int
{
    return function_exists('\_absint') ? (int) \_absint($var) : abs((int) $var);
}

function _pt($var, ...$args): void
{
    if (function_exists('\_pt')) {
        \_pt($var, ...$args);
        return;
    }
    echo is_scalar($var) ? (string) $var : '';
}

namespace App\Abstracts;

function _absint($var): int
{
    return function_exists('\_absint') ? (int) \_absint($var) : abs((int) $var);
}

namespace App\Abstracts\Traits;

function _r($var): string
{
    return function_exists('\_r') ? (string) \_r($var) : (is_scalar($var) ? (string) $var : '');
}

function _raz($var): string
{
    return function_exists('\_raz') ? (string) \_raz($var) : (is_scalar($var) ? (string) $var : '');
}

namespace App\Database;

function _absint($var): int
{
    return function_exists('\_absint') ? (int) \_absint($var) : abs((int) $var);
}

namespace App\Modellist;

function _absint($var): int
{
    return function_exists('\_absint') ? (int) \_absint($var) : abs((int) $var);
}

function _raz($var): string
{
    return function_exists('\_raz') ? (string) \_raz($var) : (is_scalar($var) ? (string) $var : '');
}
