<?php
namespace Modules\Docs\Pages;
/**
 * @title Managing Related Content in Separate Pages
 * @guide developer
 * @order 45
 * @tags Posts, Comments, Related Content, Relationships, hasMany, Cascade Delete, Additional Models
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Managing Related Content in Separate Pages</h1>
    <p class="text-muted">Revision: 2025/12/13</p>
    <p>This guide demonstrates how to manage related content (Comments for Posts) in separate pages. Each comment list and edit form loads in a new page, not via AJAX.</p>

    <div class="alert alert-info">
        <strong>Prerequisite:</strong> This guide extends the <a href="?page=docs&action=Developer/GettingStarted/getting-started-post">Posts Module Tutorial</a>. Complete that tutorial first to have the Posts module ready.
    </div>

    <div class="alert alert-warning">
        <strong>Important:</strong> This example uses the same views (list_page.php and edit_page.php) for both posts and comments. The views are designed to be reusable for different content types.
    </div>

    <h2>Step 1: Create the CommentsModel</h2>
    <p>Create <code>milkadmin/Modules/Posts/CommentsModel.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use App\Attributes\{Validate};
use App\Abstracts\AbstractModel;

class CommentsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__comm')
            ->id()
            ->int('post_id')
            ->text('comment')->formType('editor');
    }
}</code></pre>

    <p><strong>Explanation:</strong></p>
    <ul>
        <li><code>table('#__comm')</code> - Table name for comments</li>
        <li><code>int('post_id')</code> - Foreign key to link comments to posts</li>
        <li><code>text('comment')->formType('editor')</code> - Comment content with rich text editor</li>
    </ul>

    <h2>Step 2: Register CommentsModel in PostsModule</h2>
    <p>Update <code>milkadmin/Modules/Posts/PostsModule.php</code> to register the CommentsModel:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use App\Abstracts\AbstractModule;

class PostsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('posts')
             ->title('Posts')
             ->menu('Posts', '', 'bi bi-file-earmark-post-fill', 10)
             ->access('authorized')
             ->permissions(['access' => 'Access'])
             ->addModels(['comment'=>CommentsModel::class])
             ->version(251201);
    }

}</code></pre>

    <p><strong>Explanation:</strong></p>
    <ul>
        <li><code>->addModels(['comment'=>CommentsModel::class])</code> - Registers CommentsModel as an additional model accessible via the alias 'comment'</li>
    </ul>

    <h2>Step 3: Define hasMany Relationship in PostsModel</h2>
    <p>Update <code>milkadmin/Modules/Posts/PostsModel.php</code> to define the relationship:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use App\Attributes\{Validate};
use App\Abstracts\AbstractModel;

class PostsModel extends AbstractModel
{

    protected function configure($rule): void
    {
        $rule->table('#__posts')
            ->id()
            ->hasMany('comments', CommentsModel::class, 'post_id', 'CASCADE', false)
            ->title()->index()
            ->text('content')->formType('editor');

    }

    #[Validate('title')]
    public function validateTitle($current_record_obj): string {
        $value = $current_record_obj->title;
        if (strlen($value) < 5) {
            return 'Title must be at least 5 characters long';
        }
        return '';
    }

    protected function afterCreateTable(): void {
        $sql =  "INSERT INTO `".$this->table."` (`id`, `title`, `content`) VALUES
    (1, 'Post Title 1', 'Content of post 1'),
    (2, 'Post Title 2', 'Content of post 2'),
    (3, 'Post Title 3', 'Content of post 3');";
            $this->db->query($sql);
    }
}</code></pre>

    <p><strong>Explanation:</strong></p>
    <ul>
        <li><code>->hasMany('comments', CommentsModel::class, 'post_id', 'CASCADE', false)</code> - Defines a one-to-many relationship:
            <ul>
                <li><code>'comments'</code> - Alias for accessing related comments</li>
                <li><code>CommentsModel::class</code> - The related model</li>
                <li><code>'post_id'</code> - Foreign key in the comments table</li>
                <li><code>'CASCADE'</code> - Delete all comments when a post is deleted</li>
                <li><code>false</code> - Cascade save disabled (comments managed separately)</li>
            </ul>
        </li>
    </ul>

    <div class="alert alert-warning mt-3">
        <strong>Important - Relationship Positioning:</strong>
        <p class="mb-0">The <code>hasMany()</code> method must be placed immediately after the field it connects to. In this case:</p>
        <ul class="mb-0">
            <li><code>->id()</code> creates the primary key field (posts.id)</li>
            <li><code>->hasMany(..., 'post_id', ...)</code> declares that the comments table has a post_id field that links to this id</li>
            <li>Therefore, <code>hasMany()</code> is placed immediately after <code>->id()</code></li>
        </ul>
    </div>

    <h2>Step 4: Update PostsController with Comments Actions</h2>
    <p>Complete <code>milkadmin/Modules/Posts/PostsController.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use App\Abstracts\AbstractController;
use App\{Response, Route};
use App\Attributes\AccessLevel;
use App\Attributes\RequestAction;
use Builders\{ListBuilder, FormBuilder, TableBuilder};

class PostsController extends AbstractController
{
    #[RequestAction('home')]
    public function postsList() {
        $tableBuilder = TableBuilder::create($this->model, 'idTablePosts')
            ->field('content')->truncate(50)
            ->field('title')->link('?page=posts&amp;action=edit&amp;id=%id%')
            ->field('comments')->label('Comments')->fn(function($row) {
                return count($row->comments);
            })
            ->addAction('comment',  [
                'label' => 'Comments',
                'link' => '?page=posts&amp;action=comments&amp;post_id=%id%'
            ])
            ->setDefaultActions();
        $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
        $response['title_btns'] = [['label'=>'Add New', 'link'=>'?page=posts&amp;action=edit']];
        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }

    #[RequestAction('edit')]
    public function postEdit() {
        $response = $this->getCommonData();
        $response['form'] = FormBuilder::create($this->model, $this->page)
        ->getForm();
        $response['title'] = ($_REQUEST['id'] ?? 0) > 0 ? 'Edit Post' : 'Add Post';
        Response::render(__DIR__ . '/Views/edit_page.php', $response);
    }

    #[RequestAction('comments')]
    public function postComments() {
        $response = $this->getCommonData();
        $post_id = $this->requirePostId();
        $model = $this->getAdditionalModels('comment');
        $post = $this->model->getById($post_id);
        $tableBuilder = TableBuilder::create($model, 'idTableComments')
            ->where('post_id = ?', [$post_id])
            ->addAction('edit', ['label'=>'Edit', 'link'=>"?page=posts&amp;action=comment-edit&amp;id=%id%&amp;post_id=".$post_id])
            ->customData('post_id', $post_id);
        // Handle comments listing/editing for a specific post
        $response = array_merge($response, $tableBuilder->getResponse());
        $response['title_btns'] = [['label'=>'Add New', 'link'=>'?page=posts&amp;action=comment-edit&amp;post_id='.$post_id], ['label'=>'Go Back', 'link'=>'?page=posts']];
        $response['title'] = 'Post Comments';
        $response['description'] = 'Comments for post ' . $post->title;
        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }

    #[RequestAction('comment-edit')]
    public function commentEdit() {
        $post_id = $this->requirePostId();
        $model = $this->getAdditionalModels('comment');
        $response = $this->getCommonData();
        $response['form'] = FormBuilder::create($model, $this->page, '?page=posts&amp;action=comments&amp;post_id=' . $post_id)
            ->customData('post_id', $post_id)
            ->addStandardActions(true, '?page=posts&amp;action=comments&amp;post_id=' . $post_id)
            ->field('post_id')
            ->value($post_id)
            ->readonly()
            ->field('comment')->required()
            ->getForm();
        $response['title'] = ($_REQUEST['id'] ?? 0) > 0 ? 'Edit Comment' : 'Add Comment';
        Response::render(__DIR__ . '/Views/edit_page.php', $response);
    }

    private function requirePostId(): int {
        $post_id = ($_REQUEST['post_id'] ?? '0');
        if ($post_id == 0) {
            if (Response::isJson()) {
                Response::json(['success'=>false, 'msg' => 'No post ID provided']);
            } else {
                Route::redirectError('?page=posts', 'No post ID provided');
            }
        }
        return (int)$post_id;
    }

}</code></pre>

    <p><strong>Explanation:</strong></p>

    <h3 class="mt-3">postsList() Method</h3>
    <ul>
        <li><code>->field('comments')->label('Comments')->fn(...)</code> - Adds a column showing the number of comments for each post using the hasMany relationship</li>
        <li><code>->addAction('comment', [...])</code> - Adds a "Comments" button that links to the comments list page for that post</li>
    </ul>

    <h3 class="mt-3">postComments() Method</h3>
    <ul>
        <li><code>#[RequestAction('comments')]</code> - Routes to <code>?page=posts&amp;action=comments&amp;post_id=X</code></li>
        <li><code>$this->requirePostId()</code> - Validates that post_id is provided in the URL</li>
        <li><code>$this->getAdditionalModels('comment')</code> - Retrieves the CommentsModel instance registered in the module</li>
        <li><code>->where('post_id = ?', [$post_id])</code> - Filters comments to show only those for the current post</li>
        <li><code>->customData('post_id', $post_id)</code> - Passes post_id to the delete action</li>
        <li><code>Response::render(__DIR__ . '/Views/list_page.php', $response)</code> - Uses the same list view as posts</li>
    </ul>

    <h3 class="mt-3">commentEdit() Method</h3>
    <ul>
        <li><code>#[RequestAction('comment-edit')]</code> - Routes to <code>?page=posts&amp;action=comment-edit&amp;id=X&amp;post_id=Y</code></li>
        <li><code>FormBuilder::create($model, $this->page, '?page=posts&amp;action=comments&amp;post_id=' . $post_id)</code> - Creates form with redirect URL after save</li>
        <li><code>->customData('post_id', $post_id)</code> - Ensures post_id is included in the form submission</li>
        <li><code>->addStandardActions(true, '?page=posts&amp;action=comments&amp;post_id=' . $post_id)</code> - Adds Save and Cancel buttons with proper redirect</li>
        <li><code>->field('post_id')->value($post_id)->readonly()</code> - Pre-fills and locks the post_id field</li>
        <li><code>Response::render(__DIR__ . '/Views/edit_page.php', $response)</code> - Uses the same edit view as posts</li>
    </ul>

    <h3 class="mt-3">requirePostId() Helper Method</h3>
    <ul>
        <li>Validates that post_id parameter is present in the request</li>
        <li>Redirects to posts list with error message if missing</li>
        <li>Handles both JSON and HTML responses</li>
    </ul>

    <h2>Step 5: Reusing Views</h2>
    <p>The existing views <code>Views/list_page.php</code> and <code>Views/edit_page.php</code> are used for both posts and comments without modification.</p>

    <p><strong>How it works:</strong></p>
    <ul>
        <li>The controller passes different data to the same view templates</li>
        <li><code>list_page.php</code> receives <code>$title</code>, <code>$title_btns</code>, <code>$description</code>, and <code>$html</code> variables</li>
        <li><code>edit_page.php</code> receives <code>$title</code> and <code>$form</code> variables</li>
        <li>The views render whatever content is passed to them, making them reusable</li>
    </ul>

    <h2>Step 6: Install the Comments Table</h2>
    <p>After creating the CommentsModel, create the database table:</p>

    <pre><code>php milkadmin/cli.php posts:update</code></pre>

    <p>This command will:</p>
    <ul>
        <li>Add the <code>#__comm</code> table to the database</li>
        <li>Create the id, post_id, and comment fields</li>
        <li>Set up the CASCADE delete constraint on the post_id foreign key</li>
    </ul>

    <h2>Step 7: Testing the Implementation</h2>
    <p>Navigate to the Posts module and test the following workflow:</p>

    <ol>
        <li>View the posts list - you should see a "Comments" column showing the count</li>
        <li>Click the "Comments" button for a post - you'll be taken to a new page showing comments for that post</li>
        <li>Click "Add New" to create a comment - a new page loads with the comment form</li>
        <li>The post_id field is pre-filled and read-only</li>
        <li>Save the comment - you're redirected back to the comments list for that post</li>
        <li>Click "Go Back" to return to the posts list</li>
        <li>Delete a post - all its comments are automatically deleted (CASCADE)</li>
    </ol>

    <h2>Key Concepts</h2>

    <div class="alert alert-info">
        <strong>Additional Models:</strong>
        <ul class="mb-0">
            <li>Use <code>->addModels()</code> to register multiple models within a single module</li>
            <li>Access additional models in the controller with <code>$this->getAdditionalModels('alias')</code></li>
            <li>This allows managing related content without creating separate modules</li>
        </ul>
    </div>

    <div class="alert alert-info">
        <strong>Page-Based Navigation:</strong>
        <ul class="mb-0">
            <li>Each action loads a complete new page (not AJAX)</li>
            <li>URLs follow the pattern: <code>?page=posts&amp;action=comments&amp;post_id=X</code></li>
            <li>Post context is maintained via the post_id URL parameter</li>
            <li>Navigation buttons ("Go Back") provide clear user flow</li>
        </ul>
    </div>

    <div class="alert alert-info">
        <strong>View Reusability:</strong>
        <ul class="mb-0">
            <li>Same view templates serve different content types</li>
            <li>Controller prepares data and passes to generic views</li>
            <li>Views use variables like <code>$title</code>, <code>$html</code>, <code>$form</code> regardless of content type</li>
        </ul>
    </div>

    <div class="alert alert-info">
        <strong>CASCADE Delete:</strong>
        <ul class="mb-0">
            <li>When a post is deleted, all its comments are automatically deleted</li>
            <li>Defined in the hasMany relationship: <code>'CASCADE'</code> parameter</li>
            <li>Database foreign key constraint handles the deletion</li>
        </ul>
    </div>

</div>
