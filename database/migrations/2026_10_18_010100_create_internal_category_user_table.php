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
        Schema::create('internal_category_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('internal_category_id')->constrained('internal_categories')->cascadeOnDelete();

            $table->unique(['user_id', 'internal_category_id']);
        });

        // Existing non-admin users could previously write to every internal category.
        // Grant them all current categories so the new restriction does not lock them out;
        // an administrator can narrow the assignments afterwards.
        $categoryIds = DB::table('internal_categories')->pluck('id');
        $userIds = DB::table('users')->where('is_admin', false)->pluck('id');

        $rows = [];
        foreach ($userIds as $userId) {
            foreach ($categoryIds as $categoryId) {
                $rows[] = [
                    'user_id' => $userId,
                    'internal_category_id' => $categoryId,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('internal_category_user')->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_category_user');
    }
};
