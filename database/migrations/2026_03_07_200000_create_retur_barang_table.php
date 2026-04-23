<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retur_barang', function (Blueprint $table) {
            $table->id();
            $table->string('retur_number', 50)->unique();

            // Referensi PO asal (wajib)
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();

            // Gudang sumber (dari mana barang diretur)
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            // Vendor yang menerima retur
            $table->string('vendor_name', 255);
            $table->string('vendor_contact', 255)->nullable();

            // Tanggal & info
            $table->date('retur_date');
            $table->text('alasan')->nullable();         // alasan retur umum
            $table->text('notes')->nullable();

            // Status: draft → confirmed
            $table->string('status', 30)->default('draft');
            // Status: draft = belum diproses, confirmed = stok sudah dikurangi

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('retur_barang_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retur_barang_id')->constrained('retur_barang')->cascadeOnDelete();

            // Referensi item master (nullable karena bisa barang non-master)
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();

            // Referensi item PO asal
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();

            $table->string('nama_barang', 255);
            $table->string('part_number', 100)->nullable();
            $table->string('kode_unit', 100)->nullable();
            $table->string('tipe_unit', 100)->nullable();

            $table->decimal('qty', 10, 2);
            $table->string('satuan', 50);
            $table->decimal('harga_satuan', 15, 2)->default(0);

            // Jenis: returnable = dikembalikan ke vendor, non_returnable = salah beli ditandai
            $table->enum('jenis', ['returnable', 'non_returnable'])->default('returnable');
            $table->text('alasan_item')->nullable();     // alasan per-item

            $table->timestamps();
        });

        // Tambah kolom is_salah_beli pada items untuk menandai barang salah beli
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_salah_beli')->default(false)->after('notes')->comment('Ditandai sebagai barang salah beli yang tidak bisa diretur');
            $table->text('salah_beli_notes')->nullable()->after('is_salah_beli');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['is_salah_beli', 'salah_beli_notes']);
        });
        Schema::dropIfExists('retur_barang_items');
        Schema::dropIfExists('retur_barang');
    }
};
