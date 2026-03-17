<?php
/**
 * Test di esempio per buildTable()
 *
 * Questo test dimostra come:
 * 1. Creare una tabella usando buildTable()
 * 2. Inserire e leggere record con date e timezone
 * 3. Eliminare la tabella usando dropTable()
 *
 * Esegui con:
 * php vendor/bin/phpunit tests/Unit/App/Database/BuildTableExampleTest.php --testdox
 * Documentation: milkadmin/Modules/Docs/Pages/Developer/GettingStarted/getting-started-model.page.php
 */

// Bootstrap the framework
if (!defined('MILK_TEST_CONTEXT')) {
    define('MILK_TEST_CONTEXT', true);
}
if (!defined('MILK_API_CONTEXT')) {
    define('MILK_API_CONTEXT', true);
}

require_once dirname(__DIR__, 4) . '/public_html/milkadmin.php';
require_once MILK_DIR . '/autoload.php';

use PHPUnit\Framework\TestCase;
use App\{Config, Get};

// Carica il Model di test
require_once __DIR__ . '/Fixtures/BuildTableExampleModel.php';
use TestApp\BuildTableExampleModel;

class BuildTableExampleTest extends TestCase
{
    private $model;
    private string $previousLocale = 'en_US';

    protected function setUp(): void
    {
        // Crea l'istanza del model
        $this->model = new BuildTableExampleModel();

        // Abilita il timezone management
        Config::set('use_user_timezone', true);

        $this->previousLocale = Config::get('locale', 'en_US');
        Config::set('locale', 'it_IT');
    }

    protected function tearDown(): void
    {
        // Pulisci: elimina la tabella di test
        try {
            $this->model->dropTable();
        } catch (\Exception $e) {
            // Ignora errori
        }

        // Reset del timezone utente
        Get::setUserTimezone(null);

        Config::set('locale', $this->previousLocale);
    }

    /**
     * Test 1: Verifica che buildTable() crei la tabella correttamente
     *
     * Questo test dimostra:
     * - Come usare buildTable() per creare una tabella
     * - Come verificare che la tabella sia stata creata
     */
    public function testBuildTableCreatesTable()
    {
        // Usa buildTable() per creare la tabella
        $result = $this->model->buildTable();

        // Verifica che la creazione sia andata a buon fine
        $this->assertTrue($result, "buildTable() dovrebbe restituire true");
        $this->assertEquals('', $this->model->last_error,
            "Non dovrebbero esserci errori: " . $this->model->last_error);

        // Verifica che la tabella esista interrogandola
        $db = Get::db();
        $table_name = 'build_table_example';

        // Prova a fare una query sulla tabella
        try {
            $result = $db->query("SELECT * FROM {$table_name} LIMIT 1");
            $this->assertNotFalse($result, "La tabella esiste ed è interrogabile");
        } catch (\Exception $e) {
            $this->fail("La tabella non esiste o non è interrogabile: " . $e->getMessage());
        }
    }

    /**
     * Test 2: Verifica inserimento e lettura di un record con data
     *
     * Questo test dimostra:
     * - Come inserire un record usando il model
     * - Come il sistema gestisce le date in UTC
     * - Come leggere i record salvati
     */
    public function testInsertAndReadRecordWithTimezone()
    {
        // Crea la tabella
        $this->model->buildTable();

        // Imposta il timezone dell'utente a Europe/Rome
        Get::setUserTimezone('Europe/Rome');

        // Ottieni timestamp UTC corrente usando Get::dateTimeZone()
        // Questo metodo restituisce sempre l'ora in UTC
        $utcNow = Get::dateTimeZone();
        $utcString = $utcNow->format('Y-m-d H:i:s');

        // Salva il record
        $this->model->title = 'Test Record';
        $this->model->created_at = $utcString;
        $saveResult = $this->model->save();

        $this->assertTrue($saveResult, "Il salvataggio dovrebbe avere successo");

        // Leggi il record appena inserito
        $records = $this->model->getAll();

        $this->assertGreaterThan(0, $records->count(),
            "Dovrebbe esserci almeno un record");

        // Verifica che il record abbia i dati corretti
        $this->assertEquals('Test Record', $records->title);
        $this->assertNotNull($records->created_at,
            "La data created_at non dovrebbe essere null");
    }

    /**
     * Test 3: Verifica che dropTable() rimuova la tabella
     *
     * Questo test dimostra:
     * - Come usare dropTable() per eliminare una tabella
     * - Come verificare che la tabella sia stata eliminata
     */
    public function testDropTableRemovesTable()
    {
        // Crea la tabella
        $this->model->buildTable();

        // Rimuovi la tabella
        $result = $this->model->dropTable();

        $this->assertTrue($result, "dropTable() dovrebbe restituire true");

        // Verifica che la tabella non esista più
        $db = Get::db();
        $table_name = 'build_table_example';

        try {
            $db->query("SELECT * FROM {$table_name} LIMIT 1");
            $this->fail("La tabella dovrebbe essere stata eliminata");
        } catch (\Exception $e) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Test 4: Verifica la conversione del timezone nella formattazione
     *
     * Questo test dimostra:
     * - Come Get::formatDate() converte le date dal timezone UTC al timezone utente
     * - La differenza tra timezone diversi
     */
    public function testTimezoneConversionInFormatting()
    {
        // Data di test in UTC
        $utcDate = '2024-01-15 12:00:00';

        // Test con timezone Europe/Rome (UTC+1 in inverno)
        Get::setUserTimezone('Europe/Rome');
        $displayDateRome = Get::formatDate($utcDate, 'datetime', true);

        // In Europa/Roma a gennaio, UTC+1
        // 2024-01-15 12:00:00 UTC = 2024-01-15 13:00:00 Europe/Rome
        $this->assertStringContainsString('13:00', $displayDateRome,
            "La data deve essere convertita nel timezone Europe/Rome (UTC+1)");

        // Test con timezone America/New_York (UTC-5 in inverno)
        Get::setUserTimezone('America/New_York');
        $displayDateNY = Get::formatDate($utcDate, 'datetime', true);

        // In America/New_York a gennaio (EST, UTC-5)
        // 2024-01-15 12:00:00 UTC = 2024-01-15 07:00:00 America/New_York
        $this->assertStringContainsString('07:00', $displayDateNY,
            "La data deve essere convertita nel timezone America/New_York (UTC-5)");

        // Verifica che senza conversione timezone la data rimanga in UTC
        $displayDateNoTz = Get::formatDate($utcDate, 'datetime', false);
        $this->assertStringContainsString('12:00', $displayDateNoTz,
            "Senza conversione timezone, la data deve rimanere in UTC");
    }
}
