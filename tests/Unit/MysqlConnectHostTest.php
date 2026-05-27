<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MysqlConnectHostTest extends TestCase
{
    #[Test]
    public function returns_ip_addresses_unchanged(): void
    {
        $this->assertSame('127.0.0.1', mysql_connect_host('127.0.0.1'));
    }

    #[Test]
    public function returns_localhost_unchanged_when_ipv4_not_forced(): void
    {
        putenv('DB_FORCE_IPV4=false');
        $_ENV['DB_FORCE_IPV4'] = 'false';

        $this->assertSame('localhost', mysql_connect_host('localhost'));
    }
}
