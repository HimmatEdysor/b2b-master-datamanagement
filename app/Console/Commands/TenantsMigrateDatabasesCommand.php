<?php

namespace App\Console\Commands;

use App\Services\TenantCrmMigrateService;
use Illuminate\Console\Command;

class TenantsMigrateDatabasesCommand extends Command
{
    protected $signature = 'tenants:migrate-databases
                            {slug? : Run only for this company slug (all companies if omitted)}
                            {--force : Run without confirmation}';

    protected $description = 'Run B2B CRM Laravel migrations (php artisan migrate --force) on every tenant database';

    public function handle(TenantCrmMigrateService $migrator): int
    {
        $slug = $this->argument('slug');
        $crmPath = config('master.tenant_crm_path');

        if (! is_string($crmPath) || $crmPath === '' || ! is_dir($crmPath)) {
            $this->error('TENANT_CRM_PATH is not set or invalid. Point it at the B2B CRM Laravel root (folder with artisan).');
            $this->line('Monorepo default: parent of master-portal when it contains artisan.');

            return self::FAILURE;
        }

        $this->info('CRM project: '.$crmPath);
        $this->line('Per database: php artisan migrate --force');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Run migrations on all matching company databases now?', true)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $this->line('Running migrations (one company at a time)…');
        $this->newLine();

        $summary = $migrator->migrateAll(slug: $slug);

        foreach ($summary['results'] as $row) {
            $label = "[{$row['slug']}] {$row['database_name']}";
            if ($row['ok']) {
                $this->line("<info>OK</info> {$label}");
            } else {
                $this->line("<error>FAILED</error> {$label}");
                $this->line('  '.$row['message']);
            }
        }

        $this->newLine();
        $this->info("Succeeded: {$summary['ok_count']}");
        if ($summary['fail_count'] > 0) {
            $this->error("Failed: {$summary['fail_count']}");
        }

        if ($summary['total_eligible'] > $summary['run_count']) {
            $this->warn("Only {$summary['run_count']} of {$summary['total_eligible']} companies were processed (TENANT_CRM_MIGRATE_BULK_MAX_TENANTS cap).");
        }

        if ($summary['run_count'] === 0) {
            $this->warn('No company databases found to migrate.');

            return self::FAILURE;
        }

        return $summary['fail_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
