<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Artisan command: signature:migrate-to-storage
 *
 * Memindahkan data tanda tangan lama dari kolom `signature` (base64 di DB)
 * ke file PNG di Storage::disk('local') dan mengisi kolom `signature_path`.
 *
 * Jalankan SETELAH migration 2026_05_31_000001 (kolom signature_path sudah ada)
 * dan SEBELUM migration 2026_05_31_000002 (kolom signature lama dihapus).
 *
 * Usage:
 *   php artisan signature:migrate-to-storage
 *   php artisan signature:migrate-to-storage --dry-run   # preview tanpa ubah data
 */
class MigrateSignaturesToStorage extends Command
{
    protected $signature   = 'signature:migrate-to-storage {--dry-run : Preview saja, tidak ubah data}';
    protected $description = 'Pindahkan tanda tangan dari base64 DB ke Storage::disk(local) private';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY RUN] Tidak ada perubahan yang akan disimpan.');
        }

        // Ambil user yang masih punya kolom signature lama (base64) tapi belum punya path baru
        // Kolom 'signature' mungkin sudah dihapus jika migration ke-2 sudah berjalan.
        // Guard: cek dulu kolom ada.
        $hasOldColumn = DB::getSchemaBuilder()->hasColumn('users', 'signature');

        if (! $hasOldColumn) {
            $this->error('Kolom `signature` tidak ditemukan. Sudah dimigrasikan sebelumnya?');
            return self::FAILURE;
        }

        // Ambil user dengan signature lama yang belum dimigrasikan
        $users = DB::table('users')
            ->whereNotNull('signature')
            ->whereNull('signature_path')
            ->whereNull('deleted_at')
            ->select('id', 'name', 'signature')
            ->get();

        if ($users->isEmpty()) {
            $this->info('Tidak ada data TTD lama yang perlu dimigrasikan.');
            return self::SUCCESS;
        }

        $this->info("Ditemukan {$users->count()} user dengan TTD lama.");

        $bar      = $this->output->createProgressBar($users->count());
        $success  = 0;
        $failed   = 0;
        $skipped  = 0;

        foreach ($users as $row) {
            $bar->advance();

            $raw = $row->signature;

            // Ekstrak binary PNG dari data URI atau plain base64
            if (str_starts_with($raw, 'data:')) {
                // Format: data:image/png;base64,XXXX
                $parts      = explode(',', $raw, 2);
                $pureBase64 = $parts[1] ?? '';
            } else {
                $pureBase64 = $raw;
            }

            // Validasi base64
            $binary = base64_decode($pureBase64, strict: true);

            if ($binary === false) {
                $this->newLine();
                $this->warn("  [SKIP] User #{$row->id} ({$row->name}): data base64 tidak valid.");
                $skipped++;
                continue;
            }

            $path = "signatures/{$row->id}.png";

            if (! $dryRun) {
                try {
                    Storage::disk('local')->put($path, $binary);

                    DB::table('users')
                        ->where('id', $row->id)
                        ->update(['signature_path' => $path]);

                    $success++;
                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->error("  [FAIL] User #{$row->id} ({$row->name}): {$e->getMessage()}");
                    $failed++;
                }
            } else {
                // Dry run: hanya hitung
                $sizeKb = round(strlen($binary) / 1024, 1);
                $this->newLine();
                $this->line("  [PREVIEW] User #{$row->id} ({$row->name}) → {$path} ({$sizeKb} KB)");
                $success++;
            }
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("Preview selesai: {$success} akan dimigrasikan, {$skipped} akan diskip.");
            return self::SUCCESS;
        }

        $this->info("Migrasi selesai:");
        $this->table(
            ['Status', 'Jumlah'],
            [
                ['✅ Berhasil', $success],
                ['⚠️  Diskip (data tidak valid)', $skipped],
                ['❌ Gagal', $failed],
            ]
        );

        if ($failed > 0) {
            $this->warn('Beberapa TTD gagal dimigrasikan. Periksa log untuk detail.');
            return self::FAILURE;
        }

        if ($success > 0) {
            $this->line('');
            $this->line('Langkah selanjutnya:');
            $this->line('  php artisan migrate   (jalankan migration drop-signature-column)');
        }

        return self::SUCCESS;
    }
}
