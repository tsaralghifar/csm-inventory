<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            // Rekening kas besar sumber dana saat top-up (type = 'in')
            $table->foreignId('main_cash_account_id')
                ->nullable()
                ->after('petty_cash_account_id')
                ->constrained('main_cash_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->dropForeign(['main_cash_account_id']);
            $table->dropColumn('main_cash_account_id');
        });
    }
};
