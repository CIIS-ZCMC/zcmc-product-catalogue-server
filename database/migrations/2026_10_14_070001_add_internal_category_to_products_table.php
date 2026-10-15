<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('internal_category_id')->nullable()->after('category_key')->constrained('internal_categories')->nullOnDelete();
            $table->string('internal_category_code', 50)->nullable()->after('internal_category_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['internal_category_id']);
            $table->dropColumn(['internal_category_id', 'internal_category_code']);
        });
    }
};
