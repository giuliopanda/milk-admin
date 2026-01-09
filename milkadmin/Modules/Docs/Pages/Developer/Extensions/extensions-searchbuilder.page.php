<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
* @title SearchBuilder Extensions
* @order 14
* @tags extensions, searchbuilder-extensions, AbstractSearchBuilderExtension, search-hooks, configure, actionList, addSearchField, search-filters
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

   <h1>SearchBuilder Extensions</h1>

   <p>SearchBuilder Extensions allow you to add custom search fields, filters, and action lists to the search interface without modifying the SearchBuilder class itself. They extend the <code>AbstractSearchBuilderExtension</code> class.</p>

   <h2>Creating a SearchBuilder Extension</h2>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\MyExtension;

use App\Abstracts\AbstractSearchBuilderExtension;

class SearchBuilder extends AbstractSearchBuilderExtension
{
    // Configuration parameters
    protected string $label = 'Status:';
    protected string $filter_name = 'status_filter';

    // Hook called during builder configuration
    public function configure(object $builder): void
    {
        // Add search fields and filters
        $builder->actionList(
            $this->filter_name,
            $this->label,
            ['active' => 'Active', 'inactive' => 'Inactive'],
            'active'
        );
    }
}</code></pre>

   <h2 class="mt-4">Accessing the Model</h2>

   <p>Get the model instance from the SearchBuilder:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function configure(object $builder): void
{
    // Get the model
    $model = $builder->getModel();

    // Access model properties
    $fields = $model->getFields();

    // Get loaded model extensions
    $soft_del_ext = $model->getLoadedExtension('SoftDelete');
    if ($soft_del_ext) {
        $field_name = $soft_del_ext->field_name;
    }
}</code></pre>

   <h2 class="mt-4">Available Hooks</h2>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Hook</th>
            <th>Parameters</th>
            <th>Return</th>
            <th>Description</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><code>configure()</code></td>
            <td><code>object $builder</code></td>
            <td><code>void</code></td>
            <td>Add search fields, filters, and action lists</td>
         </tr>
      </tbody>
   </table>

   <h2 class="mt-4">SearchBuilder Methods</h2>

   <p>Common methods available in the SearchBuilder:</p>

   <table class="table table-bordered table-striped mt-3">
      <thead>
         <tr>
            <th>Method</th>
            <th>Parameters</th>
            <th>Description</th>
         </tr>
      </thead>
      <tbody>
         <tr>
            <td><code>actionList()</code></td>
            <td><code>string $name, string $label, array $options, mixed $default</code></td>
            <td>Add a dropdown filter with predefined options</td>
         </tr>
         <tr>
            <td><code>addSearchField()</code></td>
            <td><code>string $name, string $label, string $type = 'text'</code></td>
            <td>Add a search input field</td>
         </tr>
         <tr>
            <td><code>getModel()</code></td>
            <td>-</td>
            <td>Get the associated model instance</td>
         </tr>
      </tbody>
   </table>

   <h2 class="mt-4">Extension Parameters</h2>

   <p>Define configurable parameters as protected properties:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class SearchBuilder extends AbstractSearchBuilderExtension
{
    // Default values
    protected string $label = 'Status:';
    protected string $filter_name = 'show_deleted';
}

// Override in module configuration
$rule_builder->addExtension('SoftDelete', [
    'label' => 'Record Status:',
    'filter_name' => 'custom_filter'
]);</code></pre>

   <h2 class="mt-4">Example 1: SoftDelete SearchBuilder Extension</h2>

   <p>Adds a filter to show active or deleted records:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\SoftDelete;

use App\Abstracts\AbstractSearchBuilderExtension;

class SearchBuilder extends AbstractSearchBuilderExtension
{
    protected string $label = 'Status:';
    protected string $filter_name = 'show_deleted';

    public function configure(object $builder): void
    {
        // Add action list filter
        $builder->actionList(
            $this->filter_name,
            $this->label,
            [
                'active' => 'Active',
                'deleted' => 'Deleted'
            ],
            'active'
        );
    }
}</code></pre>

   <h2 class="mt-4">Example 2: Status Filter Extension</h2>

   <p>Add a filter for record status (published, draft, archived):</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\PublishStatus;

use App\Abstracts\AbstractSearchBuilderExtension;

class SearchBuilder extends AbstractSearchBuilderExtension
{
    protected string $label = 'Status:';
    protected string $filter_name = 'status';

    public function configure(object $builder): void
    {
        $builder->actionList(
            $this->filter_name,
            $this->label,
            [
                'all' => 'All',
                'published' => 'Published',
                'draft' => 'Draft',
                'archived' => 'Archived'
            ],
            'all'
        );
    }
}</code></pre>

   <h2 class="mt-4">Example 3: Category Filter Extension</h2>

   <p>Add a dynamic filter based on database categories:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\CategoryFilter;

use App\Abstracts\AbstractSearchBuilderExtension;
use App\Get;

class SearchBuilder extends AbstractSearchBuilderExtension
{
    protected string $label = 'Category:';
    protected string $filter_name = 'category_id';

    public function configure(object $builder): void
    {
        // Get categories from database
        $categories = $this->getCategories();

        if (empty($categories)) {
            return;
        }

        // Add filter with dynamic options
        $builder->actionList(
            $this->filter_name,
            $this->label,
            $categories,
            'all'
        );
    }

    private function getCategories(): array
    {
        $categories = ['all' => 'All Categories'];

        try {
            $db = Get::db();
            $results = $db->select('categories')
                ->fields(['id', 'name'])
                ->where('status = ?', ['active'])
                ->order('name ASC')
                ->getResults();

            foreach ($results as $cat) {
                $categories[$cat->id] = $cat->name;
            }
        } catch (\Exception $e) {
            // Fallback to empty list
        }

        return $categories;
    }
}</code></pre>

   <h2 class="mt-4">Example 4: Date Range Filter Extension</h2>

   <p>Add search fields for filtering by date range:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\DateRange;

use App\Abstracts\AbstractSearchBuilderExtension;

class SearchBuilder extends AbstractSearchBuilderExtension
{
    protected bool $add_date_from = true;
    protected bool $add_date_to = true;

    public function configure(object $builder): void
    {
        if ($this->add_date_from) {
            $builder->addSearchField(
                'date_from',
                'From Date:',
                'date'
            );
        }

        if ($this->add_date_to) {
            $builder->addSearchField(
                'date_to',
                'To Date:',
                'date'
            );
        }
    }
}</code></pre>

   <h2 class="mt-4">Example 5: User Filter Extension</h2>

   <p>Add a filter to show records by specific user (author, assignee, etc.):</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Extensions\UserFilter;

use App\Abstracts\AbstractSearchBuilderExtension;

class SearchBuilder extends AbstractSearchBuilderExtension
{
    protected string $label = 'Created By:';
    protected string $filter_name = 'created_by';
    protected bool $show_all_option = true;

    public function configure(object $builder): void
    {
        // Get list of users
        $users = $this->getUsersList();

        if (empty($users)) {
            return;
        }

        $builder->actionList(
            $this->filter_name,
            $this->label,
            $users,
            'all'
        );
    }

    private function getUsersList(): array
    {
        $users = [];

        if ($this->show_all_option) {
            $users['all'] = 'All Users';
        }

        try {
            $userModel = new \Modules\Auth\UserModel();
            $allUsers = $userModel->order('username')->getResults();

            foreach ($allUsers as $user) {
                $users[$user->id] = $user->username ?? "User #{$user->id}";
            }
        } catch (\Exception $e) {
            // Fallback to empty list
        }

        return $users;
    }
}</code></pre>

   <h2 class="mt-4">Common Use Cases</h2>

   <ul>
      <li><strong>Status filters</strong> - Filter by record status (active/inactive, published/draft)</li>
      <li><strong>Soft delete filters</strong> - Show/hide deleted records</li>
      <li><strong>Category filters</strong> - Filter by categories or tags</li>
      <li><strong>User filters</strong> - Filter by author, assignee, or related user</li>
      <li><strong>Date filters</strong> - Filter by date ranges or specific periods</li>
      <li><strong>Priority filters</strong> - Filter by priority levels (high, medium, low)</li>
      <li><strong>Type filters</strong> - Filter by content type or record type</li>
   </ul>

   <h2 class="mt-4">Best Practices</h2>

   <ul>
      <li>Use descriptive labels that clearly indicate the filter purpose</li>
      <li>Provide sensible default values (often 'all' or 'active')</li>
      <li>Use protected properties for configurable options</li>
      <li>Keep filter names consistent with field names when possible</li>
      <li>Handle database errors gracefully when loading dynamic options</li>
      <li>Consider performance when loading large option lists</li>
   </ul>

   <h2 class="mt-4">See Also</h2>

   <ul>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-introduction'); ?>">Extensions Introduction</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-getdatabuilder'); ?>">GetDataBuilder Extensions</a></li>
      <li><a href="<?php echo Route::url('?page=docs&action=Developer/Extensions/extensions-formbuilder'); ?>">FormBuilder Extensions</a></li>
   </ul>

</div>
