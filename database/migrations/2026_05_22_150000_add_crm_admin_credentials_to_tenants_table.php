<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('crm_admin_email')->nullable()->after('database_password');
            $table->text('crm_admin_password')->nullable()->after('crm_admin_email');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['crm_admin_email', 'crm_admin_password']);
        });
    }
};
