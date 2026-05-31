<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill linked_po_id pada Transfer Part yang sudah punya PM pengganti
        // Ambil PO pertama yang terhubung ke PM pengganti tersebut
        DB::statement("
            UPDATE material_requests tp
            SET linked_po_id = (
                SELECT po.id
                FROM purchase_orders po
                JOIN purchase_order_permintaan_material pivot
                    ON pivot.purchase_order_id = po.id
                WHERE pivot.permintaan_material_id = tp.linked_pm_id
                ORDER BY po.created_at ASC
                LIMIT 1
            )
            WHERE tp.type = 'transfer_part'
              AND tp.linked_pm_id IS NOT NULL
              AND tp.linked_po_id IS NULL
        ");
    }

    public function down(): void {}
};
