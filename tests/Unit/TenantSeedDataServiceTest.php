<?php

namespace Tests\Unit;

use App\Services\TenantSeedDataService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantSeedDataServiceTest extends TestCase
{
    #[Test]
    public function universities_blank_columns_are_configured(): void
    {
        $cols = config('master.tenant_universities_blank_columns');

        $this->assertContains('urm_name', $cols);
        $this->assertContains('urm_contact_no', $cols);
        $this->assertContains('urm_email', $cols);
    }

    #[Test]
    public function template_user_id_one_is_configured_for_seed(): void
    {
        $this->assertContains(1, config('master.tenant_seed_user_ids'));
    }
}
