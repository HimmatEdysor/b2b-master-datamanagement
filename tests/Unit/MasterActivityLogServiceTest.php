<?php

namespace Tests\Unit;

use App\Services\MasterActivityLogService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MasterActivityLogServiceTest extends TestCase
{
    #[Test]
    public function it_writes_and_reads_daily_log_file(): void
    {
        $base = storage_path('framework/testing/master-activity-'.uniqid());
        config(['master_logs.path' => $base]);

        $service = new MasterActivityLogService;
        $date = now()->format('Y-m-d');

        $service->database('test_action', 'ok', 'Hello log line');

        $this->assertFileExists($base.'/database/'.$date.'.log');
        $content = $service->readLog('database', $date);
        $this->assertStringContainsString('action=test_action', $content);
        $this->assertStringContainsString('Hello log line', $content);

        $all = $service->listAllLogFiles();
        $this->assertCount(1, $all);
        $this->assertSame('database', $all[0]['channel']);
        $this->assertSame($date, $all[0]['date']);

        $this->assertTrue($service->isValidChannel('database'));
        $this->assertFalse($service->isValidChannel('../etc'));

        @unlink($base.'/database/'.$date.'.log');
        @rmdir($base.'/database');
        @rmdir($base);
    }
}
