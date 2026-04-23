<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permintaan_material', function (Blueprint $table) {
            // Tambah kolom type (part / office) jika belum ada
            if (!Schema::hasColumn('permintaan_material', 'type')) {
                $table->enum('type', ['part', 'office'])->default('part')->after('nomor');
            }

            // Tambah kolom chief mekanik authorization
            if (!Schema::hasColumn('permintaan_material', 'chief_authorized_by')) {
                $table->foreignId('chief_authorized_by')->nullable()->constrained('users')->after('requested_by');
            }
            if (!Schema::hasColumn('permintaan_material', 'chief_authorized_at')) {
                $table->timestamp('chief_authorized_at')->nullable()->after('chief_authorized_by');
            }

            // Tambah kolom manager approval
            if (!Schema::hasColumn('permintaan_material', 'manager_approved_by')) {
                $table->foreignId('manager_approved_by')->nullable()->constrained('users')->after('chief_authorized_at');
            }
            if (!Schema::hasColumn('permintaan_material', 'manager_approved_at')) {
                $table->timestamp('manager_approved_at')->nullable()->after('manager_approved_by');
            }
        });

        // Update status enum agar mencakup status baru
        // PostgreSQL tidak support ALTER COLUMN enum secara langsung, gunakan raw SQL
        DB::statement("
            ALTER TABLE permintaan_material
            DROP CONSTRAINT IF EXISTS permintaan_material_status_check
        ");

        DB::statement("
            ALTER TABLE permintaan_material
            ADD CONSTRAINT permintaan_material_status_check
            CHECK (status IN (
                'draft',
                'pending_chief',
                'pending_manager',
                'pending_ho',
                'approved',
                'rejected',
                'completed',
                -- status lama untuk backward compatibility
                'pending_site'
            ))
        ");

        // Migrasi data: ubah status lama ke status baru
        // pending_site → pending_chief (flow lama ke flow baru)
        DB::statement("UPDATE permintaan_material SET status = 'pending_chief' WHERE status = 'pending_site'");

        // ho_approved_by adalah kolom yang sudah ada, rename dari ho_approved_by jika ada kolom lama
        // (tidak perlu karena sudah menggunakan ho_approved_by di model baru)
    }

    public function down(): void
    {
        Schema::table('permintaan_material', function (Blueprint $table) {
            $table->dropForeign(['chief_authorized_by']);
            $table->dropForeign(['manager_approved_by']);
            $table->dropColumn(['type', 'chief_authorized_by', 'chief_authorized_at', 'manager_approved_by', 'manager_approved_at']);
        });

        DB::statement("UPDATE permintaan_material SET status = 'pending_site' WHERE status = 'pending_chief'");
    }
};
