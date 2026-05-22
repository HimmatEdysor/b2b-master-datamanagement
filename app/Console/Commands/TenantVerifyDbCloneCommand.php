<?php

namespace App\Console\Commands;

use App\Support\TenantDbAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class TenantVerifyDbCloneCommand extends Command
{
    protected $signature = 'tenant:verify-db-clone
        {--database= : Template DB name (default: TENANT_TEMPLATE_DATABASE)}';

    protected $description = 'Verify RDS-safe mysqldump (--skip-lock-tables) against the template database';

    public function handle(): int
    {
        TenantDbAdmin::assertCanProvision();

        $database = $this->option('database') ?: config('master.template_database');
        $command = TenantDbAdmin::mysqldumpCommand($database, schemaOnly: true);

        $this->info('Build: '.TenantDbAdmin::MYSQLDUMP_RDS_BUILD);
        $this->line('Command: '.TenantDbAdmin::mysqldumpCommandForLog($database, schemaOnly: true));
        $this->line('Host: '.TenantDbAdmin::host().':'.TenantDbAdmin::port());
        $this->line('User: '.TenantDbAdmin::username());
        $this->newLine();
        $this->info('Running schema-only mysqldump (stdout discarded) …');

        $timeout = min(120.0, (float) config('master.tenant_db_clone_timeout', 600));
        $result = Process::timeout($timeout)
            ->env(TenantDbAdmin::mysqlCliEnv())
            ->run($command);

        if ($result->successful() && trim($result->output()) !== '') {
            $this->info('OK — mysqldump works on RDS without FLUSH TABLES / RELOAD.');
            $this->line('Bytes: '.strlen($result->output()));
            $this->line('After deploy, run: php artisan horizon:terminate');

            return self::SUCCESS;
        }

        $this->error(TenantDbAdmin::cliFailureMessage($result, 'mysqldump'));

        return self::FAILURE;
    }
}
