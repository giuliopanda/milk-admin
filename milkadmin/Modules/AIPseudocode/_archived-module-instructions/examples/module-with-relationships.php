<?php
// Example: Module with Multiple Models and Relationships
// This example shows the CORRECT way to create a module with additional models and relationships

// ============================================
// LessonsModule.php
// ============================================
namespace Local\Modules\Lessons;

use App\Abstracts\AbstractModule;

class LessonsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('lessons')
             ->title('Corsi di Laurea')
             ->menu('Corsi', '', 'bi bi-mortarboard-fill', 10)
             ->access('public')
             // ✅ CORRECT: Register additional models in the chain
             ->addModels([
                 'Enrollment' => LessonsEnrollmentModel::class,
                 'Category' => LessonsCategoryModel::class
             ])
             ->version(251205);
    }

    // ❌ WRONG: Don't create a separate addModels() method
    // protected function addModels(): array {
    //     return ['Enrollment' => LessonsEnrollmentModel::class];
    // }

    public function bootstrap()
    {
        // Bootstrap code
    }
}

// ============================================
// LessonsModel.php (Main Model)
// ============================================
namespace Local\Modules\Lessons;

use App\Abstracts\AbstractModel;

class LessonsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__lessons')
             ->id()
             ->title('nome_corso', 255)->required()->label('Nome Corso')
             ->text('descrizione')->formType('editor')->label('Descrizione')
             ->string('codice_corso', 50)->unique()->required()->label('Codice Corso')
             // Optional: Define reverse relationship
             ->hasMany('enrollments', LessonsEnrollmentModel::class, 'id_corso');

        // Now you can use: $lesson->enrollments to get all enrollments
    }
}

// ============================================
// LessonsEnrollmentModel.php (Related Model)
// ============================================
namespace Local\Modules\Lessons;

use App\Abstracts\AbstractModel;

class LessonsEnrollmentModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__lessons_enrollments')
             ->id()
             // ✅ CORRECT: Chain belongsTo immediately after the foreign key field
             ->int('id_corso')->belongsTo('corso', LessonsModel::class)->required()->label('Corso')
             ->string('nome_utente', 255)->required()->label('Nome Utente')
             ->date('data_iscrizione')->required()->label('Data Iscrizione');

        // Now you can use: $enrollment->corso to get the related lesson
    }
}

// ❌ WRONG EXAMPLES - DO NOT USE
/*
// Wrong 1: belongsTo at the end of the chain
$rule->table('#__lessons_enrollments')
     ->id()
     ->int('id_corso')->required()->label('Corso')
     ->string('nome_utente', 255)->required()
     ->belongsTo('corso', LessonsModel::class);  // ❌ This applies to the wrong field!

// Wrong 2: Using foreign key as third parameter
->int('id_corso')->belongsTo('corso', LessonsModel::class, 'id_corso');  // ❌ Wrong parameter!

// Wrong 3: Separate addModels() method in Module
protected function addModels(): array {  // ❌ This won't work!
    return ['Enrollment' => LessonsEnrollmentModel::class];
}
*/

// ============================================
// Usage in Controller
// ============================================
/*
namespace Local\Modules\Lessons;

use App\Abstracts\AbstractController;

class LessonsController extends AbstractController
{
    public function viewEnrollments()
    {
        // Get additional model
        $enrollmentModel = $this->module->getAdditionalModel('Enrollment');

        // ✅ CORRECT: Use getAll() not all()
        $enrollments = $enrollmentModel->getAll();
        foreach ($enrollments as $enrollment) {
            echo $enrollment->corso->nome_corso;  // Access related lesson
        }

        // ✅ CORRECT: Use getById() not find()
        $lesson = $this->model->getById(1);
        if (!$lesson->isEmpty()) {
            foreach ($lesson->enrollments as $enrollment) {
                echo $enrollment->nome_utente;
            }
        }

        // ✅ CORRECT: Count with conditions
        $numEnrollments = $enrollmentModel->query()
            ->where('id_corso = ?', [1])
            ->getTotal();

        // ❌ WRONG examples:
        // $enrollments = $enrollmentModel->all();  // ❌ Method doesn't exist!
        // $lesson = $this->model->find(1);  // ❌ Method doesn't exist!
        // if (!$lesson) { ... }  // ❌ Wrong! Use isEmpty()
        // $count = $enrollmentModel->count(['id_corso' => 1]);  // ❌ Wrong syntax!
        // $count = $enrollmentModel->query()->where('id_corso = ?', [1])->total();  // ❌ Use getTotal()!
        // $count = $enrollmentModel->query()->whereIs('id_corso', 1)->getTotal();  // ❌ whereIs() doesn't exist!
        // $count = $enrollmentModel->query()->where(['id_corso' => 1])->getTotal();  // ❌ Array syntax is wrong!
    }
}
*/
