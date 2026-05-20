<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('host')->unique();
            $table->string('type', 32)->default('subdomain'); // subdomain, custom, primary
            $table->boolean('is_primary')->default(false);
            $table->timestamp('dns_verified_at')->nullable();
            $table->string('ssl_status', 32)->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
