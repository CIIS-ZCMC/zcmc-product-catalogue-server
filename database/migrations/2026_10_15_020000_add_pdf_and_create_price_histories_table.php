<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add tech_specs_pdf_path to products table
        Schema::table('products', function (Blueprint $table) {
            $table->string('tech_specs_pdf_path')->nullable()->after('other_specs');
        });

        // 2. Create product_price_histories table for inflation & price tracking
        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2);
            $table->decimal('price_change', 12, 2)->default(0);
            $table->decimal('percentage_change', 8, 2)->default(0);
            $table->string('reason')->nullable();
            $table->string('changed_by')->nullable();
            $table->timestamp('effective_date')->useCurrent();
            $table->timestamps();

            $table->index(['product_id', 'effective_date']);
        });

        // 3. Seed initial price history record for any existing products
        $existingProducts = DB::table('products')->select('id', 'unit_price', 'created_at')->get();
        foreach ($existingProducts as $product) {
            DB::table('product_price_histories')->insert([
                'product_id' => $product->id,
                'old_price' => null,
                'new_price' => $product->unit_price ?? 0,
                'price_change' => 0,
                'percentage_change' => 0,
                'reason' => 'Initial catalogue price',
                'effective_date' => $product->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tech_specs_pdf_path');
        });
    }
};
