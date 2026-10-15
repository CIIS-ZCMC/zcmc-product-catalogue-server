<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('data/unspsc_categories.csv');
        if (!file_exists($csvPath)) {
            $csvPath = database_path('data/categories.csv');
        }

        if (!file_exists($csvPath)) {
            $this->command->error("Categories CSV file not found at: {$csvPath}");
            return;
        }

        $this->command->info("Importing UNSPSC categories from: {$csvPath}");

        DB::disableQueryLog();
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::statement('SET UNIQUE_CHECKS=0;');
        DB::table('categories')->truncate();

        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle); // Read CSV header

        $batch = [];
        $batchSize = 500;
        $totalInserted = 0;
        $seenKeys = [];
        $now = now()->toDateTimeString();

        DB::beginTransaction();

        while (($row = fgetcsv($handle)) !== false) {
            if (!isset($row[0]) || !is_numeric($row[0])) {
                continue;
            }

            $key = (int) trim($row[0]);
            if (isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;
            $parentKey = !empty($row[1]) && is_numeric($row[1]) ? (int) trim($row[1]) : null;
            $code = !empty($row[2]) ? trim($row[2]) : null;
            $title = isset($row[3]) ? trim($row[3]) : '';
            $level = isset($row[4]) && is_numeric($row[4]) ? (int) $row[4] : 0;
            $path = isset($row[5]) && !empty($row[5]) ? trim($row[5]) : "/{$key}/";
            $definition = isset($row[6]) && !empty($row[6]) ? trim($row[6]) : null;

            $batch[] = [
                'key' => $key,
                'parent_key' => $parentKey,
                'code' => $code,
                'title' => $title,
                'definition' => $definition,
                'level' => $level,
                'path' => $path,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $batchSize) {
                DB::table('categories')->insert($batch);
                $totalInserted += count($batch);
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            DB::table('categories')->insert($batch);
            $totalInserted += count($batch);
        }

        DB::commit();
        fclose($handle);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        DB::statement('SET UNIQUE_CHECKS=1;');

        $this->command->info("Successfully imported {$totalInserted} UNSPSC categories into database.");
    }
}
