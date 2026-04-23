<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_jalan', function (Blueprint $table) {
            // Nama orang yang menerima barang (string bebas, bukan FK)
            $table->string('received_by_name')->nullable()->after('received_by_user');
        });
    }

    public function down(): void
    {
        Schema::table('surat_jalan', function (Blueprint $table) {
            $table->dropColumn('received_by_name');
        });
    }
};
