<?php
namespace Modules\Docs\Pages;
/**
 * @title Managing Related Content with Extensions
 * @guide developer
 * @order 47
 * @tags Posts, Comments, Related Content, Relationships, hasMany, Extensions, Modular Architecture
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Managing Related Content with Extensions</h1>
    <p class="text-muted">Revision: 2025/12/21</p>
    <p>This guide demonstrates how to create a modular extension system for managing related content using the Extensions architecture. Extensions allow you to organize related functionality into separate, reusable components that extend different parts of your module.</p>

    <div class="alert alert-info">
        <strong>Prerequisite:</strong> This guide assumes familiarity with the fetch-based approach described in <a href="?page=docs&action=Developer/RelatedContent/managing-related-content-fetch">Managing Related Content with Fetch-Based Interface</a>.
    </div>

    <h2>1. What are Extensions?</h2>
    <p>Extensions are modular components that extend the functionality of Abstract classes (Model, Controller, Module, GetDataBuilder, etc.) in MilkAdmin. They allow you to:</p>
    <ul>
        <li>Organize related functionality into separate folders</li>
        <li>Keep your main module classes clean and focused</li>
        <li>Reuse common functionality across multiple modules</li>
        <li>Extend models, controllers, and builders with additional capabilities</li>
    </ul>

    <h2>2. Extension Structure</h2>
    <p>An extension lives in a dedicated folder and can contain multiple files, each extending a different abstract class:</p>

    <pre class="border p-2 text-bg-gray"><code>milkadmin/Modules/Recipe/Extensions/Comments/
├── Module.php              # Extends AbstractModuleExtension
├── Model.php               # Extends AbstractModelExtension
├── Controller.php          # Extends AbstractControllerExtension
├── GetDataBuilder.php      # Extends AbstractGetDataBuilderExtension
├── Service.php             # Helper class (not an extension)
└── CommentsModel.php       # Model class for comments table</code></pre>

    <h2>3. Abstract Extension Classes</h2>
    <p>MilkAdmin provides several abstract classes for creating extensions:</p>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Abstract Class</th>
                    <th>Purpose</th>
                    <th>Key Methods</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>AbstractModuleExtension</strong></td>
                    <td>Extends module configuration and lifecycle</td>
                    <td><code>configure()</code>, <code>bootstrap()</code>, <code>init()</code></td>
                </tr>
                <tr>
                    <td><strong>AbstractModelExtension</strong></td>
                    <td>Adds fields and behavior to models</td>
                    <td><code>configure()</code>, <code>onAttributeMethodsScanned()</code></td>
                </tr>
                <tr>
                    <td><strong>AbstractControllerExtension</strong></td>
                    <td>Adds controller actions</td>
                    <td><code>onInit()</code>, <code>onHookInit()</code>, <code>onHandleRoutes()</code></td>
                </tr>
                <tr>
                    <td><strong>AbstractGetDataBuilderExtension</strong></td>
                    <td>Extends TableBuilder/FormBuilder</td>
                    <td><code>configure()</code>, <code>beforeGetData()</code>, <code>afterGetData()</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>4. Creating the Comments Extension</h2>
    <p>We'll create a Comments extension for the Recipe module. This extension will manage comments using the same fetch-based approach, but organized in a modular structure.</p>

    <h3>Step 1: Create the Extension Folder</h3>
    <p>Create the folder structure:</p>
    <pre class="border p-2 text-bg-gray"><code>mkdir -p milkadmin/Modules/Recipe/Extensions/Comments</code></pre>

    <h3>Step 2: Create CommentsModel</h3>
    <p>Create <code>milkadmin/Modules/Recipe/Extensions/Comments/CommentsModel.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Recipe\Extensions\Comments;

use App\Abstracts\AbstractModel;
use App\{Hooks, Get};
use App\Attributes\{ToDisplayValue, ToDatabaseValue};

!defined('MILK_DIR') && die();

class CommentsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__recipes_comments')
            ->id()
            ->int('recipe_id')
                ->formType('hidden')
                ->hideFromList()
                ->label('Recipe ID')
            ->text('comment')
                ->label('Comment')
                ->required();
    }
}</code></pre>

    <p><strong>Key points:</strong></p>
    <ul>
        <li><strong>Namespace</strong>: <code>Modules\Recipe\Extensions\Comments</code> - follows the convention</li>
        <li><strong>Table</strong>: <code>#__recipes_comments</code> - hardcoded for this specific module</li>
        <li><strong>Foreign key</strong>: <code>recipe_id</code> - links to the recipes table</li>
        <li>This is a standard AbstractModel, not an extension class</li>
    </ul>

    <h3>Step 3: Create Module Extension</h3>
    <p>Create <code>milkadmin/Modules/Recipe/Extensions/Comments/Module.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Recipe\Extensions\Comments;

use App\Abstracts\{AbstractModuleExtension, ModuleRuleBuilder};

!defined('MILK_DIR') && die();

class Module extends AbstractModuleExtension
{
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        // Register CommentsModel as an additional model
        // This allows the update command to create/update the comments table
        $rule_builder->addModels(['comment' => CommentsModel::class]);
    }
}</code></pre>

    <p><strong>Understanding AbstractModuleExtension:</strong></p>
    <ul>
        <li>Extends <code>AbstractModuleExtension</code> - provides module extension capabilities</li>
        <li>Receives <code>ModuleRuleBuilder $rule_builder</code> in the configure() method</li>
        <li><code>configure()</code> is called during the module's configuration phase</li>
        <li><code>->addModels()</code> registers CommentsModel so the CLI update command creates its table</li>
        <li>The Module extension also makes Controller extensions available automatically</li>
    </ul>

    <h3>Step 4: Create Model Extension</h3>
    <p>Create <code>milkadmin/Modules/Recipe/Extensions/Comments/Model.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Recipe\Extensions\Comments;

use App\Abstracts\{AbstractModelExtension, RuleBuilder};
use App\{Get, Hooks};

!defined('MILK_DIR') && die();

class Model extends AbstractModelExtension
{
    protected $foreign_key = 'entity_id';
    protected $entity_label = 'Entity';
    protected $comment_field = 'comment';

    public function configure(RuleBuilder $rule_builder): void
    {
        // Add hasMany relationship to comments
        $rule_builder
            ->ChangeCurrentField($rule_builder->getPrimaryKey())
            ->hasMany('comments', CommentsModel::class, 'recipe_id');
    }
}</code></pre>

    <p><strong>Understanding AbstractModelExtension:</strong></p>
    <ul>
        <li>Extends <code>AbstractModelExtension</code> - provides model extension capabilities</li>
        <li>Receives <code>RuleBuilder $rule_builder</code> in the configure() method</li>
        <li><code>configure()</code> is called after the RecipeModel's configure() method</li>
        <li><code>->ChangeCurrentField($rule_builder->getPrimaryKey())</code> - positions on the ID field</li>
        <li><code>->hasMany('comments', CommentsModel::class, 'recipe_id')</code> - adds the relationship:
            <ul>
                <li><code>'comments'</code> - property name for accessing comments (<code>$recipe->comments</code>)</li>
                <li><code>CommentsModel::class</code> - the related model</li>
                <li><code>'recipe_id'</code> - foreign key in the comments table</li>
            </ul>
        </li>
        <li>The extension can also use <code>onAttributeMethodsScanned()</code> to register custom handlers</li>
    </ul>

    <h3>Step 5: Create Service Class</h3>
    <p>Create <code>milkadmin/Modules/Recipe/Extensions/Comments/Service.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Recipe\Extensions\Comments;

use Builders\{TableBuilder, FormBuilder, TitleBuilder, SearchBuilder};
use App\Response;
use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

class Service
{
    public static function getEntityRecord(AbstractModel $model): AbstractModel
    {
        $entity_id = $_POST['data']['entity_id'] ?? $_REQUEST['entity_id'] ?? 0;

        if ($entity_id == 0) {
            Response::json(['success' => false, 'msg' => 'Entity ID not provided']);
        }

        $entity = $model->getById($entity_id);
        if ($entity->isEmpty()) {
            Response::json(['success' => false, 'msg' => 'Entity not found']);
        }

        return $entity;
    }

    public static function getCommentsOffcanvasHtml(AbstractModel $entity): string
    {
        $primary_id = $entity->getPrimaryKey();
        $entity_id_value = $entity->$primary_id;

        $entity_name = $entity->name ?? $entity->title ?? $entity->label ?? "Entity #{$entity_id_value}";

        $title = TitleBuilder::create($entity_name)
            ->addButton(
                'New Comment',
                '?page=' . self::getPageFromRequest() . '&action=comment-edit&entity_id=' . $entity_id_value,
                'primary',
                '',
                'post'
            );

        $search = SearchBuilder::create('idTableComments')
            ->search('search')
            ->layout('full-width')
            ->placeholder('Type to search...');

        $table = self::getCommentsTable($entity);

        return $title->render() . '&lt;br&gt;' . $search->render() . '&lt;br&gt;' . $table->render();
    }

    public static function getCommentsTable(AbstractModel $entity): TableBuilder
    {
        $primary_id = $entity->getPrimaryKey();
        $entity_id_value = $entity->$primary_id;
        $page = self::getPageFromRequest();

        $commentsModel = new CommentsModel();

        return TableBuilder::create($commentsModel, 'idTableComments')
            ->activeFetch()
            ->setRequestAction('update-comment-table')
            ->where('recipe_id = ?', [$entity_id_value])
            ->field('comment')->truncate(100)
            ->field('created_by')
            ->field('created_at')
            ->field('updated_by')
            ->field('updated_at')
            ->addAction('edit', [
                'label' => 'Edit',
                'link' => '?page=' . $page . '&action=comment-edit&entity_id=' . $entity_id_value . '&id=%id%',
            ])
            ->addAction('delete', [
                'label' => 'Delete',
                'action' => [self::class, 'deleteComment'],
                'class' => 'link-action-danger',
                'confirm' => 'Are you sure you want to delete this comment?',
            ])
            ->customData('entity_id', $entity_id_value);
    }

    public static function getCommentForm(AbstractModel $entity): FormBuilder
    {
        $primary_id = $entity->getPrimaryKey();
        $entity_id_value = $entity->$primary_id;
        $page = self::getPageFromRequest();

        $commentsModel = new CommentsModel();

        return FormBuilder::create($commentsModel, $page)
            ->activeFetch()
            ->asModal()
            ->customData('entity_id', $entity_id_value)
            ->setTitle('New Comment', 'Edit Comment')
            ->dataListId('idTableComments')
            ->field('recipe_id')->value($entity_id_value)->readonly()
            ->field('comment')->required()
            ->setActions([
                'save' => [
                    'label' => 'Save',
                    'class' => 'btn btn-primary',
                    'action' => FormBuilder::saveAction()
                ]
            ]);
    }

    public static function deleteComment($record, $request): array
    {
        if ($record->delete($record->id)) {
            return ['success' => true, 'message' => 'Comment deleted successfully'];
        }
        return ['success' => false, 'message' => 'Delete failed'];
    }

    private static function getPageFromRequest(): string
    {
        return $_REQUEST['page'] ?? '';
    }
}</code></pre>

    <p><strong>Understanding the Service class:</strong></p>
    <ul>
        <li><strong>Not an extension</strong> - this is a regular helper class</li>
        <li>Contains all the business logic for building UI components</li>
        <li>Used by the Controller extension to generate responses</li>
        <li>Makes the code more organized and testable</li>
    </ul>

    <h3>Step 6: Create Controller Extension</h3>
    <p>Create <code>milkadmin/Modules/Recipe/Extensions/Comments/Controller.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Recipe\Extensions\Comments;

use App\Abstracts\AbstractControllerExtension;
use App\Attributes\{RequestAction, AccessLevel};
use App\Response;

!defined('MILK_DIR') && die();

class Controller extends AbstractControllerExtension
{
    #[RequestAction('comments')]
    public function viewComments()
    {
        $entity = $this->getEntityRecord();

        Response::json([
            'offcanvas_end' => [
                'title' => 'Comments',
                'body' => Service::getCommentsOffcanvasHtml($entity),
                'size' => 'lg'
            ]
        ]);
    }

    #[RequestAction('update-comment-table')]
    public function updateCommentTable()
    {
        $entity = $this->getEntityRecord();
        $tableBuilder = Service::getCommentsTable($entity);
        $response = $tableBuilder->getResponse();

        // Reload the main entity list table if it exists
        $response['list'] = [
            "id" => "idTable" . ucfirst($this->module->get()->getPage()),
            "action" => "reload"
        ];

        Response::json($response);
    }

    #[RequestAction('comment-edit')]
    public function commentEdit()
    {
        $entity = $this->getEntityRecord();
        $formBuilder = Service::getCommentForm($entity);
        Response::json($formBuilder->getResponse());
    }

    private function getEntityRecord()
    {
        $module = $this->module->get();
        $model = $module->getModel();

        return Service::getEntityRecord($model);
    }
}</code></pre>

    <p><strong>Understanding AbstractControllerExtension:</strong></p>
    <ul>
        <li>Extends <code>AbstractControllerExtension</code> - provides controller extension capabilities</li>
        <li>Methods use <code>#[RequestAction('action-name')]</code> attribute to register routes</li>
        <li><code>$this->module</code> - WeakReference to the parent module (use <code>->get()</code>)</li>
        <li>Actions are automatically registered and routed by the system</li>
        <li>The Controller extension is loaded when the Module extension is loaded</li>
    </ul>

    <h3>Step 7: Create GetDataBuilder Extension</h3>
    <p>Create <code>milkadmin/Modules/Recipe/Extensions/Comments/GetDataBuilder.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Recipe\Extensions\Comments;

use App\Abstracts\AbstractGetDataBuilderExtension;
use App\{Get, Permissions};

!defined('MILK_DIR') && die();

class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    public function configure(object $builder): void
    {
        $page = $builder->getPage();

        $builder->field('comments')
            ->label('Comments')
            ->fn(function ($row) use ($page) {
                $commentsCount = count($row->comments);
                return '&lt;a href="?page=' . $page . '&action=comments&entity_id=' . $row->id . '" data-fetch="post"&gt;'
                    . $commentsCount . ' &lt;i class="bi bi-chat-dots"&gt;&lt;/i&gt;&lt;/a&gt;';
            });
    }
}</code></pre>

    <p><strong>Understanding AbstractGetDataBuilderExtension:</strong></p>
    <ul>
        <li>Extends <code>AbstractGetDataBuilderExtension</code> - provides builder extension capabilities</li>
        <li>Receives the builder instance (<code>TableBuilder</code> or <code>FormBuilder</code>)</li>
        <li><code>configure()</code> is called during the builder's configuration phase</li>
        <li>Can add fields, modify existing fields, add actions, etc.</li>
        <li>Here we add a "comments" column that shows the count and links to the offcanvas</li>
    </ul>

    <h2>5. Loading Extensions in the Module</h2>
    <p><strong>IMPORTANT:</strong> Extensions must be explicitly loaded in every component where they're needed. The system does not automatically propagate extensions.</p>

    <h3>5.1 Load Extension in RecipeModel</h3>
    <p>Update <code>milkadmin/Modules/Recipe/RecipeModel.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Recipe;
use App\Abstracts\AbstractModel;

class RecipeModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__recipes')
            ->id()
            ->title('name')->index()
            ->text('ingredients')->formType('textarea')
            ->extensions(['Comments'])  // Load Model extension here
            ->select('difficulty', ['Easy', 'Medium', 'Hard']);
    }
}</code></pre>

    <p><strong>What happens:</strong></p>
    <ul>
        <li><code>->extensions(['Comments'])</code> loads <code>Modules\Recipe\Extensions\Comments\Model</code></li>
        <li>The Model extension's <code>configure()</code> method is called</li>
        <li>The hasMany relationship is added to RecipeModel</li>
        <li>Now <code>$recipe->comments</code> is available</li>
    </ul>

    <h3>5.2 Load Extension in RecipeModule</h3>
    <p>Update <code>milkadmin/Modules/Recipe/RecipeModule.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Recipe;
use App\Abstracts\AbstractModule;
use App\Attributes\{RequestAction};
use Builders\{TableBuilder, FormBuilder};
use App\Response;

class RecipeModule extends AbstractModule
{
    protected function configure($rule): void {
        $rule->page('recipes')
             ->title('My Recipes')
             ->menu('Recipes', '', 'bi bi-book', 10)
             ->access('public')
             ->extensions(['Comments'])  // Load Module extension here
             ->version(251221);
    }

    #[RequestAction('home')]
    public function recipesList() {
        $tableBuilder = TableBuilder::create($this->model, 'idTableRecipes')
            ->activeFetch()
            ->field('name')
                ->link('?page='.$this->page.'&action=edit&id=%id%')
            ->field('ingredients')->truncate(50)
            ->extensions(['Comments'])  // Load GetDataBuilder extension here
            ->field('difficulty')
            ->setDefaultActions();

        $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }

    #[RequestAction('edit')]
    public function recipeEdit() {
        $response = ['page' => $this->page, 'title' => $this->title];

        $response = array_merge($response, FormBuilder::create($this->model, $this->page)
            ->asOffcanvas()
            ->activeFetch()
            ->setTitle('New Recipe', 'Edit Recipe')
            ->dataListId('idTableRecipes')
            ->getResponse());

        Response::json($response);
    }
}</code></pre>

    <p><strong>What happens:</strong></p>
    <ul>
        <li><code>->extensions(['Comments'])</code> in <code>configure()</code>:
            <ul>
                <li>Loads <code>Modules\Recipe\Extensions\Comments\Module</code></li>
                <li>The Module extension registers CommentsModel</li>
                <li>Automatically loads the Controller extension</li>
                <li>Actions (comments, update-comment-table, comment-edit) become available</li>
            </ul>
        </li>
        <li><code>->extensions(['Comments'])</code> in <code>TableBuilder</code>:
            <ul>
                <li>Loads <code>Modules\Recipe\Extensions\Comments\GetDataBuilder</code></li>
                <li>Adds the "comments" column to the table</li>
            </ul>
        </li>
    </ul>

    <h2>6. Extension Loading Summary</h2>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Where to Load</th>
                    <th>Extension Class Loaded</th>
                    <th>Effect</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>RecipeModel</strong><br><code>->extensions(['Comments'])</code></td>
                    <td><code>Modules\Recipe\Extensions\Comments\Model</code></td>
                    <td>Adds hasMany relationship to RecipeModel</td>
                </tr>
                <tr>
                    <td><strong>RecipeModule</strong><br><code>->extensions(['Comments'])</code></td>
                    <td><code>Modules\Recipe\Extensions\Comments\Module</code><br>
                        <code>Modules\Recipe\Extensions\Comments\Controller</code></td>
                    <td>Registers CommentsModel<br>Adds controller actions</td>
                </tr>
                <tr>
                    <td><strong>TableBuilder</strong><br><code>->extensions(['Comments'])</code></td>
                    <td><code>Modules\Recipe\Extensions\Comments\GetDataBuilder</code></td>
                    <td>Adds comments column with count and link</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="alert alert-warning">
        <strong>Important:</strong> Each extension must be loaded explicitly in the appropriate location. Extensions are <strong>not automatically propagated</strong> from one component to another.
    </div>

    <h2>7. How Extensions are Resolved</h2>
    <p>When you call <code>->extensions(['Comments'])</code>, the system searches for the extension class in this order:</p>

    <ol>
        <li><strong>Module-specific extension</strong>: <code>Modules\Recipe\Extensions\Comments\{Type}</code>
            <ul>
                <li>Example: <code>Modules\Recipe\Extensions\Comments\Model</code></li>
            </ul>
        </li>
        <li><strong>Global extension</strong>: <code>Extensions\Comments\{Type}</code>
            <ul>
                <li>Example: <code>Extensions\Comments\Model</code></li>
            </ul>
        </li>
    </ol>

    <p>Where <code>{Type}</code> is one of: Model, Module, Controller, GetDataBuilder, FormBuilder, etc.</p>

    <h2>8. Complete Flow Diagram</h2>

    <pre class="border p-2 text-bg-light"><code>User clicks "Comments" link
  ↓
JavaScript: data-fetch="post" → makes fetch POST to ?page=recipes&action=comments&entity_id=X
  ↓
Router: finds action in Controller extension
  ↓
Controller::viewComments()
  ├─ Service::getEntityRecord() → validates entity_id
  ├─ Service::getCommentsOffcanvasHtml() → builds complete HTML
  └─ Response::json(['offcanvas_end' => [...]])
  ↓
JavaScript: opens offcanvas with HTML

User sorts/searches comments table
  ↓
TableBuilder (with ->setRequestAction('update-comment-table'))
  ↓
Controller::updateCommentTable()
  ├─ Service::getCommentsTable() → builds updated table
  ├─ Adds 'list' => ['id' => 'idTableRecipes', 'action' => 'reload']
  └─ Response::json()
  ↓
JavaScript: updates table + reloads main list

User clicks "Edit" comment
  ↓
Controller::commentEdit()
  ├─ Service::getCommentForm() → builds form with ->asModal()
  └─ Response::json()
  ↓
JavaScript: opens modal

User saves form
  ↓
FormBuilder processes save
  ├─ Closes modal
  ├─ Reloads idTableComments (via ->dataListId())
  └─ Which triggers update-comment-table
      └─ Which reloads idTableRecipes
  ↓
Result: modal closed, both tables updated</code></pre>

    <h2>9. Global Reusable Extensions</h2>

    <p>MilkAdmin includes a generic Comments extension in <code>milkadmin/Extensions/Comments/</code> that can be used by any module without creating module-specific extension files.</p>

    <h3>Key Differences from Module-Specific Extensions</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Aspect</th>
                    <th>Module-Specific Extension</th>
                    <th>Global Extension</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Location</strong></td>
                    <td><code>milkadmin/Modules/Recipe/Extensions/Comments/</code></td>
                    <td><code>milkadmin/Extensions/Comments/</code></td>
                </tr>
                <tr>
                    <td><strong>Namespace</strong></td>
                    <td><code>Modules\Recipe\Extensions\Comments</code></td>
                    <td><code>Extensions\Comments</code></td>
                </tr>
                <tr>
                    <td><strong>Foreign Key</strong></td>
                    <td>Hardcoded: <code>recipe_id</code></td>
                    <td>Dynamic: <code>entity_id</code> (configurable)</td>
                </tr>
                <tr>
                    <td><strong>Table Name</strong></td>
                    <td>Hardcoded: <code>#__recipes_comments</code></td>
                    <td>Dynamic: <code>{parent_table}_comments</code></td>
                </tr>
                <tr>
                    <td><strong>Configuration</strong></td>
                    <td>Fixed for Recipe module</td>
                    <td>Uses hooks for dynamic configuration</td>
                </tr>
                <tr>
                    <td><strong>Reusability</strong></td>
                    <td>Only for Recipe module</td>
                    <td>Works with any module</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Using the Global Comments Extension</h3>
    <p>To use the global extension instead of creating your own, simply use the same <code>->extensions(['Comments'])</code> syntax. The system will automatically find and load the global extension if no module-specific one exists:</p>

    <pre class="border p-2 text-bg-gray"><code class="language-php">// RecipeModel.php
$rule->table('#__recipes')
    ->id()
    ->title('name')
    ->extensions(['Comments'])  // Loads Extensions\Comments\Model
    ->text('content');

// RecipeModule.php
$rule->page('recipes')
    ->extensions(['Comments']);  // Loads Extensions\Comments\Module and Controller

// In controller
TableBuilder::create($this->model, 'idTableRecipes')
    ->extensions(['Comments']);  // Loads Extensions\Comments\GetDataBuilder</code></pre>

    <p>The global extension will automatically create a <code>#__recipes_comments</code> table with an <code>entity_id</code> foreign key, and provide all the same functionality without writing any extension code.</p>

    <h3>Available Global Extensions</h3>
    <p>MilkAdmin provides several ready-to-use global extensions in <code>milkadmin/Extensions/</code>:</p>
    <ul>
        <li><strong>Comments</strong> - Complete commenting system</li>
        <li><strong>Audit</strong> - Automatic tracking of created_by, created_at, updated_by, updated_at</li>
        <li><strong>Author</strong> - Tracking of created_by only</li>
        <li><strong>SoftDelete</strong> - Soft delete functionality</li>
    </ul>

    <p>Each global extension can be loaded the same way: <code>->extensions(['ExtensionName'])</code></p>

    <h2>10. See Also</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/RelatedContent/managing-related-content-fetch">Managing Related Content with Fetch-Based Interface</a> - The fetch approach without extensions</li>
        <li><a href="?page=docs&action=Developer/RelatedContent/managing-related-content">Managing Related Content in Separate Pages</a> - Page-based approach</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form">FormBuilder Documentation</a></li>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder Documentation</a></li>
    </ul>

</div>
