<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration ini TIDAK mengubah struktur tabel.
 * Fungsinya: update min_stock = 0 yang NULL menjadi 0 (sanitasi data),
 * dan memberi panduan untuk mengecek item tanpa min_stock via query.
 *
 * Jalankan:
 *   php artisan migrate
 *
 * Setelah migrate, cek item tanpa min_stock dengan Artisan tinker:
 *   php artisan tinker
 *   >>> App\Models\Item::where('min_stock', 0)->orWhereNull('min_stock')->count()
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pastikan tidak ada NULL di kolom min_stock (seharusnya sudah default 0)
        DB::table('items')
            ->whereNull('min_stock')
            ->update(['min_stock' => 0]);

        // Log ke console berapa item yang belum punya min_stock > 0
        $count = DB::table('items')
            ->where('min_stock', '<=', 0)
            ->where('is_active', true)
            ->count();

        if ($count > 0) {
            echo "\n⚠️  PERHATIAN: Terdapat {$count} barang aktif dengan min_stock = 0.\n";
            echo "   Dashboard alert stok tidak akan bekerja untuk barang-barang tersebut.\n";
            echo "   Silakan update min_stock via halaman Barang / Sparepart.\n\n";
        }
    }

    public function down(): void
    {
        // Tidak ada yang perlu di-rollback
    }
};
