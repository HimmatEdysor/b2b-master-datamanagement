<?php

return [

    'brand' => 'Guarantee Admit',

    'tagline' => 'Multi-tenant B2B CRM for education consultancies & study abroad agencies',

    /*
    | Services provided by Guarantee Admit to partner companies.
    */
    'services' => [
        [
            'slug' => 'dedicated-crm',
            'icon' => 'crm',
            'title' => 'Dedicated CRM instance',
            'summary' => 'Your own branded B2B CRM portal — separate from other partners, fully under your company name.',
            'points' => [
                'Isolated tenant with own login and data',
                'Subdomain on your company slug (e.g. yourcompany.guaranteeadmit.com portal / yourcompany.main.guaranteeadmit.com API)',
                'Optional custom domain (white-label)',
            ],
        ],
        [
            'slug' => 'database-hosting',
            'icon' => 'database',
            'title' => 'Database provisioning',
            'summary' => 'We create and manage a dedicated MySQL database for each registered company.',
            'points' => [
                'Cloned from a proven CRM template',
                'Credentials managed securely in master portal',
                'Schema migrations applied on approval',
            ],
        ],
        [
            'slug' => 'branding',
            'icon' => 'brand',
            'title' => 'White-label branding',
            'summary' => 'Present the CRM as your own product to agents, counsellors, and students.',
            'points' => [
                'Custom logo, colours, and app name',
                'Support email shown inside CRM',
                'Consistent brand across all touchpoints',
            ],
        ],
        [
            'slug' => 'lead-management',
            'icon' => 'leads',
            'title' => 'Lead & application management',
            'summary' => 'End-to-end pipeline for study abroad leads — enquiry to enrolment.',
            'points' => [
                'Student / lead profiles and follow-ups',
                'Application tracking and document checklist',
                'Team assignments and activity history',
            ],
        ],
        [
            'slug' => 'agents',
            'icon' => 'team',
            'title' => 'Agents & branch management',
            'summary' => 'Scale your consultancy with role-based access for counsellors and branches.',
            'points' => [
                'User roles and permissions',
                'Multi-branch visibility and reporting',
                'Agent performance insights',
            ],
        ],
        [
            'slug' => 'integrations',
            'icon' => 'api',
            'title' => 'Tenant API & integrations',
            'summary' => 'Single resolve API connects your subdomain to the correct database automatically.',
            'points' => [
                'Host-based tenant resolution',
                'Secure API token for main CRM',
                'Ready for custom integrations per plan',
            ],
        ],
        [
            'slug' => 'onboarding',
            'icon' => 'onboard',
            'title' => 'Onboarding & approval',
            'summary' => 'Structured registration and review before your CRM goes live.',
            'points' => [
                'Online company registration form',
                'Admin review and approval workflow',
                'Provisioning status and error handling',
            ],
        ],
        [
            'slug' => 'support',
            'icon' => 'support',
            'title' => 'Plans & support',
            'summary' => 'Flexible subscription tiers with the help you need to grow.',
            'points' => [
                'Starter, Growth, and Enterprise plans',
                'Email and priority support by tier',
                'Billing and subscription managed centrally',
            ],
        ],
    ],

    'stats' => [
        ['value' => 'Multi-tenant', 'label' => 'Architecture'],
        ['value' => 'Isolated DB', 'label' => 'Per company'],
        ['value' => 'API-ready', 'label' => 'Tenant resolve'],
        ['value' => '24/7', 'label' => 'Cloud hosted'],
    ],

    /*
    | Tenant / CRM header logo (upload + crop).
    */
    'logo' => [
        'aspect_width' => 3,
        'aspect_height' => 1,
        'aspect_ratio' => 3,
        'output_width' => 360,
        'output_height' => 120,
        'max_upload_kb' => 5120,
        'mimes' => ['jpeg', 'jpg', 'png', 'webp'],
    ],

    'favicon' => [
        'output_size' => 64,
        'max_upload_kb' => 512,
        'mimes' => ['png', 'jpg', 'jpeg', 'webp', 'ico', 'svg'],
    ],

];
