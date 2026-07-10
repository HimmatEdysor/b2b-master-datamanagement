<?php

return [

    'default_admin_email' => env('MASTER_DEFAULT_ADMIN_EMAIL', 'himmat@edysor'),
    'default_admin_password' => env('MASTER_DEFAULT_ADMIN_PASSWORD', 'himmat@edysor.in'),
    'default_admin_name' => env('MASTER_DEFAULT_ADMIN_NAME', 'Himmat'),

    'groups' => [
        'Dashboard' => ['dashboard.view'],
        'Companies' => [
            'tenants.view', 'tenants.create', 'tenants.edit', 'tenants.approve', 'tenants.delete',
            'logs.view',
        ],
        'Activity logs' => ['logs.view'],
        'Subscription plans' => ['plans.view', 'plans.create', 'plans.edit'],
        'Website' => ['pages.view', 'pages.create', 'pages.edit', 'blog.view', 'blog.create', 'blog.edit'],
        'Support tickets' => ['tickets.view', 'tickets.reply', 'tickets.manage'],
        'Admin users' => ['users.view', 'users.create', 'users.edit', 'users.delete'],
        'Roles' => ['roles.view', 'roles.create', 'roles.edit'],
        'Permissions' => ['permissions.view', 'permissions.create', 'permissions.edit'],
        'Master settings' => ['settings.view', 'settings.edit'],
        'Queue (Horizon)' => ['horizon.view'],
    ],

    'super_admin_role' => 'super-admin',

    /** Roles that cannot be edited or deactivated (B2B-style protected system roles). */
    'protected_roles' => [
        'super-admin',
    ],

];
