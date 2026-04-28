<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel notifikasi Laravel.
 *
 * Alternatif: generate otomatis dengan:
 *   php artisan notifications:table
 *   php artisan migrate
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');                          // Nama class Notification
            $table->morphs('notifiable');                   // notifiable_type + notifiable_id
            $table->text('data');                           // JSON payload
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notif_notifiable_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
