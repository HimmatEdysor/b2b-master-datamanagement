<?php

return [

    /*
    | Daily log files: storage/logs/master-activity/{channel}/Y-m-d.log
    */
    'path' => storage_path('logs/master-activity'),

    'channels' => [
        'database' => [
            'label' => 'Database',
            'description' => 'Create, clone, migrate, and DB user provisioning',
        ],
        's3' => [
            'label' => 'S3',
            'description' => 'Tenant S3 folder creation and storage',
        ],
        'domain' => [
            'label' => 'Domains',
            'description' => 'Subdomain and custom domain changes',
        ],
        'dns' => [
            'label' => 'DNS',
            'description' => 'DNS / hostname setup notes when domains are added',
        ],
    ],

    'max_view_lines' => (int) env('MASTER_LOG_VIEW_LINES', 2000),

];
