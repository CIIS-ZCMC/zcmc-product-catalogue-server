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
        Schema::create('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('key')->primary();
            $table->unsignedBigInteger('parent_key')->nullable()->index();
            $table->string('code', 50)->nullable()->index();
            $table->string('title', 500);
            $table->text('definition')->nullable();
            $table->integer('level')->default(0)->index();
            $table->string('path', 1000)->nullable();
            $table->timestamps();

            $table->foreign('parent_key')->references('key')->on('categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
