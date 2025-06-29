<?php
namespace Modules\Jobs;
use Modules\Jobs\JobsServices;
use MilkCore\Get;
!defined('MILK_DIR') && die(); // Avoid direct access

$jobs_contract = Get::make('jobs_contract');

$jobs_contract::register(
    'cleanup_logs', 
    [JobsServices::class, 'cleanup_logs'],
    '30 1 * * *',
    'Log cleanup'
);

$jobs_contract::register(
    'check_errors_jobs', 
    [JobsServices::class, 'check_errors_jobs'],
    '0 0 * * *',
    'Verify if there are errors in the jobs and send an email to the admin'
);