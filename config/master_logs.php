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
            'label' => 'DNS & SSL',
            'description' => 'DNS Update, SSL Apply, verify, and Cloudflare A record changes',
        ],
        'resolve' => [
            'label' => 'Subdomain checks',
            'description' => 'CRM tenant resolve API — each host lookup from master DB',
        ],
    ],

    'max_view_lines' => (int) env('MASTER_LOG_VIEW_LINES', 5000),

    'max_view_bytes' => (int) env('MASTER_LOG_VIEW_BYTES', 2_097_152),

];
