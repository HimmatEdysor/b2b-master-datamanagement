<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('name');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone', 32)->nullable()->after('contact_email');
            $table->text('registration_notes')->nullable()->after('contact_phone');
            $table->timestamp('approved_at')->nullable()->after('provision_error');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->foreignId('approved_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'contact_name',
                'contact_email',
                'contact_phone',
                'registration_notes',
                'approved_at',
                'rejected_at',
                'approved_by',
            ]);
        });
    }
};
