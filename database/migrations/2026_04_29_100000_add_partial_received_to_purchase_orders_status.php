<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah nilai 'partial_received' ke enum status purchase_orders
        DB::statement("ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check");
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft', 'sent_to_vendor', 'partial_received', 'completed', 'cancelled'))");
    }

    public function down(): void
    {
        // Kembalikan ke enum lama (tanpa partial_received)
        // Pastikan tidak ada data dengan status partial_received sebelum rollback
        DB::statement("UPDATE purchase_orders SET status = 'sent_to_vendor' WHERE status = 'partial_received'");
        DB::statement("ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check");
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft', 'sent_to_vendor', 'completed', 'cancelled'))");
    }
};
