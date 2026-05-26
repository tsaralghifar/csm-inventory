<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pisahkan konsep invoice_number vs internal_number:
 *
 *  internal_number  = nomor yang digenerate SISTEM (selalu ada, tidak berubah)
 *  invoice_number   = nomor yang diberikan oleh SUPPLIER (boleh kosong/null,
 *                     bisa diisi/diupdate kemudian)
 *
 * Sebelumnya invoice_number diisi dengan nomor auto-generate sistem
 * (INV-YYYYMMDD-XXXX), yang membingungkan karena itu bukan nomor dari supplier.
 *
 * Migrasi ini:
 * 1. Jadikan invoice_number nullable
 * 2. Pindahkan semua nilai auto-generated (pola INV-*) dari invoice_number
 *    ke internal_number (jika internal_number belum terisi / kosong)
 * 3. Set invoice_number menjadi NULL untuk baris yang invoice_number-nya
 *    hasil generate sistem (bukan dari supplier)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: jadikan nullable dulu agar bisa di-update
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->change();
        });

        // Step 2: untuk baris yang invoice_number = internal_number
        //         (tanda bahwa keduanya diisi sistem), nullkan invoice_number
        DB::statement("
            UPDATE supplier_invoices
            SET invoice_number = NULL
            WHERE invoice_number = internal_number
               OR invoice_number LIKE 'INV-%'
               OR invoice_number LIKE 'AUTO-%'
        ");

        // Step 3: pastikan internal_number selalu terisi —
        //         baris lama yang internal_number kosong, isi dari invoice_number
        DB::statement("
            UPDATE supplier_invoices
            SET internal_number = CONCAT('SYS-', LPAD(id::text, 6, '0'))
            WHERE internal_number IS NULL OR internal_number = ''
        ");
    }

    public function down(): void
    {
        // Kembalikan invoice_number menjadi NOT NULL
        // (isi dengan internal_number sebagai fallback untuk baris yang kosong)
        DB::statement("
            UPDATE supplier_invoices
            SET invoice_number = internal_number
            WHERE invoice_number IS NULL OR invoice_number = ''
        ");

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable(false)->change();
        });
    }
};
