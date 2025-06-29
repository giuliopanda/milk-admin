<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Make your first job
 * @category Getting started
 * @order 20
 * @tags Cron, jobs, jobs_contract, Get::make, Hooks::set, Get make, Hooks set, jobs-init
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
<h1>Make your first job</h1>

<p>Before using the Cron module, ensure that:</p>
   <ul>
       <li>The cron service is enabled on your server</li>
       <li>You have the necessary permissions to create and modify cron jobs</li>
       <li>You have a database connection configured in your application</li>
   </ul>
   <p>Add the following line to your crontab file:</p>
   <p>In the shell, run the following command:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">crontab -e</code></pre>
   <p>Then add the following line:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">* * * * * php <?php echo MILK_DIR; ?>cron.php</code></pre>
   <p>Save and exit the editor. The cron job will now run every minute.</p>


<h2>Make your first job</h2>

   <p>Go to customizations/functions.php and add the following code:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use MilkCore\Get;
use MilkCore\Hooks;

!defined('MILK_DIR') && die(); // Avoid direct access
Hooks::set('jobs-init', function() {
    $jobs_contract = Get::make('jobs_contract');
    $jobs_contract::register(
        'my_first_jobs', 
        function($metadata) {
           // do something
          return true;
         },
        'hourly',
        'A description',
    );
});</code></pre>

<p>Go to jobs page in the admin panel and you will see your job.</p>
<p><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/cron.page'); ?>">Go to the Cron Module Documentation</a> for more information.</p>
</div>
