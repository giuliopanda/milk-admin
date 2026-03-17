<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/App/ExpressionParser/DotNotationParameterTest.php
 */

declare(strict_types=1);

namespace {
    if (!defined('MILK_TEST_CONTEXT')) {
        define('MILK_TEST_CONTEXT', true);
    }
    if (!defined('MILK_API_CONTEXT')) {
        define('MILK_API_CONTEXT', true);
    }

    $projectRoot = dirname(__DIR__, 4);
    require_once $projectRoot . '/public_html/milkadmin.php';
    require_once MILK_DIR . '/autoload.php';
}

namespace Tests\Unit\App\ExpressionParser {
    use App\Abstracts\AbstractModel;
    use App\Get;
    use App\MessagesHandler;
    use PHPUnit\Framework\TestCase;

    final class DotNotationParameterModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_dot_notation_parameter_tests')
                ->db('array')
                ->id('ID')
                ->array('user')->label('User')
                ->int('CHECK')->label('Check')
                    ->validateExpr('[user.id] > 0', 'User id must be > 0')
                ->int('ADULT_CHECK')->label('Adult Check')
                    ->validateExpr('[user.profile.age] >= 18', 'User must be adult')
                ->int('FIRST_TAG')->label('First Tag')
                    ->validateExpr('[user.tags.0] == "a"', 'First tag must be a');
        }
    }

    final class DotCampusModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_dot_campuses')
                ->db('array')
                ->id('ID_CAMPUS')
                ->string('NOME', 100)->label('Nome');
        }
    }

    final class DotBuildingModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_dot_buildings')
                ->db('array')
                ->id('ID_BUILDING')
                ->int('ID_CAMPUS')->label('Campus')
                    ->belongsTo('campus', DotCampusModel::class, 'ID_CAMPUS')
                ->string('NOME', 100)->label('Nome');
        }
    }

    final class DotAulaDettagliModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_dot_aule_dettagli')
                ->db('array')
                ->id('ID_DETT')
                ->string('AULA', 10)->label('Aula')
                    ->belongsTo('aula', DotAulaModel::class, 'AULA')
                ->string('PROIETTORE', 1)->label('Proiettore')
                ->string('NOTE', 255)->label('Note')->nullable();
        }
    }

    final class DotAulaModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_dot_aule')
                ->db('array')
                ->id('ID_AULA')
                    ->hasMany('responsabili', DotResponsabileModel::class, 'ID_AULA')
                ->int('ID_BUILDING')->label('Building')
                    ->belongsTo('building', DotBuildingModel::class, 'ID_BUILDING')
                ->string('AULA', 10)->label('Aula')
                    ->hasOne('dettagli', DotAulaDettagliModel::class, 'AULA')
                ->string('DISLOCAZIONE', 100)->label('Dislocazione')
                ->int('SUPERFICIE')->label('Superficie')
                ->int('CAPIENZA')->label('Capienza')
                ->string('PIANO', 5)->label('Piano')
                ->string('ATTIVA', 1)->label('Attiva');
        }
    }

    final class DotResponsabileModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_dot_aule_responsabili')
                ->db('array')
                ->id('ID_RESP')
                ->int('ID_AULA')->label('Aula')
                    ->belongsTo('aula', DotAulaModel::class, 'ID_AULA')
                ->string('NOME', 100)->label('Nome')
                ->string('EMAIL', 255)->label('Email');
        }
    }

    /**
     * @property DotAulaModel|null $aula
     * @property DotResponsabileModel|null $responsabile
     */
    final class DotPrenotazioneValidationModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_dot_prenotazioni')
                ->db('array')
                ->id('ID_PREN')
                ->int('ID_AULA')->label('Aula')
                    ->belongsTo('aula', DotAulaModel::class, 'ID_AULA')
                ->int('ID_RESP')->label('Responsabile')
                    ->belongsTo('responsabile', DotResponsabileModel::class, 'ID_RESP')
                ->int('CHECK_AULA_ATTIVA')->label('Check Aula Attiva')->nullable()
                    ->validateExpr('[aula.ATTIVA] == "S"', 'Aula must be active')
                ->int('CHECK_CAMPUS')->label('Check Campus')->nullable()
                    ->validateExpr('[aula.building.campus.NOME] == "Campus Nord"', 'Campus mismatch')
                ->int('CHECK_PROIETTORE')->label('Check Proiettore')->nullable()
                    ->validateExpr('[aula.dettagli.PROIETTORE] == "S"', 'Projector required')
                ->int('CHECK_FIRST_RESP_EMAIL')->label('Check First Resp Email')->nullable()
                    ->validateExpr('[aula.responsabili.0.EMAIL] != ""', 'First responsible must have an email')
                ->int('CHECK_RESP_MATCH')->label('Check Resp Match')->nullable()
                    ->validateExpr('[responsabile.ID_AULA] == [ID_AULA]', 'Responsible must match the booked room');
        }
    }

    final class DotNotationParameterTest extends TestCase
    {
        private const REL_TABLES = [
            'expr_dot_notation_parameter_tests',
            'expr_dot_campuses',
            'expr_dot_buildings',
            'expr_dot_aule',
            'expr_dot_aule_dettagli',
            'expr_dot_aule_responsabili',
            'expr_dot_prenotazioni',
        ];

        protected function setUp(): void
        {
            parent::setUp();
            MessagesHandler::reset();
            $this->seedArrayDbRelationshipsFixture();
        }

        protected function tearDown(): void
        {
            $this->dropArrayDbRelationshipsFixture();
            parent::tearDown();
        }

        private function seedArrayDbRelationshipsFixture(): void
        {
            $db = Get::arrayDb();

            foreach (self::REL_TABLES as $table) {
                try {
                    $db->dropTable($table);
                } catch (\Throwable) {
                    // ignore
                }
            }

            $userStdClass = new \stdClass();
            $userStdClass->id = 5;
            $userStdClass->profile = (object) ['age' => 20];
            $userStdClass->tags = ['a', 'b'];

            $db->addTable('expr_dot_notation_parameter_tests', [
                [
                    'ID' => 1,
                    'user' => $userStdClass,
                    'CHECK' => 1,
                    'ADULT_CHECK' => 1,
                    'FIRST_TAG' => 1,
                ],
                [
                    'ID' => 2,
                    'user' => [
                        'id' => 1,
                        'profile' => ['age' => 18],
                        'tags' => ['a'],
                    ],
                    'CHECK' => 1,
                    'ADULT_CHECK' => 1,
                    'FIRST_TAG' => 1,
                ],
                [
                    'ID' => 3,
                    'user' => (object) ['profile' => (object) ['age' => 20]],
                    'CHECK' => 1,
                    'ADULT_CHECK' => 1,
                    'FIRST_TAG' => 1,
                ],
            ], 'ID');

            $db->addTable('expr_dot_campuses', [
                ['ID_CAMPUS' => 1, 'NOME' => 'Campus Nord'],
                ['ID_CAMPUS' => 2, 'NOME' => 'Campus Sud'],
            ], 'ID_CAMPUS');

            $db->addTable('expr_dot_buildings', [
                ['ID_BUILDING' => 1, 'ID_CAMPUS' => 1, 'NOME' => 'Edificio A'],
                ['ID_BUILDING' => 2, 'ID_CAMPUS' => 1, 'NOME' => 'Edificio B'],
                ['ID_BUILDING' => 3, 'ID_CAMPUS' => 2, 'NOME' => 'Edificio C'],
            ], 'ID_BUILDING');

            $db->addTable('expr_dot_aule', [
                ['ID_AULA' => 1, 'ID_BUILDING' => 1, 'AULA' => 'A1', 'DISLOCAZIONE' => 'Piano Terra', 'SUPERFICIE' => 35, 'CAPIENZA' => 20, 'PIANO' => 'T', 'ATTIVA' => 'S'],
                ['ID_AULA' => 2, 'ID_BUILDING' => 1, 'AULA' => 'A2', 'DISLOCAZIONE' => 'Piano Terra', 'SUPERFICIE' => 28, 'CAPIENZA' => 16, 'PIANO' => 'T', 'ATTIVA' => 'S'],
                ['ID_AULA' => 3, 'ID_BUILDING' => 2, 'AULA' => 'B1', 'DISLOCAZIONE' => 'Primo Piano', 'SUPERFICIE' => 45, 'CAPIENZA' => 28, 'PIANO' => '1', 'ATTIVA' => 'S'],
                ['ID_AULA' => 4, 'ID_BUILDING' => 2, 'AULA' => 'B2', 'DISLOCAZIONE' => 'Primo Piano', 'SUPERFICIE' => 40, 'CAPIENZA' => 24, 'PIANO' => '1', 'ATTIVA' => 'N'],
                ['ID_AULA' => 8, 'ID_BUILDING' => 3, 'AULA' => 'D2', 'DISLOCAZIONE' => 'Terzo Piano', 'SUPERFICIE' => 65, 'CAPIENZA' => 44, 'PIANO' => '3', 'ATTIVA' => 'N'],
            ], 'ID_AULA');

            $db->addTable('expr_dot_aule_dettagli', [
                ['ID_DETT' => 1, 'AULA' => 'A1', 'PROIETTORE' => 'S', 'NOTE' => 'Aula con proiettore'],
                ['ID_DETT' => 2, 'AULA' => 'A2', 'PROIETTORE' => 'N', 'NOTE' => ''],
                // Aula 8 intentionally missing details (null relationship)
            ], 'ID_DETT');

            $db->addTable('expr_dot_aule_responsabili', [
                ['ID_RESP' => 1, 'ID_AULA' => 8, 'NOME' => 'Mario Rossi', 'EMAIL' => 'mario.rossi@example.test'],
                ['ID_RESP' => 2, 'ID_AULA' => 1, 'NOME' => 'Laura Bianchi', 'EMAIL' => 'laura.bianchi@example.test'],
                ['ID_RESP' => 3, 'ID_AULA' => 3, 'NOME' => 'Giuseppe Verdi', 'EMAIL' => 'giuseppe.verdi@example.test'],
                ['ID_RESP' => 4, 'ID_AULA' => 4, 'NOME' => 'Anna Neri', 'EMAIL' => 'anna.neri@example.test'],
                ['ID_RESP' => 8, 'ID_AULA' => 8, 'NOME' => 'Elisa Ricci', 'EMAIL' => 'elisa.ricci@example.test'],
            ], 'ID_RESP');

            $db->addTable('expr_dot_prenotazioni', [
                [
                    'ID_PREN' => 1,
                    'ID_AULA' => 1,
                    'ID_RESP' => 2,
                    'CHECK_AULA_ATTIVA' => 1,
                    'CHECK_CAMPUS' => 1,
                    'CHECK_PROIETTORE' => 1,
                    'CHECK_FIRST_RESP_EMAIL' => 1,
                    'CHECK_RESP_MATCH' => 1,
                ],
                [
                    'ID_PREN' => 2,
                    'ID_AULA' => 8,
                    'ID_RESP' => 1,
                    'CHECK_FIRST_RESP_EMAIL' => 1,
                ],
                [
                    'ID_PREN' => 3,
                    'ID_AULA' => 1,
                    'ID_RESP' => 2,
                    'CHECK_AULA_ATTIVA' => 1,
                ],
                [
                    'ID_PREN' => 4,
                    'ID_AULA' => 8,
                    'ID_RESP' => 1,
                    'CHECK_PROIETTORE' => 1,
                ],
                [
                    'ID_PREN' => 5,
                    'ID_AULA' => 8,
                    'ID_RESP' => 1,
                    'CHECK_CAMPUS' => 1,
                ],
            ], 'ID_PREN');
        }

        private function dropArrayDbRelationshipsFixture(): void
        {
            $db = Get::arrayDb();
            foreach (self::REL_TABLES as $table) {
                try {
                    $db->dropTable($table);
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        private function assertFieldError(string $field, string $expected): void
        {
            $errors = MessagesHandler::getErrors();
            $this->assertArrayHasKey($field, $errors);
            $this->assertSame($expected, $errors[$field]);
        }

        public function testValidateExprResolvesDotNotationOnStdClass(): void
        {
            $model = (new DotNotationParameterModel())
                ->query()
                ->where('ID = ?', [1])
                ->getRow();
            $this->assertInstanceOf(DotNotationParameterModel::class, $model);

            MessagesHandler::reset();
            $this->assertTrue($model->validate());
            $this->assertFalse(MessagesHandler::hasErrors());
        }

        public function testValidateExprResolvesDotNotationOnArray(): void
        {
            $model = (new DotNotationParameterModel())
                ->query()
                ->where('ID = ?', [2])
                ->getRow();
            $this->assertInstanceOf(DotNotationParameterModel::class, $model);
            
            MessagesHandler::reset();
            $this->assertTrue($model->validate());
            $this->assertFalse(MessagesHandler::hasErrors());
        }

        public function testMissingDotNotationPathFailsValidation(): void
        {
            $model = (new DotNotationParameterModel())
                ->query()
                ->where('ID = ?', [3])
                ->getRow();
            $this->assertInstanceOf(DotNotationParameterModel::class, $model);

            MessagesHandler::reset();
            $this->assertFalse($model->validate());

            $errors = MessagesHandler::getErrors();
            $this->assertSame('User id must be > 0', $errors['CHECK'] ?? '');
        }

        public function testValidateExprResolvesDotNotationAcrossBelongsToHasOneHasManyRelationships(): void
        {
            $booking = (new DotPrenotazioneValidationModel())
                ->query()
                ->where('ID_PREN = ?', [1])
                ->getRow();
            $this->assertInstanceOf(DotPrenotazioneValidationModel::class, $booking);

            // Preload root relationships so they exist as ExpressionParser parameters.
            // Deeper chains are lazy-loaded by AbstractModel->__get during dot traversal.
            $aula = $booking->aula;
            $responsabile = $booking->responsabile;
            $this->assertNotNull($aula);
            $this->assertNotNull($responsabile);

            MessagesHandler::reset();
            $this->assertTrue($booking->validate());
            $this->assertFalse(MessagesHandler::hasErrors());
        }

        public function testDotNotationCanIndexHasManyRelationshipCollection(): void
        {
            $booking = (new DotPrenotazioneValidationModel())
                ->query()
                ->where('ID_PREN = ?', [2])
                ->getRow();
            $this->assertInstanceOf(DotPrenotazioneValidationModel::class, $booking);

            $aula = $booking->aula;
            $responsabile = $booking->responsabile;
            $this->assertNotNull($aula);
            $this->assertNotNull($responsabile);

            MessagesHandler::reset();
            $this->assertTrue($booking->validate());
            $this->assertFalse(MessagesHandler::hasErrors());
        }

        public function testMissingRelationshipRootInParametersFailsValidation(): void
        {
            $booking = (new DotPrenotazioneValidationModel())
                ->query()
                ->where('ID_PREN = ?', [3])
                ->getRow();
            $this->assertInstanceOf(DotPrenotazioneValidationModel::class, $booking);

            // DO NOT preload $booking->aula → ExpressionParser cannot resolve [aula.*]
            MessagesHandler::reset();
            $this->assertFalse($booking->validate());
            $this->assertFieldError('CHECK_AULA_ATTIVA', 'Aula must be active');
        }

        public function testHasOneNullTraversalFailsValidationWithMessage(): void
        {
            $booking = (new DotPrenotazioneValidationModel())
                ->query()
                ->where('ID_PREN = ?', [4])
                ->getRow();
            $this->assertInstanceOf(DotPrenotazioneValidationModel::class, $booking);

            $aula = $booking->aula;
            $responsabile = $booking->responsabile;
            $this->assertNotNull($aula);
            $this->assertNotNull($responsabile);

            MessagesHandler::reset();
            $this->assertFalse($booking->validate());
            $this->assertFieldError('CHECK_PROIETTORE', 'Projector required');
        }

        public function testBelongsToChainCanFailValidationWhenDataMismatch(): void
        {
            $booking = (new DotPrenotazioneValidationModel())
                ->query()
                ->where('ID_PREN = ?', [5])
                ->getRow();
            $this->assertInstanceOf(DotPrenotazioneValidationModel::class, $booking);

            $aula = $booking->aula;
            $responsabile = $booking->responsabile;
            $this->assertNotNull($aula);
            $this->assertNotNull($responsabile);

            MessagesHandler::reset();
            $this->assertFalse($booking->validate());
            $this->assertFieldError('CHECK_CAMPUS', 'Campus mismatch');
        }
    }
}
