<?php

namespace App\Console\Commands;

use App\Services\TenantDatabaseSchemaCloneService;
use App\Support\TenantDbAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class TenantVerifyDbCloneCommand extends Command
{
    protected $signature = 'tenant:verify-db-clone
        {--database= : Template DB name (default: TENANT_TEMPLATE_DATABASE)}';

    protected $description = 'Verify tenant DB schema clone (PDO default; optional mysqldump test)';

    public function handle(TenantDatabaseSchemaCloneService $schemaClone): int
    {
        TenantDbAdmin::assertCanProvision();

        $database = $this->option('database') ?: config('master.template_database');
        $method = TenantDbAdmin::cloneMethod();

        $this->info('Clone method: '.$method.' (set TENANT_DB_CLONE_METHOD=pdo on AWS RDS)');
        $this->line('Host: '.TenantDbAdmin::host().':'.TenantDbAdmin::port());
        $this->line('User: '.TenantDbAdmin::username());
        $this->newLine();

        try {
            TenantDbAdmin::adminPdo();
            $this->info('Admin PDO connection: OK');
        } catch (\PDOException $e) {
            $this->error(TenantDbAdmin::connectionErrorMessage($e));

            return self::FAILURE;
        }

        try {
            $inspect = $schemaClone->inspectTemplate($database);
            $this->info("Template [{$inspect['database']}]: {$inspect['tables']} tables, {$inspect['views']} views (PDO readable).");
        } catch (\Throwable $e) {
            $this->error('PDO: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($method !== 'mysqldump') {
            $this->info('OK — provisioning uses PDO (no mysqldump / FLUSH TABLES).');
            $this->line('Restart workers after deploy: php artisan horizon:terminate');

            return self::SUCCESS;
        }

        $this->warn('TENANT_DB_CLONE_METHOD=mysqldump — testing mysqldump …');
        $command = TenantDbAdmin::mysqldumpCommand($database, schemaOnly: true);
        $this->line('Command: '.TenantDbAdmin::mysqldumpCommandForLog($database, schemaOnly: true));

        $timeout = min(120.0, (float) config('master.tenant_db_clone_timeout', 600));
        $result = Process::timeout($timeout)
            ->env(TenantDbAdmin::mysqlCliEnv())
            ->run($command);

        if ($result->successful() && trim($result->output()) !== '') {
            $this->info('mysqldump OK ('.strlen($result->output()).' bytes).');

            return self::SUCCESS;
        }

        $this->error(TenantDbAdmin::cliFailureMessage($result, 'mysqldump'));
        $this->line('Use TENANT_DB_CLONE_METHOD=pdo on RDS.');

        return self::FAILURE;
    }
}
