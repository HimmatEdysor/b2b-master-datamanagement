<?php

namespace App\Console\Commands;

use App\Services\TenantDbAdminCapabilityService;
use App\Support\TenantDbAdmin;
use Illuminate\Console\Command;

class TenantDbAdminCheckCommand extends Command
{
    protected $signature = 'tenant:db-admin-check';

    protected $description = 'Check TENANT_DB_* connection and privileges for tenant provisioning (RDS)';

    public function handle(TenantDbAdminCapabilityService $capabilities): int
    {
        $audit = $capabilities->audit();

        $this->info($audit['summary']);
        $this->line('Build: '.TenantDbAdminCapabilityService::CHECK_BUILD);
        $this->line('Provision test DB: '.TenantDbAdmin::provisionCheckDatabaseName());
        $this->newLine();

        foreach ($audit['checks'] as $check) {
            $icon = $check['ok'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line(" {$icon} {$check['name']}: {$check['detail']}");
        }

        $this->newLine();
        $this->line('Host: '.$audit['config']['host'].':'.$audit['config']['port']);
        $this->line('User: '.$audit['config']['user']);
        $this->line('Template: '.$audit['config']['template']);
        $this->line('Password source: '.$audit['config']['password_source']);
        $this->line('Shared tenant credentials: '.(TenantDbAdmin::usesSharedTenantCredentials() ? 'yes (RDS)' : 'no (dedicated user per company)'));

        if (! $audit['ok']) {
            $this->newLine();
            $source = $audit['config']['password_source'] ?? '';
            if ($source !== '') {
                $this->warn(app(TenantDbAdminCapabilityService::class)->passwordSourceHint($source));
            }
            $this->warn('If connection fails (1045): password wrong, user missing, or host not allowed from app server.');
            $this->warn('Use @\'%\' (any host) for EC2 → RDS, not only @\'localhost\'.');
            $this->newLine();
            $this->line('<comment>--- Setup SQL (run as RDS master user) ---</comment>');
            $this->line($audit['setup_sql']);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Provisioning can run. Queue: php artisan horizon:terminate after .env changes.');

        return self::SUCCESS;
    }
}
