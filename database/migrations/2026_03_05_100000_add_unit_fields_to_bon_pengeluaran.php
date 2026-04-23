<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bon_pengeluaran', function (Blueprint $table) {
            $table->string('unit_code', 50)->nullable()->after('notes');
            $table->string('unit_type', 100)->nullable()->after('unit_code');
            $table->decimal('hm_km', 10, 2)->nullable()->after('unit_type');
            $table->string('mechanic', 150)->nullable()->after('hm_km');
            $table->string('po_number', 100)->nullable()->after('mechanic');
        });
    }

    public function down(): void
    {
        Schema::table('bon_pengeluaran', function (Blueprint $table) {
            $table->dropColumn(['unit_code', 'unit_type', 'hm_km', 'mechanic', 'po_number']);
        });
    }
};
