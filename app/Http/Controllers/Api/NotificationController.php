<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LowStockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NotificationController
 *
 * Daftarkan di routes/api.php (di dalam group auth:sanctum):
 *
 *   Route::get('/notifications',          [NotificationController::class, 'index']);
 *   Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
 *   Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
 *   Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
 *   Route::get('/notifications/low-stock', [NotificationController::class, 'lowStockSummary']);
 */
class NotificationController extends Controller
{
    public function __construct(private LowStockAlertService $alertService) {}

    /**
     * GET /api/notifications
     * Ambil semua notifikasi user (paginasi).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage       = min((int) ($request->per_page ?? 15), 50);
        $onlyUnread    = $request->boolean('unread');

        $query = $request->user()->notifications();

        if ($onlyUnread) {
            $query->unread();
        }

        $notifications = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $notifications->items(),
            'meta'    => [
                'total'        => $notifications->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
                'page'         => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/notifications/unread-count
     * Hanya return jumlah unread — dipakai untuk badge di navbar.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'count'   => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/notifications/read-all
     * Tandai semua notifikasi sebagai sudah dibaca.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/notifications/low-stock
     * Ringkasan stok kritis / menipis yang diakses langsung
     * oleh dashboard (tanpa perlu masuk ke queue notification).
     */
    public function lowStockSummary(Request $request): JsonResponse
    {
        $user         = $request->user();
        $warehouseIds = $this->getAccessibleWarehouseIds($user);

        $summary = $this->alertService->getLowStockSummary($warehouseIds);

        $grouped = collect($summary)->groupBy('alert_level');

        return response()->json([
            'success' => true,
            'data'    => [
                'items'  => $summary,
                'counts' => [
                    'minus'    => $grouped->get('minus',    collect())->count(),
                    'critical' => $grouped->get('critical', collect())->count(),
                    'low'      => $grouped->get('low',      collect())->count(),
                    'total'    => count($summary),
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function getAccessibleWarehouseIds($user): array
    {
        if ($user->isSuperuser() || $user->isAdminHO() || $user->hasRole('purchasing') || $user->hasRole('manager')) {
            return \App\Models\Warehouse::pluck('id')->toArray();
        }
        return $user->warehouse_id ? [$user->warehouse_id] : [];
    }
}
