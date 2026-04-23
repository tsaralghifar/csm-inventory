<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_material', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();                         // PM-20260301-0001
            $table->foreignId('warehouse_id')->constrained('warehouses'); // Site asal permintaan
            $table->foreignId('requested_by')->constrained('users');      // Mekanik / Admin Site
            $table->foreignId('site_approved_by')->nullable()->constrained('users');  // Admin Site
            $table->foreignId('ho_approved_by')->nullable()->constrained('users');    // Admin HO
            $table->enum('status', ['draft', 'pending_site', 'pending_ho', 'approved', 'rejected'])->default('draft');
            $table->timestamp('site_approved_at')->nullable();
            $table->timestamp('ho_approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->date('needed_date')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('permintaan_material_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_material_id')->constrained('permintaan_material')->cascadeOnDelete();
            $table->string('nama_barang');           // Nama bebas / deskripsi
            $table->string('kode_unit')->nullable(); // Kode unit / alat berat
            $table->string('tipe_unit')->nullable(); // Tipe unit
            $table->decimal('qty', 12, 2);
            $table->string('satuan', 50);            // Pcs, Liter, Set, dll
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_material_items');
        Schema::dropIfExists('permintaan_material');
    }
};
