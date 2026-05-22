<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subdomain_check_stats', function (Blueprint $table) {
            $table->id();
            $table->string('host', 255)->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('slug', 64)->nullable()->index();
            $table->unsignedBigInteger('check_count')->default(0);
            $table->unsignedBigInteger('allowed_count')->default(0);
            $table->unsignedBigInteger('denied_count')->default(0);
            $table->unsignedBigInteger('not_found_count')->default(0);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_outcome', 32)->nullable();
            $table->string('last_code', 64)->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('first_checked_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_subdomain_check_logs', function (Blueprint $table) {
            $table->id();
            $table->string('host', 255)->index();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('slug', 64)->nullable();
            $table->string('outcome', 32);
            $table->unsignedSmallInteger('http_status');
            $table->string('code', 64)->nullable();
            $table->text('message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['host', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subdomain_check_logs');
        Schema::dropIfExists('tenant_subdomain_check_stats');
    }
};
