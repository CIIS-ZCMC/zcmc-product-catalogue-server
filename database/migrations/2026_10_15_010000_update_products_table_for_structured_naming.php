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
            // Drop removed columns
            $table->dropColumn(['internal_category_code', 'generic_name', 'brand', 'remarks']);

            // Rename specifications to other_specs
            $table->renameColumn('specifications', 'other_specs');

            // Add structured standard naming fields
            $table->string('noun', 100)->nullable()->after('unspsc_code');
            $table->string('type', 100)->nullable()->after('noun');
            $table->string('material_composition', 255)->nullable()->after('type');
            $table->text('key_attributes')->nullable()->after('material_composition');
            $table->string('size', 100)->nullable()->after('key_attributes');
            $table->string('net_content', 100)->nullable()->after('size');
            $table->string('pack', 100)->nullable()->after('net_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'noun',
                'type',
                'material_composition',
                'key_attributes',
                'size',
                'net_content',
                'pack',
            ]);

            $table->renameColumn('other_specs', 'specifications');

            $table->string('internal_category_code', 50)->nullable()->after('internal_category_id');
            $table->string('generic_name')->nullable()->after('item_description');
            $table->string('brand')->nullable()->after('generic_name');
            $table->text('remarks')->nullable()->after('specifications');
        });
    }
};
