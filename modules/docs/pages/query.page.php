<?php
namespace Modules\docs;
use MilkCore\Route;
use MilkCore\Query;
/**
 * @title Query
 * @category Framework
 * @order 
 * @tags 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Query Class Documentation</h1>
    <p>The <code>Query</code> class is designed to facilitate the construction and management of SQL queries in PHP, offering a fluent and intuitive interface.</p>
    
    <h2>Introduction</h2>
    <p>The Query class allows you to specify components like <code>SELECT</code>, <code>WHERE</code>, <code>ORDER BY</code> and <code>LIMIT</code> in a simple and intuitive way.</p>

    <h2>Public Methods</h2>

    <h4>__construct($table, $db = null)</h4>
    <p>Class constructor. Initializes the table and database.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query = new Query('table_name');
    </code></pre>

    <h4>select($fields)</h4>
    <p>Specifies the fields to select.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query->select('field1, field2');
    </code></pre>

    <h4>has_select()</h4>
    <p>Checks if fields to select have been specified.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$hasSelect = $query->has_select();
    </code></pre>

    <h4>from($from)</h4>
    <p>Specifies other FROM tables or JOINs.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query->from('LEFT JOIN other_table ON table.id = other_table.id');
    </code></pre>

    <h4>where($where, $params = [], $operator = 'AND')</h4>
    <p>Adds WHERE conditions to the query.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query->where('field1 = ? AND field2 = ?', ['value1', 'value2']);
    </code></pre>

    <h4>has_where()</h4>
    <p>Checks if WHERE conditions have been specified.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$hasWhere = $query->has_where();
    </code></pre>

    <h4>order($field = '', $dir = 'asc')</h4>
    <p>Specifies the ordering of results.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Single field ordering 
$query->order('field1', 'desc');

// Multiple field ordering
$query->order(['field1', 'field2'], ['desc', 'asc']);
    </code></pre>

    <h4>has_order()</h4>
    <p>Checks if ordering has been specified.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$hasOrder = $query->has_order();
    </code></pre>

    <h4>limit($start, $limit)</h4>
    <p>Sets the limit of results to return.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query->limit(0, 10);
    </code></pre>

    <h4>has_limit()</h4>
    <p>Checks if a limit has been specified.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$hasLimit = $query->has_limit();
    </code></pre>

    <h4>group($group)</h4>
    <p>Sets the result grouping.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query->group('field1');
    </code></pre>

    <h4>has_group()</h4>
    <p>Checks if grouping has been specified.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$hasGroup = $query->has_group();
    </code></pre>

    <h4>get()</h4>
    <p>Builds and returns the SQL query and parameters to pass to bind_param.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
list($sql, $params) = $query->get();
    </code></pre>

    <h4>get_total()</h4>
    <p>Builds and returns the SQL query to calculate the total number of records.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
list($sqlTotal, $paramsTotal) = $query->get_total();
    </code></pre>

    <h4>clean($single = '')</h4>
    <p>Cleans the query parameters.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$query->clean('select');
    </code></pre>

      
    <h2>Usage</h2>
    
    
    <h4>Creating a Query</h4>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;?php
use MilkCore\Query;

// Creating an instance of the Query class for the 'users' table
$query = new Query('users');

// Building the query
$query->select('name', 'surname', 'sex', 'height')
      ->where('sex = ?', 'M')
      ->order('name', 'asc')
      ->limit(0, 10);

// Getting the SQL query and parameters
list($sql, $params) = $query->get();

echo $sql;
// Output: SELECT nome, cognome, email FROM utenti WHERE sez = ? ORDER BY name ASC LIMIT 0, 10
?&gt;</code></pre>

<p>This way you can manage more complex queries, for example by separating the various left join groups as in this example:</p>
<pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;?php
use MilkCore\Query;
$query = new Query('patient');
$query->select('`patient`.name, `patient`.surname, `patient`.sex')
      ->where('sex = ?', 'M')
      ->where('height > ?', '170', 'AND')
      ->order('name', 'asc')
      ->limit(0, 10);

$query->select('`visit`.date, `visit`.note')
    ->from('LEFT JOIN `visit` ON `patient`.`id` = `visit`.`patient_id`')
    ->where('date >= ?', '2021-01-01');

list($sql, $params) = $query->get();
echo $sql;
// Output: SELECT `patient`.name, `patient`.surname, `patient`.sex,`visit`.date, `visit`.note FROM `patient` LEFT JOIN `visit` ON `patient`.`id` = `visit`.`patient_id` WHERE (sex = 'M') OR (height > 170 ) AND (date >= '2021-01-01') ORDER BY `name` ASC LIMIT 0,10
?&gt;</code></pre>


    <h4>Calculating the Total Number of Records</h4>
        <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">
// Using the same instance of the Query class
list($sqlTotal, $paramsTotal) = $query->get_total();

echo $sqlTotal;
// Output: SELECT COUNT(*) FROM utenti WHERE eta &gt;= ?</code></pre>

<h4>Apply the queries</h4>
<pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">$db = Get::db();
$users = $db->get_results(...$query->get());
$total = $db->get_var(...$query->get_total());
</code></pre>
</div>