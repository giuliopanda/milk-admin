<?php
// Controller - Correct Model Methods Usage
// This example shows the CORRECT way to use Model methods in Controllers

namespace Modules\Posts;

use App\Abstracts\AbstractController;
use App\Response;
use App\Attributes\RequestAction;

class PostsController extends AbstractController
{
    // ============================================
    // GETTING DATA
    // ============================================

    #[RequestAction('home')]
    public function listPosts()
    {
        // ✅ CORRECT: Use getAll() to get all records
        $posts = $this->model->getAll();

        // ❌ WRONG: Don't use all() - method doesn't exist!
        // $posts = $this->model->all();  // ❌ ERROR!

        Response::render(__DIR__ . '/Views/list_page.php', [
            'posts' => $posts
        ]);
    }

    #[RequestAction('view')]
    public function viewPost()
    {
        // ✅ CORRECT: Get parameter safely
        $id = _absint($_GET['id'] ?? 0);

        // ❌ WRONG: Get::int() doesn't exist!
        // $id = Get::int('id');  // ❌ ERROR!

        // ✅ CORRECT: Use getById() to get single record
        $post = $this->model->getById($id);

        // ❌ WRONG: Don't use find() - method doesn't exist!
        // $post = $this->model->find($id);  // ❌ ERROR!

        // ✅ CORRECT: Always check if empty
        if ($post->isEmpty()) {
            Response::error('Post not found');
        }

        // ❌ WRONG: Don't use !$object
        // if (!$post) {  // ❌ Object always exists!

        Response::render(__DIR__ . '/Views/view_page.php', [
            'post' => $post
        ]);
    }

    // ============================================
    // FILTERING DATA
    // ============================================

    #[RequestAction('by-category')]
    public function postsByCategory()
    {
        $categoryId = _absint($_GET['category_id'] ?? 0);

        // ✅ CORRECT: Use query()->where('field = ?', [$value])->getResults()
        $posts = $this->model->query()
            ->where('category_id = ?', [$categoryId])
            ->order('created_at', 'desc')
            ->getResults();

        // ❌ WRONG: whereIs() doesn't exist!
        // $posts = $this->model->query()->whereIs('category_id', $categoryId)->getResults();  // ❌ ERROR!

        // ❌ WRONG: Don't use array syntax in where()!
        // $posts = $this->model->query()->where(['category_id' => $categoryId])->getResults();  // ❌ ERROR!

        Response::render(__DIR__ . '/Views/list_page.php', [
            'posts' => $posts
        ]);
    }

    // ============================================
    // COUNTING
    // ============================================

    #[RequestAction('stats')]
    public function getStats()
    {
        // ✅ CORRECT: Count all records
        $allPosts = $this->model->getAll();
        $totalPosts = $allPosts->count();

        // ✅ CORRECT: Count with conditions using query()->where()->getTotal()
        $publishedCount = $this->model->query()
            ->where('status = ?', ['published'])
            ->getTotal();

        $draftCount = $this->model->query()
            ->where('status = ?', ['draft'])
            ->getTotal();

        // ❌ WRONG: Don't use count() with conditions!
        // $published = $this->model->count(['status' => 'published']);  // ❌ ERROR!

        // ❌ WRONG: Don't use total() - use getTotal()!
        // $published = $this->model->query()->where('status = ?', ['published'])->total();  // ❌ ERROR!

        // ❌ WRONG: Don't use whereIs()!
        // $published = $this->model->query()->whereIs('status', 'published')->getTotal();  // ❌ ERROR!

        Response::json([
            'total' => $totalPosts,
            'published' => $publishedCount,
            'draft' => $draftCount
        ]);
    }

    // ============================================
    // SAVING DATA
    // ============================================

    #[RequestAction('save')]
    public function savePost()
    {
        // ✅ CORRECT: save() returns ['success' => bool, 'error' => string]
        // It handles fill() and validate() internally
        $result = $this->model->save();

        if ($result['success']) {
            Response::json([
                'success' => true,
                'message' => 'Post saved successfully',
                'modal' => ['action' => 'hide'],
                'reload_table' => 'idTablePosts'  // Use reload_table, not table => ['action'=>'reload', 'id'=>...]
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => $result['error']
            ]);
        }
    }

    // ❌ WRONG - Don't use fill() and validate() manually:
    /*
    $post = $this->model;
    $post->fill($_POST['data'] ?? []);
    if (!$post->validate()) {
        $errors = \App\MessagesHandler::getErrors();
        return Response::json(['success' => false, 'errors' => $errors]);
    }
    if ($post->save()) {
        Response::json(['success' => true]);
    }
    */

    // ============================================
    // DELETING DATA
    // ============================================

    #[RequestAction('delete')]
    public function deletePost()
    {
        $id = _absint($_GET['id'] ?? 0);

        // ✅ CORRECT: Use delete() with ID
        if ($this->model->delete($id)) {
            Response::json(['success' => true]);
        } else {
            Response::json([
                'success' => false,
                'error' => $this->model->getLastError()
            ]);
        }
    }

    // ============================================
    // WORKING WITH RELATIONSHIPS
    // ============================================

    #[RequestAction('with-comments')]
    public function postsWithComments()
    {
        $commentModel = $this->module->getAdditionalModel('Comment');

        // ✅ CORRECT: Get all posts
        $posts = $this->model->getAll();

        // ✅ CORRECT: Count comments for each post
        foreach ($posts as $post) {
            $post->comment_count = $commentModel->query()
                ->where('post_id = ?', [$post->id])
                ->getTotal();
        }

        // ❌ WRONG examples:
        // $posts = $this->model->all();  // ❌ Method doesn't exist!
        // $post->comment_count = $commentModel->count(['post_id' => $post->id]);  // ❌ Wrong!
        // $post->comment_count = $commentModel->query()->where('post_id = ?', [$post->id])->total();  // ❌ Use getTotal()!
        // $post->comment_count = $commentModel->query()->whereIs('post_id', $post->id)->getTotal();  // ❌ whereIs() doesn't exist!

        Response::render(__DIR__ . '/Views/list_page.php', [
            'posts' => $posts
        ]);
    }
}

// ============================================
// SUMMARY - CORRECT METHODS
// ============================================
/*
GET ALL:        $model->getAll()                              NOT all()
GET BY ID:      $model->getById($id)                          NOT find()
FILTER:         $model->query()->where('field = ?', [$value])->getResults()   NOT whereIs()!
COUNT ALL:      $model->getAll()->count()
COUNT FILTER:   $model->query()->where('field = ?', [$value])->getTotal()     NOT count([]), total(), or whereIs()!
SAVE:           $result = $model->save();                     Returns ['success' => bool, 'error' => string]
                if ($result['success']) { ... }               NOT: $model->fill($data); if ($model->save()) { ... }
DELETE:         $model->delete($id)
CHECK EMPTY:    $model->isEmpty()                             NOT getEmpty() or !$object!
GET PARAM:      _absint($_GET['id'] ?? 0)                     NOT Get::int()!
RELOAD TABLE:   'reload_table' => 'idTableName'               NOT 'table' => ['action'=>'reload', 'id'=>'...']

CRITICAL ERRORS TO AVOID:
- ❌ NEVER use: $model->all()                     → ✅ Use: $model->getAll()
- ❌ NEVER use: $model->find($id)                 → ✅ Use: $model->getById($id)
- ❌ NEVER use: ->where(['field' => $value])      → ✅ Use: ->where('field = ?', [$value])
- ❌ NEVER use: ->whereIs('field', $value)        → ✅ Use: ->where('field = ?', [$value])
- ❌ NEVER use: ->total()                         → ✅ Use: ->getTotal()
- ❌ NEVER use: if (!$object)                     → ✅ Use: if ($object->isEmpty())
- ❌ NEVER use: $object->getEmpty()               → ✅ Use: $object->isEmpty()
- ❌ NEVER use: Get::int('id')                    → ✅ Use: _absint($_GET['id'] ?? 0)
- ❌ NEVER use: fill() + validate() manually      → ✅ save() handles it automatically and returns array
- ❌ NEVER use: 'table' => ['action'=>'reload']   → ✅ Use: 'reload_table' => 'idTableName'
*/
