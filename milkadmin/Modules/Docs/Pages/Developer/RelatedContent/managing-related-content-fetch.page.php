<?php
namespace Modules\Docs\Pages;
/**
 * @title Managing Related Content with Fetch-Based Interface
 * @guide developer
 * @order 46
 * @tags Posts, Comments, Related Content, Relationships, hasMany, Fetch, AJAX, Offcanvas, Modal, Service Class
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Managing Related Content with Fetch-Based Interface</h1>
    <p class="text-muted">Revision: 2025/12/16</p>
    <p>This guide demonstrates how to manage related content (Recipe → Comments) using fetch/AJAX instead of page reload. The comments list opens in an offcanvas, the edit form in a modal, and all operations (sorting, searching, pagination, save, delete) happen without reloading the main page.</p>

    <div class="alert alert-info">
        <strong>Prerequisite:</strong> This guide builds on the concepts from <a href="?page=docs&action=Developer/RelatedContent/managing-related-content">Managing Related Content in Separate Pages</a>. The main difference is in the user interface and server response type.
    </div>

    <h2>1. Overview</h2>
    <p>In a fetch-based approach, clicking on "Comments" opens an offcanvas sidebar with the comments list. Clicking "Edit" opens a modal with the form. All operations use AJAX requests, and the server responds with JSON instructions to control the UI elements.</p>

    <p>The key mechanism is the <code>activeFetch()</code> method available on TableBuilder and FormBuilder, combined with <code>data-fetch</code> attributes on links and Response::json() for server responses.</p>

    <h2>2. Page Reload vs Fetch: Architecture Comparison</h2>

    <h3>Page Reload Approach (Posts Module)</h3>
    <p><strong>User Flow:</strong></p>
    <ol>
        <li>Click "Comments" → loads new page with comments list</li>
        <li>Click "Edit Comment" → loads new page with form</li>
        <li>Save form → redirects to comments list page</li>
        <li>Every action requires a full page reload</li>
    </ol>

    <h3>Fetch Approach (Recipe Module)</h3>
    <p><strong>User Flow:</strong></p>
    <ol>
        <li>Click "Comments" → fetch request → opens offcanvas with comments list (no page reload)</li>
        <li>Sort/search/pagination → fetch request → updates only table HTML in offcanvas</li>
        <li>Click "Edit Comment" → fetch request → opens modal with form</li>
        <li>Save form → fetch request → closes modal, reloads comments table and updates counter in main list</li>
    </ol>

    <h3>Comparison Table</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Aspect</th>
                    <th>Page Reload</th>
                    <th>Fetch</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Server Response</strong></td>
                    <td>Complete HTML page</td>
                    <td>JSON with UI instructions</td>
                </tr>
                <tr>
                    <td><strong>FormBuilder</strong></td>
                    <td>Third parameter = redirect URL<br><code>->getForm()</code> returns HTML</td>
                    <td><code>->activeFetch()</code><br><code>->getResponse()</code> returns array</td>
                </tr>
                <tr>
                    <td><strong>TableBuilder</strong></td>
                    <td>Normal links</td>
                    <td><code>->activeFetch()</code><br><code>->setRequestAction()</code></td>
                </tr>
                <tr>
                    <td><strong>Links to Comments</strong></td>
                    <td><code>&lt;a href="..."&gt;</code></td>
                    <td><code>&lt;a href="..." data-fetch="post"&gt;</code></td>
                </tr>
                <tr>
                    <td><strong>Response Method</strong></td>
                    <td><code>Response::render()</code></td>
                    <td><code>Response::json()</code></td>
                </tr>
                <tr>
                    <td><strong>Architecture</strong></td>
                    <td>Separate Controller</td>
                    <td>Module with methods + Service class</td>
                </tr>
                <tr>
                    <td><strong>User Experience</strong></td>
                    <td>Loads complete page</td>
                    <td>Opens offcanvas/modal, no reload</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>3. Step 1: Create the Models</h2>

    <h3>RecipeModel (Main Model)</h3>
    <p>Create <code>milkadmin_local/Modules/Recipe/RecipeModel.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Local\Modules\Recipe;
use App\Abstracts\AbstractModel;

class RecipeModel extends AbstractModel
{
   protected function configure($rule): void
   {
        $rule->table('#__recipes')
            ->id()
            ->hasMany('comments', RecipeCommentsModel::class, 'recipe_id')
            ->title('name')->index()
            ->text('ingredients')->formType('textarea')
            ->select('difficulty', ['Easy', 'Medium', 'Hard']);
   }
}</code></pre>

    <ul>
        <li><code>->hasMany('comments', RecipeCommentsModel::class, 'recipe_id')</code> - Defines one-to-many relationship:
            <ul>
                <li><code>'comments'</code> - Alias for accessing related comments (<code>$recipe->comments</code>)</li>
                <li><code>RecipeCommentsModel::class</code> - The related model</li>
                <li><code>'recipe_id'</code> - Foreign key in the comments table</li>
            </ul>
        </li>
    </ul>

    <h3>RecipeCommentsModel (Related Model)</h3>
    <p>Create <code>milkadmin_local/Modules/Recipe/RecipeCommentsModel.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Local\Modules\Recipe;
use App\Abstracts\AbstractModel;

class RecipeCommentsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__recipe_comments')
            ->id()
            ->int('recipe_id')->formType('hidden')
            ->text('comment');
    }
}</code></pre>

    <ul>
        <li><code>->int('recipe_id')->formType('hidden')</code> - Foreign key field hidden in forms</li>
        <li>The <code>hidden</code> form type automatically hides it in the interface</li>
    </ul>

    <h2>4. Step 2: Register Models in Module</h2>
    <p>Create <code>milkadmin_local/Modules/Recipe/RecipeModule.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Local\Modules\Recipe;
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
             ->access('registered')
             ->addModels(['comment' => RecipeCommentsModel::class]);
   }

   // Controller methods will be added here
}</code></pre>

    <ul>
        <li><code>->addModels(['comment' => RecipeCommentsModel::class])</code> - Registers CommentsModel as an additional model</li>
        <li>In this module, the Module class contains both configuration and controller methods (unlike Posts where there's a separate PostsController)</li>
    </ul>

    <h2>5. Step 3: Main List with Fetch-Enabled Comments Link</h2>
    <p>Add this method in <code>RecipeModule</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('home')]
public function recipesList() {
    $tableBuilder = TableBuilder::create($this->model, 'idTableRecipes')
        ->activeFetch()
        ->field('name')
            ->link('?page='.$this->page.'&action=edit&id=%id%')
        ->field('comments')
            ->label('Comments')
            ->fn(function ($row) {
                $comments = count($row->comments);
                return '&lt;a href="?page='.$this->page.'&action=comments&recipe_id='.$row->id.'" data-fetch="post"&gt;'.$comments.' &lt;i class="bi bi-chat-dots"&gt;&lt;/i&gt;&lt;/a&gt;';
            })
        ->setDefaultActions();
    $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
    Response::render(__DIR__ . '/Views/list_page.php', $response);
}</code></pre>

    <p><strong>Key elements:</strong></p>

    <ul>
        <li><code>->activeFetch()</code> - Enables fetch mode for the table. Action links (edit, delete) are handled via fetch instead of normal navigation.</li>
        <li><code>->field('comments')->fn(...)</code> - Creates a custom column using the hasMany relationship:
            <ul>
                <li><code>count($row->comments)</code> - Counts comments using the relationship</li>
                <li>Returns HTML with a clickable link</li>
            </ul>
        </li>
        <li><code>data-fetch="post"</code> - Critical attribute that tells JavaScript to:
            <ul>
                <li>Intercept the click event</li>
                <li>Make a fetch POST request instead of navigating</li>
                <li>Without this attribute, the link would cause a normal page reload</li>
            </ul>
        </li>
    </ul>

    <div class="row">
        <div class="col-md-6">
            <h4>Page Reload Approach</h4>
            <pre class="border p-2 text-bg-gray"><code class="language-php">// No activeFetch
TableBuilder::create($model, 'idTablePosts')
    ->field('comments')->fn(function($row) {
        return count($row->comments); // Just number
    })
    ->addAction('comment', [
        'label' => 'Comments',
        'link' => '?page=posts&action=comments&post_id=%id%'
        // Normal link, causes page reload
    ]);</code></pre>
        </div>
        <div class="col-md-6">
            <h4>Fetch Approach</h4>
            <pre class="border p-2 text-bg-gray"><code class="language-php">// With activeFetch
TableBuilder::create($model, 'idTableRecipes')
    ->activeFetch() // Enable fetch mode
    ->field('comments')->fn(function ($row) {
        // Returns HTML link with data-fetch
        return '&lt;a href="..." data-fetch="post"&gt;'
            .$comments.'&lt;/a&gt;';
    });</code></pre>
        </div>
    </div>

    <h2>6. Step 4: RecipeService Class - Separating Business Logic</h2>
    <p>Create <code>milkadmin_local/Modules/Recipe/RecipeService.php</code>:</p>

    <p>This class doesn't exist in the Posts module. It serves to separate business logic from controller methods, making the code more organized and reusable.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Local\Modules\Recipe;
use Builders\{TableBuilder, FormBuilder, TitleBuilder, SearchBuilder};
use App\Response;

class RecipeService
{
    // Methods explained in following sections
}</code></pre>

    <h3>6.1 getRecipeId() - Validation</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function getRecipeId(): RecipeModel {
    $model = new RecipeModel();
    $recipe_id = $_POST['data']['recipe_id'] ?? $_REQUEST['recipe_id'] ?? 0;

    if ($recipe_id == 0) {
        Response::json(['success' => false, 'msg' => 'Recipe ID not provided']);
    }

    $recipe = $model->getById($recipe_id);
    if ($recipe->isEmpty()) {
        Response::json(['success' => false, 'msg' => 'Recipe not found']);
    }

    return $recipe;
}</code></pre>

    <ul>
        <li>Retrieves <code>recipe_id</code> from <code>$_POST['data']['recipe_id']</code> (from form submissions) or <code>$_REQUEST['recipe_id']</code> (from URL)</li>
        <li>Validates that it exists and the recipe exists in the database</li>
        <li>Responds with JSON on error (not redirect)</li>
        <li>Returns the complete RecipeModel object, not just the ID</li>
    </ul>

    <h3>6.2 getCommentOffcanvasHtml() - First Load Complete HTML</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function getCommentOffcanvasHtml(RecipeModel $recipe)  {
    $title = TitleBuilder::create($recipe->name)
        ->addButton('New Comment', '?page=recipe&action=comment-edit&recipe_id=' . $recipe->id , 'primary', '', 'post');

    $search = SearchBuilder::create('idTableRecipeComments')
        ->search('search')
        ->layout('full-width')
        ->placeholder('Type to search...');

    $table = self::getCommentTable($recipe);

    return $title->render().'&lt;br&gt;'.$search->render().'&lt;br&gt;'.$table->render();
}</code></pre>

    <p>This method builds the <strong>complete HTML</strong> for the first offcanvas load:</p>

    <ul>
        <li><strong>TitleBuilder</strong> - Creates the title with the recipe name:
            <ul>
                <li><code>->addButton(...)</code> - "New Comment" button</li>
                <li>Fifth parameter <code>'post'</code> - Adds <code>data-fetch="post"</code> attribute to the button</li>
            </ul>
        </li>
        <li><strong>SearchBuilder</strong> - Creates the search bar:
            <ul>
                <li><code>->search('search')</code> - Search field name</li>
                <li>Connected to <code>'idTableRecipeComments'</code> - When searching, updates that table</li>
            </ul>
        </li>
        <li><strong>TableBuilder</strong> - Via <code>getCommentTable()</code></li>
        <li>Returns concatenated HTML: title + search + table</li>
    </ul>

    <p><strong>Why needed:</strong> When you click "Comments", the offcanvas opens and must immediately show title, search, and table. Later, when sorting/searching/paginating, only the table is updated, not the entire HTML.</p>

    <h3>6.3 getCommentTable() - Table with Fetch Actions</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function getCommentTable(RecipeModel $recipe): TableBuilder {
    $commentsModel = new RecipeCommentsModel();

    return TableBuilder::create($commentsModel, 'idTableRecipeComments')
        ->activeFetch()
        ->setRequestAction('update-comment-table')
        ->where('recipe_id = ?', [$recipe->id])
        ->field('comment')->truncate(100)
        ->addAction('edit', [
            'label' => 'Edit',
            'link' => '?page=recipe&action=comment-edit&recipe_id='.$recipe->id.'&id=%id%',
        ])
        ->addAction('delete', [
            'label' => 'Delete',
            'action' => [self::class, 'deleteComment'],
        ])
        ->customData('recipe_id', $recipe->id);
}</code></pre>

    <p><strong>Method breakdown:</strong></p>

    <ol>
        <li><strong><code>->activeFetch()</code></strong> - Enables fetch mode:
            <ul>
                <li>Actions (edit, delete) are handled via fetch</li>
                <li>Links don't cause page reload</li>
            </ul>
        </li>
        <li><strong><code>->setRequestAction('update-comment-table')</code></strong> - Fundamental:
            <ul>
                <li>When sorting, searching, or paginating the table, calls the <code>update-comment-table</code> action</li>
                <li>Instead of the default behavior which would reload the page</li>
                <li>This action returns only the updated table HTML</li>
            </ul>
        </li>
        <li><strong><code>->where('recipe_id = ?', [$recipe->id])</code></strong> - Filters comments:
            <ul>
                <li>Shows only comments for the current recipe</li>
                <li>This filter is maintained during sorting/searching/pagination</li>
            </ul>
        </li>
        <li><strong><code>->customData('recipe_id', $recipe->id)</code></strong> - Passes extra data:
            <ul>
                <li>The <code>recipe_id</code> is included in all table requests</li>
                <li>Necessary for the delete action and update-comment-table action</li>
                <li>Without this, update-comment-table wouldn't know which recipe to filter</li>
            </ul>
        </li>
        <li><strong><code>->addAction('delete', ['action' => [self::class, 'deleteComment']])</code></strong> - Custom callback:
            <ul>
                <li>Instead of <code>'link'</code>, uses <code>'action'</code> with a callback</li>
                <li>The <code>deleteComment</code> callback is called directly</li>
            </ul>
        </li>
    </ol>

    <h3>6.4 getCommentForm() - Form in Modal with Auto-Reload</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function getCommentForm(RecipeModel $recipe) {
    $commentsModel = new RecipeCommentsModel();

    return FormBuilder::create($commentsModel, 'recipe')
        ->activeFetch()
        ->asModal()
        ->customData('recipe_id', $recipe->id)
        ->setTitle('New Comment', 'Edit Comment')
        ->dataListId('idTableRecipeComments')
        ->field('recipe_id')->value($recipe->id)->readonly()
        ->field('comment')->required()
        ->setActions([
            'save' => [
                'label' => 'Save',
                'class' => 'btn btn-primary',
                'action' => FormBuilder::saveAction()
            ]
        ]);
}</code></pre>

    <p><strong>Method breakdown:</strong></p>

    <ol>
        <li><strong><code>->activeFetch()</code></strong> - Enables fetch mode for the form:
            <ul>
                <li>Submit is handled via fetch instead of normal POST</li>
                <li>Response is JSON, not redirect</li>
            </ul>
        </li>
        <li><strong><code>->asModal()</code></strong> - Opens the form in a modal:
            <ul>
                <li>Instead of a separate page</li>
                <li>The modal overlays the offcanvas</li>
            </ul>
        </li>
        <li><strong><code>->customData('recipe_id', $recipe->id)</code></strong> - Passes hidden recipe_id:
            <ul>
                <li>Included in form data on submit</li>
                <li>Necessary to save the comment associated with the correct recipe</li>
            </ul>
        </li>
        <li><strong><code>->setTitle('New Comment', 'Edit Comment')</code></strong> - Custom titles:
            <ul>
                <li>First parameter: title when creating new (no id present)</li>
                <li>Second parameter: title when editing (id present)</li>
            </ul>
        </li>
        <li><strong><code>->dataListId('idTableRecipeComments')</code></strong> - FUNDAMENTAL:
            <ul>
                <li>After save, automatically reloads the table with id <code>idTableRecipeComments</code></li>
                <li>You immediately see the added/modified comment without closing the offcanvas</li>
            </ul>
        </li>
        <li><strong><code>->field('recipe_id')->value($recipe->id)->readonly()</code></strong> - Readonly field:
            <ul>
                <li>Pre-fills the field with recipe_id</li>
                <li>Makes it readonly to prevent changes</li>
            </ul>
        </li>
        <li><strong><code>->setActions([...])</code></strong> - Defines form buttons:
            <ul>
                <li><code>FormBuilder::saveAction()</code> - Standard save action</li>
                <li>Could add other buttons (cancel, delete, etc.)</li>
            </ul>
        </li>
    </ol>

    <h3>6.5 deleteComment() - Custom Delete Action</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function deleteComment($record, $request) {
    if($record->delete($record->id)) {
        return ['success' => true, 'message' => 'Item deleted successfully'];
    }
    return ['success' => false, 'message' => 'Delete failed'];
}</code></pre>

    <ul>
        <li>Callback used by the delete action in the table</li>
        <li>Receives <code>$record</code> (RecipeCommentsModel object) and <code>$request</code> (request data)</li>
        <li>Returns array with success and message (automatically handled by the system)</li>
    </ul>

    <h2>7. Step 5: Module Controller Actions</h2>
    <p>Add these methods in <code>RecipeModule</code> (after the <code>configure</code> method):</p>

    <h3>7.1 recipeEdit() - Edit Recipe in Offcanvas</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('edit')]
public function recipeEdit() {
    $response = ['page' => $this->page, 'title' => $this->title];

    $response = array_merge($response, FormBuilder::create($this->model, $this->page)
        ->asOffcanvas()
        ->activeFetch()
        ->setTitle('New Recipe', 'Edit Recipe')
        ->dataListId('idTableRecipes')
        ->getResponse());

    Response::json($response);
}</code></pre>

    <p>Form for editing the main recipe (not comments):</p>
    <ul>
        <li><code>->asOffcanvas()</code> - Opens in offcanvas instead of page</li>
        <li><code>->activeFetch()</code> - Submit via fetch</li>
        <li><code>->dataListId('idTableRecipes')</code> - Reloads main list after save</li>
        <li><code>->getResponse()</code> - Returns array for Response::json(), NOT HTML</li>
    </ul>

    <div class="alert alert-info mt-3">
        <strong>FormBuilder Overlay Options:</strong>
        <p>You can directly control where forms open using FormBuilder methods:</p>
        <ul class="mb-0">
            <li><code>->asOffcanvas()</code> - Opens form in an offcanvas sidebar</li>
            <li><code>->asModal()</code> - Opens form in a centered modal</li>
            <li>Without these methods, the form renders in a full page (page reload approach)</li>
        </ul>
    </div>

    <div class="row mt-3">
        <div class="col-md-6">
            <h4>Page Reload Approach</h4>
            <pre class="border p-2 text-bg-gray"><code class="language-php">public function postEdit() {
    $response = $this->getCommonData();
    $response['form'] = FormBuilder::create(
        $this->model,
        $this->page
    )->getForm(); // Returns HTML

    Response::render(
        MILK_DIR . '/Theme/SharedViews/edit_page.php',
        $response
    );
}</code></pre>
        </div>
        <div class="col-md-6">
            <h4>Fetch Approach</h4>
            <pre class="border p-2 text-bg-gray"><code class="language-php">public function recipeEdit() {
    $response = ['page' => $this->page, 'title' => $this->title];

    $response = array_merge($response,
        FormBuilder::create($this->model, $this->page)
            ->asOffcanvas() // Opens in offcanvas
            ->activeFetch() // Fetch mode
            ->dataListId('idTableRecipes')
            ->getResponse() // Returns array
    );

    Response::json($response);
}</code></pre>
        </div>
    </div>

    <h3>7.2 recipeComments() - Open Offcanvas with Comments List</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('comments')]
public function recipeComments() {
    $recipe = RecipeService::getRecipeId();

    Response::json([
        'offcanvas_end' => [
            'title' => 'Comments',
            'body' => RecipeService::getCommentOffcanvasHtml($recipe),
            'size' => 'lg'
        ]
    ]);
}</code></pre>

    <p>Called when you click the "Comments" link in the main list:</p>
    <ul>
        <li><code>RecipeService::getRecipeId()</code> - Validates and retrieves the recipe</li>
        <li><strong><code>'offcanvas_end'</code></strong> - Special key that tells JavaScript to open the offcanvas:
            <ul>
                <li><code>'title'</code> - Offcanvas title</li>
                <li><code>'body'</code> - Complete HTML (title + search + table) generated by <code>getCommentOffcanvasHtml()</code></li>
                <li><code>'size'</code> - 'lg' for wide offcanvas</li>
            </ul>
        </li>
        <li>This is the <strong>first load</strong>: generates all the HTML</li>
    </ul>

    <h3>7.3 updateCommentTable() - Reload Table on Sort/Search/Pagination</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('update-comment-table')]
public function updateCommentTable() {
    $recipe = RecipeService::getRecipeId();
    $tableBuilder = RecipeService::getCommentTable($recipe);
    $response = $tableBuilder->getResponse();

    $response['list'] = [
        "id" => "idTableRecipes",
        "action" => "reload"
    ];

    Response::json($response);
}</code></pre>

    <p>This method doesn't exist in the Posts module because Posts always reloads the entire page.</p>

    <p><strong>When called:</strong></p>
    <ul>
        <li>When sorting a column in the comments table</li>
        <li>When searching in the search bar</li>
        <li>When changing page in pagination</li>
        <li>This happens because in <code>getCommentTable()</code> there's <code>->setRequestAction('update-comment-table')</code></li>
    </ul>

    <p><strong>What it does:</strong></p>
    <ol>
        <li><code>$tableBuilder->getResponse()</code> - Generates updated HTML for ONLY the table</li>
        <li><strong><code>$response['list']</code></strong> - FUNDAMENTAL:
            <ul>
                <li>Tells JavaScript to ALSO reload the main table <code>idTableRecipes</code></li>
                <li>Why? To update the comments counter!</li>
                <li>If you add/modify/delete a comment, the number in the "Comments" column of the main list must update</li>
            </ul>
        </li>
    </ol>

    <p><strong>Why important:</strong> Updates TWO tables with one action:</p>
    <ol>
        <li>The comments table (in offcanvas) with new sorted/filtered data</li>
        <li>The main table (in page) to recalculate the counter</li>
    </ol>

    <h3>7.4 commentEdit() - Open Modal with Comment Form</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('comment-edit')]
public function commentEdit() {
    $recipe = RecipeService::getRecipeId();
    $formBuilder = RecipeService::getCommentForm($recipe);
    Response::json($formBuilder->getResponse());
}</code></pre>

    <ul>
        <li>Called when you click "Edit" or "New Comment"</li>
        <li><code>RecipeService::getCommentForm($recipe)</code> - Generates the FormBuilder</li>
        <li><code>->getResponse()</code> - Returns array with all form configurations</li>
        <li>JavaScript reads this JSON and opens the modal with the form</li>
    </ul>

    <h2>8. Step 6: Create the View</h2>
    <p>Create <code>milkadmin_local/Modules/Recipe/Views/list_page.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts\Views;
use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access
?&gt;
&lt;div class="card"&gt;
    &lt;div class="card-header"&gt;
    &lt;?php
    $title = TitleBuilder::create($title)->addButton('Add New', '?page='.$page.'&action=edit', 'primary', '', 'post');
    echo (isset($search_html)) ? $title->addRightContent($search_html) : $title->addSearch('idTableRecipes', 'Search...', 'Search');
    ?&gt;
    &lt;/div&gt;
    &lt;div class="card-body"&gt;
        &lt;p class="text-body-secondary mb-3"&gt;&lt;?php _pt('Recipe module with fetch-based comments management.') ?&gt;&lt;/p&gt;
        &lt;?php _ph($html); ?&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>

    <ul>
        <li>Very simple view, used only for the main list</li>
        <li><code>->addButton('Add New', ..., 'primary', '', 'post')</code> - Fifth parameter <code>'post'</code> adds <code>data-fetch="post"</code> to button</li>
        <li>All other interfaces (offcanvas, modal) are handled via JSON</li>
    </ul>

    <h2>9. Step 7: Understanding the Complete Fetch Flow</h2>

    <h3>1. Click "Comments" (column in main list)</h3>
    <pre class="border p-2 text-bg-light"><code>User: clicks link with data-fetch="post"
  ↓
JavaScript: intercepts click, makes fetch POST to ?page=recipe&action=comments&recipe_id=X
  ↓
Server: recipeComments() returns JSON:
  {
    "offcanvas_end": {
      "title": "Comments",
      "body": "&lt;Complete HTML: title + search + table&gt;",
      "size": "lg"
    }
  }
  ↓
JavaScript: opens offcanvas and inserts HTML in body
  ↓
Result: offcanvas opened with comments list</code></pre>

    <h3>2. Sort/Search/Pagination in comments table</h3>
    <pre class="border p-2 text-bg-light"><code>User: clicks column header to sort
  ↓
JavaScript: TableBuilder in fetch mode makes POST to ?page=recipe&action=update-comment-table
            Includes: recipe_id (from customData), sort, order, search, page
  ↓
Server: updateCommentTable() returns JSON:
  {
    "html": "&lt;Updated table HTML only&gt;",
    "list": {
      "id": "idTableRecipes",
      "action": "reload"
    }
  }
  ↓
JavaScript:
  1. Replaces table HTML in offcanvas
  2. Reloads table idTableRecipes (main list) via fetch
  ↓
Result: comments table updated + counter in main list updated</code></pre>

    <h3>3. Click "Edit" comment</h3>
    <pre class="border p-2 text-bg-light"><code>User: clicks Edit button
  ↓
JavaScript: activeFetch() intercepts, makes fetch POST to ?page=recipe&action=comment-edit&id=X&recipe_id=Y
  ↓
Server: commentEdit() returns JSON with FormBuilder::getResponse():
  {
    "modal": {
      "title": "Edit Comment",
      "body": "&lt;HTML form&gt;",
      ...
    }
  }
  ↓
JavaScript: opens modal with the form
  ↓
Result: modal opened over the offcanvas</code></pre>

    <h3>4. Submit form (Save comment)</h3>
    <pre class="border p-2 text-bg-light"><code>User: clicks "Save"
  ↓
JavaScript: activeFetch() intercepts submit, makes fetch POST with form data
            Includes: recipe_id, comment, id (from customData)
  ↓
Server: FormBuilder processes save, returns JSON:
  {
    "modal": {"action": "hide"},
    "list": {
      "id": "idTableRecipeComments",
      "action": "reload"
    }
  }
  (if validation errors, returns form with errors)
  ↓
JavaScript:
  1. Closes modal
  2. Reloads table idTableRecipeComments via fetch
     This request calls update-comment-table which:
       - Returns updated table HTML
       - Includes "list": {"id": "idTableRecipes", "action": "reload"}
  3. Also reloads table idTableRecipes
  ↓
Result: modal closed, comments table updated, counter updated</code></pre>

    <h3>5. Delete comment</h3>
    <pre class="border p-2 text-bg-light"><code>User: clicks "Delete"
  ↓
JavaScript: confirms, makes fetch POST with delete action
  ↓
Server: deleteComment() callback returns:
  {"success": true, "message": "Item deleted successfully"}
  Then automatically reloads table (like update-comment-table)
  ↓
JavaScript:
  1. Shows toast with message
  2. Reloads table idTableRecipeComments
  3. Reloads table idTableRecipes (thanks to list: {...} in updateCommentTable)
  ↓
Result: comment deleted, tables updated</code></pre>

    <h3>Key Flow Points</h3>
    <ul>
        <li><strong>First load complete</strong>: <code>recipeComments()</code> generates all HTML (title + search + table)</li>
        <li><strong>Subsequent partial updates</strong>: <code>updateCommentTable()</code> generates only table HTML</li>
        <li><strong>Double update</strong>: every comment modification updates both comments table and main list</li>
        <li><strong>Automatic dataListId</strong>: when saving form, system automatically reloads specified table</li>
        <li><strong>Cascade of updates</strong>: save form → reload comments table → reload main list</li>
    </ul>

    <h2>10. Step 8: Install and Test</h2>

    <h3>Install Database Tables</h3>
    <p>Run the CLI command:</p>

    <pre><code>php milkadmin/cli.php recipes:update</code></pre>

    <p>This command:</p>
    <ul>
        <li>Creates table <code>#__recipes</code> with fields: id, name, ingredients, difficulty</li>
        <li>Creates table <code>#__recipe_comments</code> with fields: id, recipe_id, comment</li>
    </ul>

    <h3>Testing Workflow</h3>

    <ol>
        <li><strong>Main list</strong>:
            <ul>
                <li>Add some recipes via "Add New"</li>
                <li>Note that "Comments" column shows 0 with icon</li>
            </ul>
        </li>
        <li><strong>Open comments list</strong>:
            <ul>
                <li>Click the number in "Comments" column</li>
                <li>Wide offcanvas opens with title, search, empty table</li>
                <li>Note: no page reload</li>
            </ul>
        </li>
        <li><strong>Add comment</strong>:
            <ul>
                <li>Click "New Comment" in offcanvas</li>
                <li>Modal opens with form over offcanvas</li>
                <li>recipe_id is pre-filled and readonly</li>
                <li>Write a comment and save</li>
                <li>Modal closes, comments table updates, counter in main list changes to 1</li>
            </ul>
        </li>
        <li><strong>Edit comment</strong>:
            <ul>
                <li>Click "Edit" in comment row</li>
                <li>Modal opens with comment loaded</li>
                <li>Modify and save</li>
                <li>Modal closes, table updates</li>
            </ul>
        </li>
        <li><strong>Search/Sort</strong>:
            <ul>
                <li>Add more comments</li>
                <li>Try searching in search bar</li>
                <li>Only table updates, not entire offcanvas</li>
                <li>Click column header to sort</li>
                <li>Change page if many comments</li>
            </ul>
        </li>
        <li><strong>Delete comment</strong>:
            <ul>
                <li>Click "Delete", confirm</li>
                <li>Success toast, table updates</li>
                <li>Counter in main list decreases</li>
            </ul>
        </li>
        <li><strong>Close offcanvas</strong>:
            <ul>
                <li>Click close button or outside offcanvas</li>
                <li>Offcanvas closes, main list still there without reload</li>
            </ul>
        </li>
    </ol>

    <h2>11. Key Concepts Summary</h2>

    <h3>Enabling Fetch Mode</h3>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Builder</th>
                    <th>Method</th>
                    <th>Effect</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>TableBuilder</strong></td>
                    <td><code>->activeFetch()</code></td>
                    <td>Actions (edit, delete) use fetch instead of normal links</td>
                </tr>
                <tr>
                    <td><strong>TableBuilder</strong></td>
                    <td><code>->setRequestAction('action-name')</code></td>
                    <td>Sort/search/pagination call this action instead of default</td>
                </tr>
                <tr>
                    <td><strong>FormBuilder</strong></td>
                    <td><code>->activeFetch()</code></td>
                    <td>Submit uses fetch instead of normal POST</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Opening in Overlay</h3>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Builder</th>
                    <th>Method</th>
                    <th>Effect</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>FormBuilder</strong></td>
                    <td><code>->asOffcanvas()</code></td>
                    <td>Opens form in side offcanvas</td>
                </tr>
                <tr>
                    <td><strong>FormBuilder</strong></td>
                    <td><code>->asModal()</code></td>
                    <td>Opens form in centered modal</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Auto-Reloading Lists</h3>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Builder</th>
                    <th>Method</th>
                    <th>Effect</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>FormBuilder</strong></td>
                    <td><code>->dataListId('tableId')</code></td>
                    <td>After save, automatically reloads table with this ID</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Passing Context Data</h3>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Builder</th>
                    <th>Method</th>
                    <th>Effect</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>TableBuilder</strong></td>
                    <td><code>->customData('key', $value)</code></td>
                    <td>Passes extra data to all table actions</td>
                </tr>
                <tr>
                    <td><strong>FormBuilder</strong></td>
                    <td><code>->customData('key', $value)</code></td>
                    <td>Passes hidden extra data in form</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Manual List Reload in Action</h3>
    <p>In an action (e.g., updateCommentTable), you can force reload of other lists:</p>

    <pre class="border p-2 text-bg-gray"><code class="language-php">$response['list'] = [
    "id" => "idTableRecipes",
    "action" => "reload"
];
Response::json($response);</code></pre>

    <p>This tells JavaScript to also reload the <code>idTableRecipes</code> table.</p>

    <h3>data-fetch Attribute</h3>
    <p>In custom HTML links or TitleBuilder buttons:</p>

    <pre class="border p-2 text-bg-gray"><code class="language-php">// Custom link with data-fetch
'&lt;a href="?page=recipe&action=comments&recipe_id='.$id.'" data-fetch="post"&gt;Comments&lt;/a&gt;'

// TitleBuilder with fetch (fifth parameter)
TitleBuilder::create('Title')->addButton('Label', 'url', 'primary', '', 'post');</code></pre>

    <p>The <code>'post'</code> parameter or <code>data-fetch="post"</code> attribute tells JavaScript to intercept the click and make a fetch request instead of following the link.</p>

    <h3>Response Types</h3>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Response Type</th>
                    <th>Usage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Posts</strong></td>
                    <td><code>Response::render($view, $data)</code></td>
                    <td>Loads complete HTML page</td>
                </tr>
                <tr>
                    <td><strong>Recipe (home)</strong></td>
                    <td><code>Response::render($view, $data)</code></td>
                    <td>Only for main list</td>
                </tr>
                <tr>
                    <td><strong>Recipe (actions)</strong></td>
                    <td><code>Response::json($array)</code></td>
                    <td>All other actions return JSON</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Service Class Pattern</h3>
    <p>The <code>RecipeService</code> class separates:</p>
    <ul>
        <li><strong>Validation</strong>: getRecipeId()</li>
        <li><strong>HTML generation</strong>: getCommentOffcanvasHtml()</li>
        <li><strong>Builder configuration</strong>: getCommentTable(), getCommentForm()</li>
        <li><strong>Callbacks</strong>: deleteComment()</li>
    </ul>
    <p>This keeps Module/Controller methods lean and reusable.</p>

    <h3>FormBuilder::getForm() vs getResponse()</h3>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Context</th>
                    <th>Method</th>
                    <th>Returns</th>
                    <th>Used with</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Page reload</strong></td>
                    <td><code>->getForm()</code></td>
                    <td>HTML string</td>
                    <td><code>Response::render()</code></td>
                </tr>
                <tr>
                    <td><strong>Fetch mode</strong></td>
                    <td><code>->getResponse()</code></td>
                    <td>Array</td>
                    <td><code>Response::json()</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>First Load vs Subsequent Updates</h3>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Method</th>
                    <th>Generates</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>First load</strong></td>
                    <td><code>getCommentOffcanvasHtml()</code></td>
                    <td>Title + Search + Table</td>
                    <td>Click on "Comments"</td>
                </tr>
                <tr>
                    <td><strong>Updates</strong></td>
                    <td><code>updateCommentTable()</code></td>
                    <td>Only table HTML</td>
                    <td>Sort/search/pagination</td>
                </tr>
            </tbody>
        </table>
    </div>

    <p>This optimizes performance: first load is complete, but subsequent updates transfer only necessary data.</p>

    <h2>12. See Also</h2>
    <ul>
        <li><a href="?page=docs&action=Developer/RelatedContent/managing-related-content">Managing Related Content in Separate Pages</a> - Page-based approach</li>
        <li><a href="?page=docs&action=Developer/Advanced/fetch-based-modules">Fetch-Based Modules</a> - Fetch for single modules</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form">FormBuilder Documentation</a></li>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder Documentation</a></li>
    </ul>

</div>
