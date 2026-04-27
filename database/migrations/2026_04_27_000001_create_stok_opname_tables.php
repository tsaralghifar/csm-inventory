<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_opname', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();                          // ADJ-20260427-0001
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->enum('status', ['draft', 'menunggu_approval', 'disetujui', 'ditolak'])->default('draft');
            $table->string('tipe', 50);                                    // Koreksi Opname, Temuan Stok, dll
            $table->string('no_referensi', 100)->nullable();               // nomor dokumen fisik
            $table->text('keterangan')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users');
            $table->timestamp('diajukan_at')->nullable();
            $table->timestamp('disetujui_at')->nullable();
            $table->date('tanggal_opname');
            $table->timestamps();
        });

        Schema::create('stok_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_id')->constrained('stok_opname')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('qty_sistem', 12, 2)->default(0);             // qty di sistem saat opname dibuat
            $table->decimal('qty_fisik', 12, 2)->default(0);              // qty hasil hitung fisik
            // selisih dihitung di Model (qty_fisik - qty_sistem)
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_opname_items');
        Schema::dropIfExists('stok_opname');
    }
};
