<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Siapa yang melakukan aksi
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();       // snapshot nama (tahan hapus user)
            $table->string('user_role')->nullable();       // snapshot role saat itu

            // Aksi apa
            $table->string('action');                      // create | update | delete | login | logout | export | approve | reject | dsb
            $table->string('module');                      // users | roles | items | stok | mr | po | sj | transfer | bon | retur | pm | akuntansi | payroll | dsb
            $table->string('description');                 // kalimat ringkas human-readable

            // Pada entitas mana
            $table->string('auditable_type')->nullable();  // App\Models\Item (opsional)
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->index(['auditable_type', 'auditable_id'], 'audit_auditable_index');

            // Perubahan data (opsional)
            $table->json('old_values')->nullable();        // nilai sebelum update/delete
            $table->json('new_values')->nullable();        // nilai sesudah create/update

            // Konteks request
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();

            $table->timestamp('created_at')->useCurrent();
            // Tidak pakai updated_at — audit log tidak pernah diubah

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
