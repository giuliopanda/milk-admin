#!/usr/bin/env php
<?php
/**
 * Script runner per il test di caricamento dati hasMany
 *
 * Esegui con:
 * php tests/Unit/App/Database/run-has-many-loading.php
 *
 * Oppure usa PHPUnit direttamente:
 * php vendor/bin/phpunit tests/Unit/App/Database/HasManyLoadingTest.php --testdox
 * php vendor/bin/phpunit tests/Unit/App/Database/HasManyLoadingTest.php --testdox --debug
 */

echo "\n";
echo "============================================\n";
echo "  TEST Caricamento Dati hasMany\n";
echo "============================================\n\n";

// Aggiungi --debug automaticamente
$_SERVER['argv'][] = '--debug';

// Esegui PHPUnit
$command = 'php vendor/bin/phpunit tests/Unit/App/Database/HasManyLoadingTest.php --testdox --debug';
echo "Comando: $command\n\n";

passthru($command, $exitCode);

exit($exitCode);
