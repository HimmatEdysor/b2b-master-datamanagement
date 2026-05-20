<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->json('features')->nullable()->after('limits');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            $table->boolean('is_featured')->default(false)->after('sort_order');
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body')->nullable();
            $table->string('status', 16)->default('draft');
            $table->boolean('show_in_nav')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['description', 'features', 'sort_order', 'is_featured']);
        });
    }
};
