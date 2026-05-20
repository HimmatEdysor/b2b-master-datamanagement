<?php

use App\Support\MasterAuth;

if (! function_exists('master_can')) {
    function master_can(string $permission): bool
    {
        return MasterAuth::can($permission);
    }
}
