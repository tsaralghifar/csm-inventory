<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recalculate qty_received on purchase_order_items based on actual surat_jalan_items.
 * Also recalculate delivery_status on purchase_orders.
 *
 * Fixes data integrity issues where qty_received was set incorrectly.
 *
 * FIX: Sekarang menggunakan purchase_order_item_id (bukan item_id) untuk join
 * yang lebih akurat. purchase_order_item_id sudah di-backfill oleh migration
 * 2026_04_29_300000_add_po_item_id_to_surat_jalan_items sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Reset semua qty_received ke 0
        DB::statement("UPDATE purchase_order_items SET qty_received = 0");

        // Step 2: Hitung ulang qty_received dari surat_jalan_items yang valid.
        // FIX: Gunakan purchase_order_item_id jika tersedia (lebih akurat),
        // fallback ke item_id + purchase_order_id untuk data lama yang belum ter-backfill.
        DB::statement("
            UPDATE purchase_order_items poi
            SET qty_received = COALESCE((
                SELECT SUM(sji.qty_received)
                FROM surat_jalan_items sji
                JOIN surat_jalan sj ON sj.id = sji.surat_jalan_id
                WHERE (
                    -- Prioritas 1: match via purchase_order_item_id (akurat)
                    (sji.purchase_order_item_id IS NOT NULL AND sji.purchase_order_item_id = poi.id)
                    OR
                    -- Fallback: match via item_id + purchase_order_id (untuk data lama)
                    (sji.purchase_order_item_id IS NULL AND sji.item_id = poi.item_id
                     AND sj.purchase_order_id = poi.purchase_order_id)
                )
                AND sj.deleted_at IS NULL
            ), 0)
        ");

        // Step 3: Recalculate delivery_status di purchase_orders
        DB::statement("
            UPDATE purchase_orders po
            SET delivery_status = CASE
                WHEN NOT EXISTS (
                    SELECT 1 FROM purchase_order_items
                    WHERE purchase_order_id = po.id AND qty_received > 0
                ) THEN NULL
                WHEN NOT EXISTS (
                    SELECT 1 FROM purchase_order_items
                    WHERE purchase_order_id = po.id AND qty_received < qty
                ) THEN 'completed'
                ELSE 'partial'
            END
            WHERE po.deleted_at IS NULL
        ");

        // Step 4: Sinkronkan status PO berdasarkan delivery_status yang baru.
        // FIX: Jika delivery_status IS NULL, JANGAN ubah status — PO mungkin sudah
        // di-send ke vendor (sent_to_vendor) dan memang belum ada penerimaan barang.
        // Hanya update jika delivery_status terisi (partial atau completed).
        DB::statement("
            UPDATE purchase_orders
            SET status = CASE
                WHEN delivery_status = 'completed' THEN 'completed'
                WHEN delivery_status = 'partial'   THEN 'partial_received'
                ELSE status
            END
            WHERE delivery_status IS NOT NULL
              AND status NOT IN ('draft', 'cancelled')
              AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        // Tidak bisa di-rollback otomatis
    }
};