<?php
namespace Modules\Docs\Pages;

use App\Theme;

/**
 * @title Cron Module Documentation
 * @guide user
 * @order 2
 * @tags cron, scheduler, jobs, background-tasks, automation, task-scheduling, intervals, execution, command-line, maintenance, JobsContract, cron-expressions, predefined-intervals, fluent-API, job-management, recurring-tasks, periodic-execution
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
   <h1>Cron Module Documentation</h1>
   <?php if (!is_dir(MILK_DIR . '/modules/jobs')) : ?>
      <div class="alert alert-danger">The <strong>jobs</strong> module is not installed. Please install it to use this module.</div>
   <?php else: ?>
      <p>This documentation provides a concise guide on how to implement scheduled tasks in your application using the Cron module.</p>
   <?php endif; ?>

<h2>Prerequisites</h2>
<p>Remember that you must have cron running and you must have added the line <br><code>* * * * * php <?php echo MILK_DIR; ?>/cron.php >/dev/null 2>&1</code><br> to your crontab file.</p>

<p>If you haven't done so, follow the tutorial on getting started.</p>

   <h2>Introduction</h2>

    <p>The Cron module provides a way to schedule recurring tasks in your application. It supports standard cron expressions, predefined intervals, and offers a fluent API for configuring job schedules.</p>

   <h2>Registering Cron Jobs</h2>
   <p>To register cron jobs in your application, you'll use the JobsContract service, which is accessible through the Get::make() method.</p>

   <h3>Basic Usage</h3>
   <p>Here's how to register a basic cron job:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Get the jobs contract service
$jobs_contract = Get::make('jobs_contract');

// Register a job that runs at 1:30 AM every day
$jobs_contract::register(
    'my_daily_report',          // Unique name for the job
    function() {                // Callback function to execute
        process_daily_reports();
        return true;            // Return true on success
    },
    '30 1 * * *',              // Cron schedule expression
    'Daily reporting process',  // Description (optional)
    true                        // Active status (optional)
);
   </code></pre>

   <h3>Output</h3>
    <p>L'output del cron job (i print, echo, ecc) viene salvato nella tabella jobs_executions dentro output.</p>
    <p>Se il job fallisce, viene salvato anche l'errore, se il jobs ritorna false viene registrato come failed.</p>

   <h3>Register Method Parameters</h3>
   <p>The register method accepts the following parameters:</p>
   <ul>
       <li><strong>$name</strong> (string): A unique name to identify the job</li>
       <li><strong>$callback</strong> (callable): The function or method to execute</li>
       <li><strong>$schedule</strong> (string|CronDateManager): When to run the job (cron expression, predefined interval, or CronDateManager instance)</li>
       <li><strong>$description</strong> (string, optional): Description of what the job does</li>
       <li><strong>$active</strong> (bool, optional): Whether the job is active (default: true)</li>
       <li><strong>$metadata</strong> (array, optional): Additional data to associate with the job</li>
   </ul>

   <h3>Using Predefined Intervals</h3>
   <p>You can use predefined intervals instead of cron expressions:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$jobs_contract = Get::make('jobs_contract');

// Register a job that runs hourly
$jobs_contract::register(
    'cleanup_temp_files',
    function() {
        cleanup_temporary_files();
        return true;
    },
    'hourly',               // Predefined interval
    'Temporary file cleanup'
);

// Available predefined intervals include:
// 'yearly', 'annually', 'monthly', 'weekly', 'daily', 'midnight',
// 'hourly', 'every_minute', 'every_5_minutes', 'every_10_minutes',
// 'every_15_minutes', 'every_30_minutes', 'twice_daily',
// 'weekdays', 'weekends'
   </code></pre>

   <h3>Using the Fluent API</h3>
   <p>For more complex schedules, you can use the fluent API:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$jobs_contract = Get::make('jobs_contract');

// Create a scheduler for complex scheduling
$scheduler = $jobs_contract::createScheduler()
    ->setMinutes(0)
    ->setHours(9)
    ->setDayOfWeek('1-5'); // Monday to Friday

// Register the job with the scheduler
$jobs_contract::register(
    'workday_morning_task',
    function() {
        send_morning_notifications();
        return true;
    },
    $scheduler,
    'Morning notification sender'
);
   </code></pre>

   <h2>Initialization Hooks and Environments</h2>
   <p>The system provides different initialization hooks depending on the execution environment:</p>
   
   <ul>
       <li><strong>init</strong> - Called when running in web environment (normal browser access)</li>
       <li><strong>cli_init</strong> - Called when running in command-line interface (CLI)</li>
       <li><strong>jobs_init</strong> - Called when running in cron job environment</li>
   </ul>

   <p>Each module's module class (extending AbstractModule) can implement these corresponding methods:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
class MyModuleModule extends \App\AbstractModule {
    // Called in web environment
    public function hookInit() {
        // Web-specific initialization
    }

    // Called in CLI environment
    public function cliInit() {
        // CLI-specific initialization
    }

    // Called in cron environment
    public function jobsInit() {
        // Cron-specific initialization
    }
}
   </code></pre>

   <p>When registering cron jobs, make sure to use the appropriate hook based on your environment. For cron jobs, use the <code>jobs-init</code> hook:</p>

   <h2>Implementing Custom Cron Jobs</h2>
   <p>The recommended place to add your custom cron jobs is in the <code>milkadmin_local/functions.php</code> file:</p>
   
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
&lt;?php
!defined('MILK_DIR') && die(); // Avoid direct access

// Register cron jobs
add_action('jobs-init', function() {
    // Get the jobs contract service
    $jobs_contract = Get::make('jobs_contract');
    
    // Register a job that runs daily at midnight
    $jobs_contract::register(
        'my_custom_daily_job',
        function() {
            // Your job logic here
            return true;
        },
        'daily',
        'Daily maintenance job'
    );
    
    // Register a job with a custom cron expression
    $jobs_contract::register(
        'my_custom_weekly_job',
        function() {
            // Your job logic here
            return true;
        },
        '0 3 * * 1', // Every Monday at 3:00 AM
        'Weekly processing job'
    );
});
   </code></pre>

   <h2>Cron Expression Format</h2>
   <p>The Cron module supports the standard cron expression format:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code>
* * * * *
│ │ │ │ │
│ │ │ │ └─── Day of week (0-6 or SUN-SAT)
│ │ │ └───── Month (1-12 or JAN-DEC)
│ │ └─────── Day of month (1-31)
│ └───────── Hour (0-23)
└─────────── Minute (0-59)
   </code></pre>
   
   <h2>Predefined Intervals</h2>
   <p>Instead of cron expressions, you can use these predefined intervals:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code>
'yearly'          => '0 0 1 1 *'         // At 00:00 on January 1st
'annually'        => '0 0 1 1 *'         // Same as yearly
'monthly'         => '0 0 1 * *'         // At 00:00 on day 1 of every month
'weekly'          => '0 0 * * 0'         // At 00:00 on Sunday
'daily'           => '0 0 * * *'         // At 00:00 every day
'midnight'        => '0 0 * * *'         // Same as daily
'hourly'          => '0 * * * *'         // At minute 0 of every hour
'every_minute'    => '* * * * *'         // Every minute
'every_5_minutes' => '*/5 * * * *'       // Every 5 minutes
'every_10_minutes'=> '*/10 * * * *'      // Every 10 minutes
'every_15_minutes'=> '*/15 * * * *'      // Every 15 minutes
'every_30_minutes'=> '*/30 * * * *'      // Every 30 minutes
'twice_daily'     => '0 0,12 * * *'      // At 00:00 and 12:00
'weekdays'        => '0 0 * * 1-5'       // At 00:00 on weekdays
'weekends'        => '0 0 * * 0,6'       // At 00:00 on weekends
   </code></pre>
   
   <h2>Advanced Scheduling with the Fluent API</h2>
   <p>For complex scheduling requirements, use the fluent API:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$jobs_contract = Get::make('jobs_contract');

// Create a scheduler for complex timing
$scheduler = $jobs_contract::createScheduler()
    ->setMinutes(30)
    ->setHours(14)
    ->setDayOfMonth(15)
    ->setMonth('1,4,7,10'); // January, April, July, October

// Register a job with the complex schedule
$jobs_contract::register(
    'quarterly_report',
    function() {
        generate_quarterly_report();
        return true;
    },
    $scheduler,
    'Quarterly report generator that runs at 2:30 PM on the 15th of each quarter'
);
   </code></pre>

   <h2>Special Characters in Cron Expressions</h2>
   <p>Cron expressions support several special characters:</p>
   <ul>
       <li><strong>*</strong> - matches any value (e.g., * in the hour field means "every hour")</li>
       <li><strong>,</strong> - value list separator (e.g., 1,3,5 means 1, 3, and 5)</li>
       <li><strong>-</strong> - range of values (e.g., 1-5 means 1, 2, 3, 4, and 5)</li>
       <li><strong>/</strong> - step values (e.g., */5 in minutes means every 5 minutes)</li>
   </ul>
   </code></pre>

   <h2>Job Execution Status and Return Function</h2>
   <p>Each job function receives a <code>$metadata</code> array parameter containing job-specific data and should return a boolean value. Here's how to implement your job function:</p>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
function myJobFunction($metadata) {
    try {
        // Your job logic here
        echo "Processing task..."; // This will be saved to the output column
        
        if (/* task successful */) {
            return true; // Indicates successful execution
        } else {
            return false; // Indicates failed execution
        }
    } catch (\Exception $e) {
        // The exception message will be saved to the errors column
        throw new \Exception("Job failed: " . $e->getMessage());
    }
}
   </code></pre>

   <h3>Function Implementation Details</h3>
   <ul>
       <li><strong>Parameters</strong> - The function receives a <code>$metadata</code> array containing job-specific data</li>
       <li><strong>Return Value</strong> - Must return <code>true</code> for success or <code>false</code> for failure</li>
       <li><strong>Exceptions</strong> - Throwing an exception will mark the job as failed and save the message to the errors column</li>
       <li><strong>Output</strong> - Any content echoed or printed will be captured and saved to the output column</li>
   </ul>

   <p>The cron system tracks the following information for each job:</p>
   <ul>
       <li><strong>Last run time</strong> - When the job was last executed</li>
       <li><strong>Next run time</strong> - When the job is scheduled to run next</li>
       <li><strong>Success/failure</strong> - Whether the last execution was successful</li>
       <li><strong>Error messages</strong> - Details of any failures</li>
       <li><strong>Output</strong> - Text output from the job execution</li>
   </ul>

   <h3>Automatic Job Disabling</h3>
   <p>For reliability and system protection, a cron job will be automatically disabled if it fails 3 consecutive times. This prevents problematic jobs from continuously consuming system resources. You'll need to manually re-enable the job after investigating and fixing the cause of the failures.</p>

   <h2>Best Practices</h2>
   <ul>
       <li><strong>Keep jobs short and focused</strong> - Long-running jobs should be broken down or use a queuing system</li>
       <li><strong>Handle failures gracefully</strong> - Implement proper error handling and logging</li>
       <li><strong>Consider job frequency</strong> - Don't schedule jobs to run too frequently unless necessary</li>
       <li><strong>Use descriptive job names</strong> - Names should indicate what the job does</li>
       <li><strong>Return true/false</strong> - Always return a boolean result from your job functions</li>
   </ul>

   <h2>Conclusion</h2>
   <p>The Cron module provides a flexible way to schedule recurring tasks in your application. Add your custom cron jobs in the <code>milkadmin_local/functions.php</code> file using the <code>Get::make('jobs_contract')</code> service, or create custom modules for more complex scheduling needs.</p>
</div>