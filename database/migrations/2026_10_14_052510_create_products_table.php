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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_key')->nullable()->index();
            $table->string('erp_item_code')->nullable()->index();
            $table->string('bizbox_item_code')->nullable()->index();
            $table->string('philgeps_code')->nullable()->index();
            $table->string('unspsc_code', 50)->nullable()->index();
            $table->text('item_description');
            $table->string('generic_name')->nullable();
            $table->string('brand')->nullable();
            $table->string('uom', 50)->default('piece');
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->text('specifications')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('category_key')->references('key')->on('categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
