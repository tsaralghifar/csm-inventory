<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PriceAlertSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'   => 'threshold_up_low',
                'value' => '5',
                'label' => 'Batas Kenaikan Harga Waspada (%)',
                'type'  => 'number',
            ],
            [
                'key'   => 'threshold_up_high',
                'value' => '20',
                'label' => 'Batas Kenaikan Harga Kritis (%)',
                'type'  => 'number',
            ],
            [
                'key'   => 'threshold_up_critical',
                'value' => '50',
                'label' => 'Batas Kenaikan Harga Sangat Kritis (%)',
                'type'  => 'number',
            ],
            [
                'key'   => 'digest_time',
                'value' => '08:00',
                'label' => 'Jam Kirim Digest Harian',
                'type'  => 'time',
            ],
            [
                'key'   => 'digest_enabled',
                'value' => '1',
                'label' => 'Aktifkan Digest Harian',
                'type'  => 'boolean',
            ],
            [
                'key'   => 'budget_alert_threshold',
                'value' => '20',
                'label' => 'Budget Alert Threshold (%)',
                'type'  => 'number',
            ],
            [
                'key'   => 'budget_alert_months',
                'value' => '3',
                'label' => 'Rata-rata Berapa Bulan Terakhir untuk Budget Alert',
                'type'  => 'number',
            ],
            [
                'key'   => 'consecutive_increase_count',
                'value' => '3',
                'label' => 'Jumlah Kenaikan Berturut-turut untuk Anomali',
                'type'  => 'number',
            ],
            [
                'key'   => 'po_vs_receive_threshold',
                'value' => '5',
                'label' => 'Batas Selisih Harga PO vs Tanda Terima (%)',
                'type'  => 'number',
            ],
        ];

        foreach ($settings as $s) {
            DB::table('price_alert_settings')->updateOrInsert(
                ['key' => $s['key']],
                array_merge($s, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
