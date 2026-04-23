<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // ppn_percent: 0 = tidak pakai PPN, 11 = PPN 11%, dst
            $table->decimal('ppn_percent', 5, 2)->default(0)->after('total_amount');
            $table->decimal('ppn_amount', 15, 2)->default(0)->after('ppn_percent');
            $table->decimal('grand_total', 15, 2)->default(0)->after('ppn_amount');
        });

        // Isi grand_total dari data lama (yang tidak pakai PPN)
        \Illuminate\Support\Facades\DB::statement('
            UPDATE purchase_orders SET grand_total = total_amount WHERE grand_total = 0
        ');
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['ppn_percent', 'ppn_amount', 'grand_total']);
        });
    }
};
