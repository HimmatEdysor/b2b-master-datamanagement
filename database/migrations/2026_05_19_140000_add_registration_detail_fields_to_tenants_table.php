<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('company_website')->nullable()->after('registration_notes');
            $table->string('address_line')->nullable()->after('company_website');
            $table->string('city', 120)->nullable()->after('address_line');
            $table->string('state', 120)->nullable()->after('city');
            $table->string('country', 120)->nullable()->after('state');
            $table->string('contact_designation')->nullable()->after('contact_phone');
            $table->string('business_type', 120)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'company_website',
                'address_line',
                'city',
                'state',
                'country',
                'contact_designation',
                'business_type',
            ]);
        });
    }
};
