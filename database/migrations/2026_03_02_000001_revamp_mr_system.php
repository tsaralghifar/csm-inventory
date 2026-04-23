<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom baru di material_requests
        Schema::table('material_requests', function (Blueprint $table) {
            $table->enum('type', ['part', 'office'])->default('part')->after('mr_number');
            $table->foreignId('chief_authorized_by')->nullable()->constrained('users')->after('requested_by');
            $table->timestamp('chief_authorized_at')->nullable()->after('chief_authorized_by');
            $table->foreignId('manager_approved_by')->nullable()->constrained('users')->after('chief_authorized_at');
            $table->timestamp('manager_approved_at')->nullable()->after('manager_approved_by');
        });

        // 2. Purchase Orders
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 50)->unique();
            $table->foreignId('material_request_id')->constrained('material_requests');
            $table->foreignId('warehouse_id')->constrained('warehouses'); // gudang tujuan
            $table->foreignId('created_by')->constrained('users');        // Purchasing
            $table->enum('status', ['draft', 'sent_to_vendor', 'completed', 'cancelled'])->default('draft');
            $table->string('vendor_name')->nullable();
            $table->string('vendor_contact')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->date('expected_date')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('nama_barang');           // nama bebas dari MR
            $table->string('kode_unit')->nullable();
            $table->string('tipe_unit')->nullable();
            $table->decimal('qty', 12, 2);
            $table->string('satuan', 50);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('total_harga', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // 3. Bon Pengeluaran (jika barang ada di stok gudang)
        Schema::create('bon_pengeluaran', function (Blueprint $table) {
            $table->id();
            $table->string('bon_number', 50)->unique();
            $table->foreignId('material_request_id')->nullable()->constrained('material_requests')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses'); // gudang sumber stok
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['draft', 'approved', 'issued'])->default('draft');
            $table->string('received_by')->nullable();   // nama penerima
            $table->date('issue_date')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('bon_pengeluaran_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_pengeluaran_id')->constrained('bon_pengeluaran')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete(); // item dari stok
            $table->string('nama_barang');     // nama dari MR (bisa bebas)
            $table->decimal('qty', 12, 2);
            $table->string('satuan', 50);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // 4. Surat Jalan (tanda terima barang yang dibeli)
        Schema::create('surat_jalan', function (Blueprint $table) {
            $table->id();
            $table->string('sj_number', 50)->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('material_request_id')->nullable()->constrained('material_requests')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses'); // gudang tujuan masuk stok
            $table->foreignId('created_by')->constrained('users');        // Purchasing
            $table->foreignId('received_by_user')->nullable()->constrained('users'); // yang terima
            $table->enum('status', ['draft', 'received'])->default('draft');
            $table->string('vendor_name')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->date('received_date')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('surat_jalan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_jalan_id')->constrained('surat_jalan')->cascadeOnDelete();
            $table->string('nama_barang');
            $table->string('kode_unit')->nullable();
            $table->string('tipe_unit')->nullable();
            $table->decimal('qty_ordered', 12, 2);   // qty di PO
            $table->decimal('qty_received', 12, 2)->default(0); // qty diterima aktual
            $table->string('satuan', 50);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->boolean('masuk_stok')->default(true); // apakah masuk stok sistem
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete(); // item stok jika masuk stok
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_jalan_items');
        Schema::dropIfExists('surat_jalan');
        Schema::dropIfExists('bon_pengeluaran_items');
        Schema::dropIfExists('bon_pengeluaran');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropForeign(['chief_authorized_by']);
            $table->dropForeign(['manager_approved_by']);
            $table->dropColumn(['type', 'chief_authorized_by', 'chief_authorized_at', 'manager_approved_by', 'manager_approved_at']);
        });
    }
};
