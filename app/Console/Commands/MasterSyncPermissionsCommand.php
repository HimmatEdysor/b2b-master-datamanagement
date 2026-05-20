<?php

namespace App\Console\Commands;

use App\Services\MasterPermissionSyncService;
use Illuminate\Console\Command;

class MasterSyncPermissionsCommand extends Command
{
    protected $signature = 'master:sync-permissions';

    protected $description = 'Sync permission rows from config/master_permissions.php';

    public function handle(MasterPermissionSyncService $sync): int
    {
        $result = $sync->sync();
        $this->info("Permissions in database: {$result['total']}");
        $this->info("Newly inserted: {$result['inserted']}");

        return self::SUCCESS;
    }
}
