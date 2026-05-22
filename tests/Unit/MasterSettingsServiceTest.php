<?php

namespace Tests\Unit;

use App\Models\MasterSetting;
use App\Services\MasterSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_setting_overrides_config(): void
    {
        config(['master.custom_domain_server_ip' => '1.2.3.4']);

        MasterSetting::query()->create([
            'key' => 'custom_domain_server_ip',
            'value' => '203.0.113.50',
        ]);

        app(MasterSettingsService::class)->clearCache();
        app(MasterSettingsService::class)->applyToConfig();

        $this->assertSame('203.0.113.50', config('master.custom_domain_server_ip'));
    }

    public function test_empty_save_removes_override(): void
    {
        MasterSetting::query()->create([
            'key' => 'custom_domain_server_ip',
            'value' => '203.0.113.50',
        ]);

        $service = app(MasterSettingsService::class);
        $service->save(['custom_domain_server_ip' => '']);
        $service->applyToConfig();

        $this->assertNull(MasterSetting::query()->where('key', 'custom_domain_server_ip')->first());
    }
}
