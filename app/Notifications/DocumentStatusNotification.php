<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * DocumentStatusNotification
 *
 * Notifikasi generik untuk perubahan status dokumen:
 * Purchase Order, Transfer Barang, Material Request,
 * Bon Pengeluaran, Surat Jalan, Retur Barang, Stok Opname.
 *
 * Dikirim via:
 *   - database  → in-app notification bell
 *   - broadcast → realtime via Laravel Reverb
 */
class DocumentStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array $payload  Data dokumen yang akan dikirim ke notifikasi.
     *                        Wajib ada: type, action, id, title, message, url.
     */
    public function __construct(private readonly array $payload) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    // ── Database (in-app notification) ────────────────────────────────────────

    public function toDatabase(object $notifiable): array
    {
        return array_merge($this->payload, [
            'created_at' => now()->toISOString(),
        ]);
    }

    // ── Broadcast (Reverb realtime) ────────────────────────────────────────────

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(array_merge($this->payload, [
            'created_at' => now()->toISOString(),
        ]));
    }

    // ── (Opsional) Email ──────────────────────────────────────────────────────
    // Uncomment dan sesuaikan jika ingin kirim via email:
    //
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->subject('[CSM] ' . $this->payload['title'])
    //         ->line($this->payload['message'])
    //         ->action('Lihat Detail', url($this->payload['url']))
    //         ->salutation('Sistem CSM Inventory');
    // }
}
