<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 32)->default('active');
            $table->string('database_name');
            $table->string('database_host')->nullable();
            $table->unsignedSmallInteger('database_port')->default(3306);
            $table->string('database_username')->nullable();
            $table->text('database_password')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->string('primary_color', 32)->nullable();
            $table->string('support_email')->nullable();
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('subscription_status', 32)->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamp('last_migration_at')->nullable();
            $table->string('migration_status', 32)->nullable();
            $table->text('migration_error')->nullable();
            $table->text('provision_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
