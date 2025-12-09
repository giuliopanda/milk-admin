<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Make your first job
 * @guide developer
 * @order 80
 * @tags Cron, jobs, jobs_contract, Get::make, Hooks::set, Get make, Hooks set, jobs-init, scheduling
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
<h1>Make your first job</h1>
<p class="text-muted">Revision: 2025/10/20</p>

<div class="alert alert-warning">
    Warning! For cron to work, you need to install the <a href="https://www.milkadmin.org/download-modules/" target="_blank">Job module</a> and enable cron in PHP!

    <p>MilkAdmin has minimal cron management, consisting only of providing the file to which the cron is associated. This file simply calls hooks that can be registered at various points in the code.</p>

</div>

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

   <p>Go to milkadmin_local/functions.php and add the following code:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Get;
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
<p><a href="<?php echo Route::url('?page=docs&action=User/Modules/Modules-cron'); ?>">Go to the Cron Module Documentation</a> for more information.</p>
</div>
