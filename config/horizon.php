<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same Redis instance.
    |
    */

    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web', 'auth', 'admin'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | You may wish to display all the jobs that have been waiting on your
    | queues for longer than these specific thresholds. By default, the
    | number of seconds to wait is 60 seconds.
    |
    */

    'waits' => [
        'redis:default' => 60,
        'redis:critical' => 5,
        'redis:high' => 30,
        'redis:low' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Trimming Periods
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon
    | to persist the recent and failed jobs. Typically, recent jobs are
    | kept for one hour while all failed jobs are stored for a week.
    |
    */

    'trim' => [
        'recent' => 60,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | long-running jobs out in the background.
    |
    */

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'critical', 'high'],
                'balance' => 'simple',
                'processes' => 10,
                'tries' => 3,
                'nice' => 0,
            ],
            'supervisor-2' => [
                'connection' => 'redis',
                'queue' => ['low'],
                'balance' => 'simple',
                'processes' => 3,
                'tries' => 3,
                'nice' => 10,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'critical', 'high', 'low'],
                'balance' => 'simple',
                'processes' => 3,
                'tries' => 3,
                'nice' => 0,
            ],
        ],

        'testing' => [
            'supervisor-1' => [
                'connection' => 'sync',
                'queue' => ['default'],
                'balance' => 'simple',
                'processes' => 1,
                'tries' => 1,
                'nice' => 0,
            ],
        ],
    ],

];