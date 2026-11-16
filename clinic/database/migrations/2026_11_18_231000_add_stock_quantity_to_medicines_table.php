<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('medicines', 'stock_quantity')) {
            Schema::table('medicines', function (Blueprint $table) {
                $table->integer('stock_quantity')->default(0)->after('price');
            });

            // Initialize stock_quantity from existing batches if any
            if (Schema::hasTable('medicine_batches')) {
                $stocks = DB::table('medicine_batches')
                    ->select('medicine_id', DB::raw('SUM(remaining) as total_stock'))
                    ->groupBy('medicine_id')
                    ->get();

                foreach ($stocks as $s) {
                    DB::table('medicines')
                        ->where('id', $s->medicine_id)
                        ->update(['stock_quantity' => (int) $s->total_stock]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('medicines', 'stock_quantity')) {
            Schema::table('medicines', function (Blueprint $table) {
                $table->dropColumn('stock_quantity');
            });
        }
    }
};
