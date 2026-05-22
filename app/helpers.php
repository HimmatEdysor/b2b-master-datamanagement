<?php

use App\Support\MasterAuth;

if (! function_exists('master_can')) {
    function master_can(string $permission): bool
    {
        return MasterAuth::can($permission);
    }
}

if (! function_exists('master_can_view_activity_logs')) {
    function master_can_view_activity_logs(): bool
    {
        return master_can('logs.view') || master_can('tenants.view');
    }
}
