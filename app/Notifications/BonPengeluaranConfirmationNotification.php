<?php

namespace App\Notifications;

use App\Models\BonPengeluaran;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * BonPengeluaranConfirmationNotification
 *
 * Dikirim ke mekanik saat admin sudah menyiapkan barang
 * dan menunggu konfirmasi kesesuaian sebelum dikeluarkan.
 */
class BonPengeluaranConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly BonPengeluaran $bon) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    private function payload(): array
    {
        $items = $this->bon->items->map(fn ($i) => [
            'nama_barang' => $i->nama_barang,
            'qty'         => $i->qty,
            'satuan'      => $i->satuan,
            'part_number' => $i->item?->part_number,
            'brand'       => $i->item?->brand,
            'keterangan'  => $i->keterangan,
        ])->toArray();

        return [
            'type'       => 'bon_confirmation',
            'action'     => 'pending_confirmation',
            'id'         => $this->bon->id,
            'bon_number' => $this->bon->bon_number,
            'unit_code'  => $this->bon->unit_code,
            'unit_type'  => $this->bon->unit_type,
            'warehouse'  => $this->bon->warehouse?->name,
            'items'      => $items,
            'title'      => 'Konfirmasi Barang — ' . $this->bon->bon_number,
            'message'    => 'Admin telah menyiapkan barang untuk ' . ($this->bon->unit_code ?? '-')
                            . '. Mohon cek kesesuaian barang sebelum dikeluarkan.',
            'url'        => '/bon-pengeluaran/' . $this->bon->id . '/confirm',
        ];
    }
}
