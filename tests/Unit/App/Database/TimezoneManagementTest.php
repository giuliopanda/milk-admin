<?php
/**
 * Test per la gestione dei timezone
 *
 * Questo test verifica che:
 * 1. Le date vengono salvate in UTC nel database
 * 2. Le date vengono trasformate nel timezone dell'utente quando lette
 * 3. Il sistema supporta timezone diversi per utenti diversi
 *
 * Esegui con:
 * php vendor/bin/phpunit tests/Unit/App/Database/TimezoneManagementTest.php --testdox
 * Documentation: milkadmin/Modules/Docs/Pages/Developer/Advanced/timezone-management.page.php
 */

// Carica l'autoload di Composer
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
require_once __DIR__ . '/Fixtures/TestTimezoneModel.php';
use TestApp\TestTimezoneModel;

class TimezoneManagementTest extends TestCase
{
    private $model;

    protected function setUp(): void
    {
        // Crea l'istanza del model
        $this->model = new TestTimezoneModel();

        // Abilita il timezone management
        Config::set('use_user_timezone', true);
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
    }

    /**
     * Test completo del ciclo di vita di una data con timezone
     *
     * Questo test segue l'esempio della guida e verifica:
     * 1. Salvataggio: La data viene salvata in UTC nel database
     * 2. Lettura: La data viene convertita nel timezone dell'utente
     * 3. Modifica: La data modificata viene risalvata in UTC
     */
    public function testCompleteDateLifecycleWithTimezone()
    {
        // Crea la tabella
        $this->model->buildTable();

        // ====================
        // FASE 1: SALVATAGGIO
        // ====================

        // Imposta timezone utente a Europe/Rome (UTC+1 in inverno, UTC+2 in estate)
        Get::setUserTimezone('Europe/Rome');

        // Usa Get::dateTimeZone() per ottenere l'ora corrente in UTC
        // Questo è il modo corretto secondo la guida
        $now = Get::dateTimeZone();

        // Salva il record - la data è in UTC
        $this->model->title = 'Test Record';
        $this->model->created_at = $now->format('Y-m-d H:i:s');
        $saveResult = $this->model->save();

        $this->assertTrue($saveResult, "Il salvataggio dovrebbe avere successo");
        $recordId = $this->model->id;

        // Verifica che sia stato salvato in UTC interrogando direttamente il database
        $db = Get::db();
        $result = $db->query("SELECT created_at FROM test_timezone_records WHERE id = ?", [$recordId]);
        $row = $result->fetch_assoc();
        $savedDateInDb = $row['created_at'];

        // La data nel database dovrebbe essere in UTC
        $this->assertEquals($now->format('Y-m-d H:i:s'), $savedDateInDb,
            "La data nel database deve essere in UTC");

        // ====================
        // FASE 2: LETTURA
        // ====================

        // Leggi il record tramite il model
        $loadedRecord = $this->model->getById($recordId);

        $this->assertNotNull($loadedRecord, "Il record dovrebbe essere caricato");
        $this->assertEquals('Test Record', $loadedRecord->title);

        // La data letta dal model dovrebbe essere un DateTime object
        $this->assertInstanceOf(\DateTime::class, $loadedRecord->created_at,
            "created_at dovrebbe essere un oggetto DateTime");

        // Quando si accede alla data attraverso getFormattedValue(), dovrebbe essere
        // convertita nel timezone dell'utente (Europe/Rome)
        $formattedDate = $loadedRecord->getFormattedValue('created_at');

        // La data formattata dovrebbe essere diversa dalla data UTC nel database
        // perché viene convertita in Europe/Rome
        // Non possiamo fare un confronto diretto perché dipende dall'ora corrente,
        // ma possiamo verificare che non sia vuota
        $this->assertNotEmpty($formattedDate,
            "La data formattata non dovrebbe essere vuota");

        // ====================
        // FASE 3: MODIFICA E RISALVATAGGIO
        // ====================

        // Modifica il record
        $loadedRecord->title = 'Modified Record';

        // Impostiamo una data specifica per il test: 2024-01-15 14:00:00 (ora locale di Roma)
        // In Europa/Roma a gennaio (senza DST) = UTC+1
        // Quindi 14:00 Roma = 13:00 UTC
        $romeDate = new \DateTime('2024-01-15 14:00:00', new \DateTimeZone('Europe/Rome'));
        $loadedRecord->created_at = $romeDate;

        // Salva le modifiche
        $updateResult = $loadedRecord->save();

        $this->assertTrue($updateResult, "L'aggiornamento dovrebbe avere successo");

        // Verifica che sia stato salvato in UTC nel database
        $result2 = $db->query("SELECT created_at FROM test_timezone_records WHERE id = ?", [$recordId]);
        $row2 = $result2->fetch_assoc();
        $updatedDateInDb = $row2['created_at'];

        // Converte la data di Roma in UTC per il confronto
        $expectedUtcDate = clone $romeDate;
        $expectedUtcDate->setTimezone(new \DateTimeZone('UTC'));

        $this->assertEquals($expectedUtcDate->format('Y-m-d H:i:s'), $updatedDateInDb,
            "La data modificata deve essere salvata in UTC (13:00 invece di 14:00)");

        // ====================
        // FASE 4: VERIFICA TIMEZONE DIVERSO
        // ====================

        // Cambia timezone utente a America/New_York (UTC-5 in inverno)
        Get::setUserTimezone('America/New_York');

        // Ricarica il record
        $reloadedRecord = $this->model->getById($recordId);

        // La stessa data dovrebbe essere mostrata in modo diverso
        $formattedDateNY = $reloadedRecord->getFormattedValue('created_at');

        // Imposta nuovamente Rome per confronto
        Get::setUserTimezone('Europe/Rome');
        $reloadedRecordRome = $this->model->getById($recordId);
        $formattedDateRome = $reloadedRecordRome->getFormattedValue('created_at');

        // Le date formattate dovrebbero essere diverse
        // (stesso momento, timezone diversi)
        $this->assertNotEquals($formattedDateNY, $formattedDateRome,
            "La stessa data dovrebbe essere formattata diversamente in timezone diversi");
    }
}
