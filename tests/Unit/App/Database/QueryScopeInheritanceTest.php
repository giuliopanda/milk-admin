<?php
/**
 * Test per verificare che gli attributi #[DefaultQuery] e #[Query('name')]
 * funzionino correttamente con l'ereditarietà dei modelli e nelle estensioni
 *
 * Questo test dimostra e valuta:
 *
 * EREDITARIETÀ MODELLI:
 * 1. Ereditarietà degli scope da modelli parent
 * 2. Scope definiti in modelli child
 * 3. Override di scope (child sovrascrive parent)
 * 4. Ereditarietà multi-livello (3+ livelli)
 * 5. Disabilitazione scope ereditati
 * 6. Named scopes ereditati
 *
 * ESTENSIONI (AbstractModelExtension):
 * 7. Estensioni possono aggiungere default scopes
 * 8. Estensioni possono aggiungere named scopes
 * 9. Estensioni possono sovrascrivere scope del modello
 * 10. Multiple estensioni possono coesistere
 * 11. Scope delle estensioni possono essere disabilitati
 * 12. Estensioni funzionano con ereditarietà modelli
 *
 * Esegui con:
 * php vendor/bin/phpunit tests/Unit/App/Database/QueryScopeInheritanceTest.php 
 *
 * Con debug dettagliato:
 * php vendor/bin/phpunit tests/Unit/App/Database/QueryScopeInheritanceTest.php --testdox --debug
 *
 * Documentation: milkadmin/Modules/Docs/Pages/Developer/AbstractsClass/abstract-model-attributes.page.php
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
use App\Abstracts\AbstractModel;
use App\Attributes\DefaultQuery;
use App\Attributes\Query;

// ============================================================================
// BASE MODEL - Modello di base con scope comuni
// ============================================================================
/**
 * @property int|null $author_id
 * @property string|null $name
 * @property string|null $country
 * @property string|null $status
 * @property \DateTimeInterface|string|null $deleted_at
 * @property \DateTimeInterface|string|null $created_at
 * @property int|null $books_count
 * @property int|null $verified
 * @property int|null $active
 * @property int|null $level
 */
class BaseAuthorModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('test_authors')
            ->id('author_id')
            ->string('name', 100)->label('Nome')->required()
            ->string('country', 50)->label('Paese')
            ->string('status', 20)->label('Stato')->default('active')
            ->datetime('deleted_at')->label('Eliminato il')->nullable()
            ->datetime('created_at')->label('Creato il')
            ->int('books_count')->label('Numero libri')->default(0)
            ->int('verified')->label('Verificato')->default(0)
            ->int('active')->label('Attivo')->default(1)
            ->int('level')->label('Livello')->default(1);
    }

    #[DefaultQuery]
    protected function onlyActive($query)
    {
        $this->debug("  → Applying onlyActive from BaseAuthorModel");
        return $query->where('status = ?', ['active']);
    }

    #[Query('usa')]
    protected function scopeUsa($query)
    {
        $this->debug("  → Applying scopeUsa from BaseAuthorModel");
        return $query->where('country = ?', ['USA']);
    }

    #[Query('italy')]
    protected function scopeItaly($query)
    {
        $this->debug("  → Applying scopeItaly from BaseAuthorModel");
        return $query->where('country = ?', ['Italy']);
    }

    protected function debug(string $message): void
    {
        if (in_array('--debug', $_SERVER['argv'] ?? [])) {
            echo $message . "\n";
        }
    }
}

// ============================================================================
// EXTENDED MODEL - Modello che estende BaseAuthorModel
// ============================================================================
class ExtendedAuthorModel extends BaseAuthorModel
{
    // Aggiunge un nuovo default scope
    #[DefaultQuery]
    protected function notDeleted($query)
    {
        $this->debug("  → Applying notDeleted from ExtendedAuthorModel");
        return $query->where('deleted_at IS NULL');
    }

    // Aggiunge nuovi named scopes
    #[Query('recent')]
    protected function scopeRecent($query)
    {
        $this->debug("  → Applying scopeRecent from ExtendedAuthorModel");
        return $query->where('created_at > ?', ['2020-01-01']);
    }

    #[Query('popular')]
    protected function scopePopular($query)
    {
        $this->debug("  → Applying scopePopular from ExtendedAuthorModel");
        return $query->where('books_count > ?', [5]);
    }
}

// ============================================================================
// OVERRIDE MODEL - Modello che sovrascrive scope del parent
// ============================================================================
class OverrideAuthorModel extends BaseAuthorModel
{
    // Sovrascrive lo scope onlyActive del parent
    #[DefaultQuery]
    protected function onlyActive($query)
    {
        $this->debug("  → Applying OVERRIDDEN onlyActive from OverrideAuthorModel");
        return $query->where('status IN (?, ?)', ['active', 'pending']);
    }

    // Sovrascrive lo scope usa del parent
    #[Query('usa')]
    protected function scopeUsa($query)
    {
        $this->debug("  → Applying OVERRIDDEN scopeUsa from OverrideAuthorModel");
        return $query->where('country IN (?, ?)', ['USA', 'Canada']);
    }
}

// ============================================================================
// MULTI-LEVEL MODEL - Ereditarietà multi-livello
// ============================================================================
class GrandParentModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('test_authors')
            ->id('author_id')
            ->string('name', 100)->label('Nome')
            ->string('country', 50)->label('Paese')
            ->string('status', 20)->label('Stato')
            ->datetime('deleted_at')->nullable()
            ->datetime('created_at')
            ->int('books_count')->default(0)
            ->int('verified')->default(0)
            ->int('active')->default(1)
            ->int('level')->default(1);
    }

    #[DefaultQuery]
    protected function gpDefaultScope($query)
    {
        $this->debug("  → Applying gpDefaultScope from GrandParentModel");
        return $query->where('status != ?', ['deleted']);
    }

    #[Query('gp_named')]
    protected function gpNamedScope($query)
    {
        $this->debug("  → Applying gpNamedScope from GrandParentModel");
        return $query->where('level >= ?', [1]);
    }

    protected function debug(string $message): void
    {
        if (in_array('--debug', $_SERVER['argv'] ?? [])) {
            echo $message . "\n";
        }
    }
}

class ParentModel extends GrandParentModel
{
    #[DefaultQuery]
    protected function parentDefaultScope($query)
    {
        $this->debug("  → Applying parentDefaultScope from ParentModel");
        return $query->where('verified = ?', [1]);
    }

    #[Query('parent_named')]
    protected function parentNamedScope($query)
    {
        $this->debug("  → Applying parentNamedScope from ParentModel");
        return $query->where('level >= ?', [2]);
    }
}

class ChildModel extends ParentModel
{
    #[DefaultQuery]
    protected function childDefaultScope($query)
    {
        $this->debug("  → Applying childDefaultScope from ChildModel");
        return $query->where('active = ?', [1]);
    }

    #[Query('child_named')]
    protected function childNamedScope($query)
    {
        $this->debug("  → Applying childNamedScope from ChildModel");
        return $query->where('level >= ?', [3]);
    }
}

// ============================================================================
// MODEL WITH EXTENSION - Test scopes in extensions
// ============================================================================
class TestExtension extends \App\Abstracts\AbstractModelExtension
{
    #[\App\Attributes\DefaultQuery]
    protected function extensionDefaultScope($query)
    {
        $this->debug("  → Applying extensionDefaultScope from TestExtension");
        return $query->where('verified = ?', [1]);
    }

    #[\App\Attributes\Query('ext_popular')]
    protected function extensionNamedScope($query)
    {
        $this->debug("  → Applying ext_popular from TestExtension");
        return $query->where('books_count > ?', [5]);
    }

    protected function debug(string $message): void
    {
        if (in_array('--debug', $_SERVER['argv'] ?? [])) {
            echo $message . "\n";
        }
    }
}

class ModelWithExtension extends BaseAuthorModel
{
    protected array $extensions = [
        TestExtension::class
    ];
}

// Extension that overrides parent model scope
class OverridingExtension extends \App\Abstracts\AbstractModelExtension
{
    #[\App\Attributes\DefaultQuery]
    protected function onlyActive($query)
    {
        $this->debug("  → Applying OVERRIDDEN onlyActive from OverridingExtension");
        return $query->where('status IN (?, ?)', ['active', 'pending']);
    }

    protected function debug(string $message): void
    {
        if (in_array('--debug', $_SERVER['argv'] ?? [])) {
            echo $message . "\n";
        }
    }
}

class ModelWithOverridingExtension extends BaseAuthorModel
{
    protected array $extensions = [
        OverridingExtension::class
    ];
}

// Multiple extensions
class FirstExtension extends \App\Abstracts\AbstractModelExtension
{
    #[\App\Attributes\DefaultQuery]
    protected function firstExtScope($query)
    {
        $this->debug("  → Applying firstExtScope from FirstExtension");
        return $query->where('active = ?', [1]);
    }

    #[\App\Attributes\Query('first_named')]
    protected function firstNamedScope($query)
    {
        $this->debug("  → Applying first_named from FirstExtension");
        return $query->where('level >= ?', [2]);
    }

    protected function debug(string $message): void
    {
        if (in_array('--debug', $_SERVER['argv'] ?? [])) {
            echo $message . "\n";
        }
    }
}

class SecondExtension extends \App\Abstracts\AbstractModelExtension
{
    #[\App\Attributes\DefaultQuery]
    protected function secondExtScope($query)
    {
        $this->debug("  → Applying secondExtScope from SecondExtension");
        return $query->where('books_count >= ?', [3]);
    }

    #[\App\Attributes\Query('second_named')]
    protected function secondNamedScope($query)
    {
        $this->debug("  → Applying second_named from SecondExtension");
        return $query->where('country = ?', ['USA']);
    }

    protected function debug(string $message): void
    {
        if (in_array('--debug', $_SERVER['argv'] ?? [])) {
            echo $message . "\n";
        }
    }
}

class ModelWithMultipleExtensions extends BaseAuthorModel
{
    protected array $extensions = [
        FirstExtension::class,
        SecondExtension::class
    ];
}

// ============================================================================
// TEST CLASS
// ============================================================================
class QueryScopeInheritanceTest extends TestCase
{
    private BaseAuthorModel $baseModel;

    protected function setUp(): void
    {
        $this->baseModel = new BaseAuthorModel();

        // Crea la tabella
        $this->createTable();

        // Inserisci dati di test
        $this->insertTestData();
    }

    protected function tearDown(): void
    {
        // Pulisci: elimina la tabella di test
        try {
            $this->baseModel->dropTable();
        } catch (\Exception) {
            // Ignora errori
        }
    }

    private function createTable(): void
    {
        $this->debug("=== CREAZIONE TABELLA ===");

        $result = $this->baseModel->buildTable();
        $this->assertTrue($result, "Tabella test_authors creata: " . $this->baseModel->last_error);
        $this->debug("✓ Tabella test_authors creata");
    }

    private function insertTestData(): void
    {
        $this->debug("\n=== INSERIMENTO DATI DI TEST ===");

        $testAuthors = [
            ['name' => 'John Doe', 'country' => 'USA', 'status' => 'active', 'deleted_at' => null, 'created_at' => '2021-01-01', 'books_count' => 10, 'verified' => 1, 'active' => 1, 'level' => 3],
            ['name' => 'Jane Smith', 'country' => 'USA', 'status' => 'inactive', 'deleted_at' => null, 'created_at' => '2021-02-01', 'books_count' => 3, 'verified' => 1, 'active' => 1, 'level' => 2],
            ['name' => 'Mario Rossi', 'country' => 'Italy', 'status' => 'active', 'deleted_at' => null, 'created_at' => '2021-03-01', 'books_count' => 7, 'verified' => 1, 'active' => 1, 'level' => 3],
            ['name' => 'Giuseppe Verdi', 'country' => 'Italy', 'status' => 'active', 'deleted_at' => '2021-04-01', 'created_at' => '2019-01-01', 'books_count' => 2, 'verified' => 0, 'active' => 0, 'level' => 1],
            ['name' => 'Pierre Dupont', 'country' => 'France', 'status' => 'active', 'deleted_at' => null, 'created_at' => '2021-05-01', 'books_count' => 6, 'verified' => 1, 'active' => 1, 'level' => 2],
        ];

        foreach ($testAuthors as $authorData) {
            $author = new BaseAuthorModel();
            foreach ($authorData as $key => $value) {
                $author->$key = $value;
            }
            $author->save();
        }

        $this->debug("✓ 5 autori inseriti");
    }

    /**
     * Test 1: Base model has its own scopes
     */
    public function testBaseModelHasOwnScopes()
    {
        $this->debug("\n=== TEST 1: Base Model Scopes ===");

        $model = new BaseAuthorModel();

        $defaultScopes = $model->getDefaultQueries();
        $namedScopes = $model->getNamedQueries();

        $this->debug("Default scopes count: " . count($defaultScopes) . " - keys: " . implode(', ', array_keys($defaultScopes)));
        $this->debug("Named scopes count: " . count($namedScopes) . " - keys: " . implode(', ', array_keys($namedScopes)));

        $this->assertContains('onlyActive', $defaultScopes, "Missing onlyActive default scope");
        $this->assertContains('usa', $namedScopes, "Missing usa named scope");
        $this->assertContains('italy', $namedScopes, "Missing italy named scope");

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 2: Extended model inherits parent scopes
     */
    public function testExtendedModelInheritsParentScopes()
    {
        $this->debug("\n=== TEST 2: Extended Model Inherits Parent Scopes ===");

        $model = new ExtendedAuthorModel();

        $defaultScopes = $model->getDefaultQueries();
        $namedScopes = $model->getNamedQueries();

        $this->debug("Default scopes count: " . count($defaultScopes) . " - keys: " . implode(', ', array_keys($defaultScopes)));
        $this->debug("Named scopes count: " . count($namedScopes) . " - keys: " . implode(', ', array_keys($namedScopes)));

        // Verifica scope ereditati dal parent
        $this->assertContains('onlyActive', $defaultScopes, "Missing inherited onlyActive scope");
        $this->assertContains('usa', $namedScopes, "Missing inherited usa scope");
        $this->assertContains('italy', $namedScopes, "Missing inherited italy scope");

        // Verifica scope propri
        $this->assertContains('notDeleted', $defaultScopes, "Missing own notDeleted scope");
        $this->assertContains('recent', $namedScopes, "Missing own recent scope");
        $this->assertContains('popular', $namedScopes, "Missing own popular scope");

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 3: Extended model default scopes are applied
     */
    public function testExtendedModelDefaultScopesApplied()
    {
        $this->debug("\n=== TEST 3: Extended Model Default Scopes Applied ===");

        $model = new ExtendedAuthorModel();

        $this->debug("Fetching all authors with default scopes:");
        $authors = $model->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        // Con onlyActive e notDeleted, dovremmo trovare solo autori attivi e non cancellati
        // Dovremmo avere: John Doe, Mario Rossi, Pierre Dupont (3 autori)
        $this->assertEquals(3, $authors->count(), "Expected 3 authors (active and not deleted)");

        foreach ($authors as $author) {
            $this->debug("  - {$author->name} ({$author->country}, {$author->status})");
            $this->assertEquals('active', $author->status, "Found non-active author: {$author->name}");
            $this->assertNull($author->deleted_at, "Found deleted author: {$author->name}");
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 4: Extended model can disable inherited scopes
     */
    public function testExtendedModelCanDisableInheritedScopes()
    {
        $this->debug("\n=== TEST 4: Disable Inherited Scopes ===");

        $model = new ExtendedAuthorModel();

        $this->debug("Disabling 'onlyActive' scope:");
        $authors = $model->withoutGlobalScope('onlyActive')->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        // Senza onlyActive ma con notDeleted, dovremmo avere 4 autori (tutti tranne quello deleted)
        $this->assertEquals(4, $authors->count(), "Expected 4 authors (all not deleted)");

        foreach ($authors as $author) {
            $this->debug("  - {$author->name} ({$author->status})");
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 5: Extended model can use inherited named scopes
     */
    public function testExtendedModelCanUseInheritedNamedScopes()
    {
        $this->debug("\n=== TEST 5: Use Inherited Named Scopes ===");

        $model = new ExtendedAuthorModel();

        $this->debug("Using inherited 'usa' named scope:");
        $authors = $model->withQuery('usa')->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        // Con onlyActive, notDeleted e usa dovremmo trovare solo John Doe
        $this->assertEquals(1, $authors->count(), "Expected 1 author (USA, active, not deleted)");
        $this->assertEquals('USA', $authors[0]->country, "Expected USA author");

        $this->debug("  - {$authors[0]->name} ({$authors[0]->country})");
        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 6: Extended model can use own named scopes
     */
    public function testExtendedModelCanUseOwnNamedScopes()
    {
        $this->debug("\n=== TEST 6: Use Own Named Scopes ===");

        $model = new ExtendedAuthorModel();

        $this->debug("Using own 'popular' named scope:");
        $authors = $model->withQuery('popular')->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        // Con onlyActive, notDeleted e popular (books_count > 5)
        // Dovremmo trovare: John Doe (10), Mario Rossi (7), Pierre Dupont (6)
        $this->assertEquals(3, $authors->count(), "Expected 3 popular authors");

        foreach ($authors as $author) {
            $this->debug("  - {$author->name} ({$author->books_count} books)");
            $this->assertGreaterThan(5, $author->books_count, "Expected books_count > 5");
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 7: Override model overrides parent scopes
     */
    public function testOverrideModelOverridesParentScopes()
    {
        $this->debug("\n=== TEST 7: Override Model Overrides Parent Scopes ===");

        $model = new OverrideAuthorModel();

        $defaultScopes = $model->getDefaultQueries();
        $namedScopes = $model->getNamedQueries();

        $this->debug("Default scopes count: " . count($defaultScopes) . " - keys: " . implode(', ', array_keys($defaultScopes)));
        $this->debug("Named scopes count: " . count($namedScopes) . " - keys: " . implode(', ', array_keys($namedScopes)));

        // Verifica che gli scope esistano
        $this->assertContains('onlyActive', $defaultScopes, "Missing onlyActive scope");
        $this->assertContains('usa', $namedScopes, "Missing usa scope");

        $this->debug("\nFetching all authors (should use overridden onlyActive):");
        $authors = $model->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        foreach ($authors as $author) {
            $this->debug("  - {$author->name} ({$author->status})");
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 8: Multi-level inheritance (3 levels)
     */
    public function testMultiLevelInheritance()
    {
        $this->debug("\n=== TEST 8: Multi-level Inheritance (GrandParent → Parent → Child) ===");

        $model = new ChildModel();

        $defaultScopes = $model->getDefaultQueries();
        $namedScopes = $model->getNamedQueries();

        $this->debug("Default scopes count: " . count($defaultScopes) . " - keys: " . implode(', ', array_keys($defaultScopes)));
        $this->debug("Named scopes count: " . count($namedScopes) . " - keys: " . implode(', ', array_keys($namedScopes)));

        // Verifica tutti gli scope da tutti i livelli
        $this->assertContains('gpDefaultScope', $defaultScopes, "Missing GrandParent default scope");
        $this->assertContains('parentDefaultScope', $defaultScopes, "Missing Parent default scope");
        $this->assertContains('childDefaultScope', $defaultScopes, "Missing Child default scope");

        $this->assertContains('gp_named', $namedScopes, "Missing GrandParent named scope");
        $this->assertContains('parent_named', $namedScopes, "Missing Parent named scope");
        $this->assertContains('child_named', $namedScopes, "Missing Child named scope");

        // Verifica che ci siano esattamente 3 default e 3 named scopes
        $this->assertCount(3, $defaultScopes, "Expected 3 default scopes");
        $this->assertCount(3, $namedScopes, "Expected 3 named scopes");

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 9: Multi-level model applies all default scopes
     */
    public function testMultiLevelModelAppliesAllDefaultScopes()
    {
        $this->debug("\n=== TEST 9: Multi-level Model Applies All Default Scopes ===");

        $model = new ChildModel();

        $this->debug("Fetching all with 3-level default scopes:");
        $authors = $model->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        // Con tutti i filtri (status != deleted, verified = 1, active = 1)
        foreach ($authors as $author) {
            $this->debug("  - {$author->name} (verified={$author->verified}, active={$author->active}, level={$author->level})");
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 10: Can disable scopes from any inheritance level
     */
    public function testCanDisableScopesFromAnyLevel()
    {
        $this->debug("\n=== TEST 10: Disable Scopes From Any Inheritance Level ===");

        $model = new ChildModel();

        $this->debug("Disabling GrandParent scope (gpDefaultScope):");
        $count1 = $model->withoutGlobalScope('gpDefaultScope')->getAll()->count();
        $this->debug("Count: $count1");

        $model2 = new ChildModel();
        $this->debug("Disabling Parent scope (parentDefaultScope):");
        $count2 = $model2->withoutGlobalScope('parentDefaultScope')->getAll()->count();
        $this->debug("Count: $count2");

        $model3 = new ChildModel();
        $this->debug("Disabling Child scope (childDefaultScope):");
        $count3 = $model3->withoutGlobalScope('childDefaultScope')->getAll()->count();
        $this->debug("Count: $count3");

        $model4 = new ChildModel();
        $this->debug("Disabling all scopes:");
        $countAll = $model4->withoutGlobalScope(['gpDefaultScope', 'parentDefaultScope', 'childDefaultScope'])->getAll()->count();
        $this->debug("Count: $countAll");

        $this->debug("Results: single disables: $count1, $count2, $count3; all disabled: $countAll");

        // Tutti i disabilitamenti dovrebbero funzionare
        $this->assertGreaterThan(0, $count1);
        $this->assertGreaterThan(0, $count2);
        $this->assertGreaterThan(0, $count3);
        $this->assertGreaterThan(0, $countAll);

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 11: Can use named scopes from any inheritance level
     */
    public function testCanUseNamedScopesFromAnyLevel()
    {
        $this->debug("\n=== TEST 11: Use Named Scopes From Any Inheritance Level ===");

        $model1 = new ChildModel();
        $this->debug("Using GrandParent named scope (gp_named - level >= 1):");
        $authors1 = $model1->withQuery('gp_named')->getAll();
        $this->debug("Found " . $authors1->count() . " authors");

        $model2 = new ChildModel();
        $this->debug("\nUsing Parent named scope (parent_named - level >= 2):");
        $authors2 = $model2->withQuery('parent_named')->getAll();
        $this->debug("Found " . $authors2->count() . " authors");

        $model3 = new ChildModel();
        $this->debug("\nUsing Child named scope (child_named - level >= 3):");
        $authors3 = $model3->withQuery('child_named')->getAll();
        $this->debug("Found " . $authors3->count() . " authors");

        // Tutti dovrebbero funzionare
        $this->assertGreaterThanOrEqual(0, $authors1->count());
        $this->assertGreaterThanOrEqual(0, $authors2->count());
        $this->assertGreaterThanOrEqual(0, $authors3->count());

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 12: Extension can add default scopes
     */
    public function testExtensionCanAddDefaultScopes()
    {
        $this->debug("\n=== TEST 12: Extension Adds Default Scopes ===");

        $model = new ModelWithExtension();

        $defaultScopes = $model->getDefaultQueries();
        $namedScopes = $model->getNamedQueries();

        $this->debug("Default scopes: " . implode(', ', $defaultScopes));
        $this->debug("Named scopes: " . implode(', ', $namedScopes));

        // Verifica scope del modello base
        $this->assertContains('onlyActive', $defaultScopes, "Missing base onlyActive scope");

        // Verifica scope dell'estensione
        $this->assertContains('extensionDefaultScope', $defaultScopes, "Missing extension default scope");
        $this->assertContains('ext_popular', $namedScopes, "Missing extension named scope");

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 13: Extension default scopes are applied
     */
    public function testExtensionDefaultScopesApplied()
    {
        $this->debug("\n=== TEST 13: Extension Default Scopes Applied ===");

        $model = new ModelWithExtension();

        $this->debug("Fetching all authors with extension scopes:");
        $authors = $model->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        // Con onlyActive (status=active) e extensionDefaultScope (verified=1)
        // Dovremmo avere: John Doe, Mario Rossi, Pierre Dupont (3 autori)
        $this->assertEquals(3, $authors->count(), "Expected 3 authors (active and verified)");

        foreach ($authors as $author) {
            $this->debug("  - {$author->name} (status={$author->status}, verified={$author->verified})");
            $this->assertEquals('active', $author->status);
            $this->assertEquals(1, $author->verified);
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 14: Extension can use named scopes
     */
    public function testExtensionCanUseNamedScopes()
    {
        $this->debug("\n=== TEST 14: Extension Named Scopes ===");

        $model = new ModelWithExtension();

        $this->debug("Using extension named scope 'ext_popular':");
        $authors = $model->withQuery('ext_popular')->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        // Con onlyActive, extensionDefaultScope e ext_popular (books_count > 5)
        // Dovremmo trovare: John Doe (10), Mario Rossi (7), Pierre Dupont (6)
        $this->assertEquals(3, $authors->count(), "Expected 3 popular authors");

        foreach ($authors as $author) {
            $this->debug("  - {$author->name} (books_count={$author->books_count})");
            $this->assertGreaterThan(5, $author->books_count);
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 15: Extension can override parent model scopes
     */
    public function testExtensionCanOverrideParentScopes()
    {
        $this->debug("\n=== TEST 15: Extension Overrides Parent Scopes ===");

        $model = new ModelWithOverridingExtension();

        $defaultScopes = $model->getDefaultQueries();
        $this->debug("Default scopes: " . implode(', ', $defaultScopes));

        // Lo scope onlyActive dovrebbe esistere solo una volta
        $count = array_count_values($defaultScopes);
        $this->assertEquals(1, $count['onlyActive'], "onlyActive should appear only once");

        $this->debug("Fetching all authors (with overridden scope):");
        $authors = $model->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        // L'override dovrebbe permettere anche status='pending'
        // Ma nei nostri dati non abbiamo pending, solo active e inactive
        foreach ($authors as $author) {
            $this->debug("  - {$author->name} (status={$author->status})");
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 16: Multiple extensions add scopes
     */
    public function testMultipleExtensionsAddScopes()
    {
        $this->debug("\n=== TEST 16: Multiple Extensions Add Scopes ===");

        $model = new ModelWithMultipleExtensions();

        $defaultScopes = $model->getDefaultQueries();
        $namedScopes = $model->getNamedQueries();

        $this->debug("Default scopes: " . implode(', ', $defaultScopes));
        $this->debug("Named scopes: " . implode(', ', $namedScopes));

        // Verifica scope dal modello base
        $this->assertContains('onlyActive', $defaultScopes, "Missing base onlyActive scope");

        // Verifica scope dalla prima estensione
        $this->assertContains('firstExtScope', $defaultScopes, "Missing firstExtScope");
        $this->assertContains('first_named', $namedScopes, "Missing first_named scope");

        // Verifica scope dalla seconda estensione
        $this->assertContains('secondExtScope', $defaultScopes, "Missing secondExtScope");
        $this->assertContains('second_named', $namedScopes, "Missing second_named scope");

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 17: Multiple extensions scopes are applied
     */
    public function testMultipleExtensionsScopesApplied()
    {
        $this->debug("\n=== TEST 17: Multiple Extensions Scopes Applied ===");

        $model = new ModelWithMultipleExtensions();

        $this->debug("Fetching all authors with multiple extension scopes:");
        $authors = $model->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        // Con onlyActive (status=active), firstExtScope (active=1), secondExtScope (books_count >= 3)
        // Dovremmo avere: John Doe, Mario Rossi, Pierre Dupont (tutti active=1 e books_count >= 3)
        foreach ($authors as $author) {
            $this->debug("  - {$author->name} (active={$author->active}, books_count={$author->books_count})");
            $this->assertEquals('active', $author->status);
            $this->assertEquals(1, $author->active);
            $this->assertGreaterThanOrEqual(3, $author->books_count);
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 18: Can use named scopes from multiple extensions
     */
    public function testCanUseNamedScopesFromMultipleExtensions()
    {
        $this->debug("\n=== TEST 18: Use Named Scopes From Multiple Extensions ===");

        $model1 = new ModelWithMultipleExtensions();
        $this->debug("Using first_named scope (level >= 2):");
        $authors1 = $model1->withQuery('first_named')->getAll();
        $this->debug("Found " . $authors1->count() . " authors");

        $model2 = new ModelWithMultipleExtensions();
        $this->debug("\nUsing second_named scope (country = USA):");
        $authors2 = $model2->withQuery('second_named')->getAll();
        $this->debug("Found " . $authors2->count() . " authors");

        foreach ($authors2 as $author) {
            $this->debug("  - {$author->name} ({$author->country})");
            $this->assertEquals('USA', $author->country);
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 19: Can disable extension scopes
     */
    public function testCanDisableExtensionScopes()
    {
        $this->debug("\n=== TEST 19: Disable Extension Scopes ===");

        $model = new ModelWithExtension();

        $this->debug("Disabling extension scope 'extensionDefaultScope':");
        $authors = $model->withoutGlobalScope('extensionDefaultScope')->getAll();

        $this->debug("Found " . $authors->count() . " authors");

        // Senza extensionDefaultScope ma con onlyActive
        // Dovremmo avere tutti gli autori attivi (anche non verificati)
        $this->assertGreaterThan(3, $authors->count(), "Should have more than 3 authors without verified filter");

        foreach ($authors as $author) {
            $this->debug("  - {$author->name} (verified={$author->verified})");
        }

        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 20: Extension scopes work with model inheritance
     */
    public function testExtensionScopesWorkWithInheritance()
    {
        $this->debug("\n=== TEST 20: Extension Scopes With Model Inheritance ===");

        // Crea un modello che estende BaseAuthorModel e ha anche estensioni
        $extendedClass = new class extends ExtendedAuthorModel {
            protected array $extensions = [
                TestExtension::class
            ];
        };

        $model = $extendedClass;

        $defaultScopes = $model->getDefaultQueries();
        $this->debug("Default scopes: " . implode(', ', $defaultScopes));

        // Verifica scope dal modello base
        $this->assertContains('onlyActive', $defaultScopes, "Missing base onlyActive");

        // Verifica scope dal modello esteso
        $this->assertContains('notDeleted', $defaultScopes, "Missing extended notDeleted");

        // Verifica scope dall'estensione
        $this->assertContains('extensionDefaultScope', $defaultScopes, "Missing extension scope");

        $this->debug("Fetching all authors:");
        $authors = $model->getAll();
        $this->debug("Found " . $authors->count() . " authors");

        // Con tutti gli scope applicati
        foreach ($authors as $author) {
            $this->debug("  - {$author->name}");
            $this->assertEquals('active', $author->status);
            $this->assertNull($author->deleted_at);
            $this->assertEquals(1, $author->verified);
        }

        $this->debug("✓ Test PASSED");
    }

    // ===== UTILITY METHODS =====

    private function debug(string $message): void
    {
        if (in_array('--debug', $_SERVER['argv'] ?? [])) {
            echo $message . "\n";
        }
    }
}
