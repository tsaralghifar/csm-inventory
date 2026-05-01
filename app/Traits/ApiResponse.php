<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    /**
     * Response sukses umum.
     */
    protected function success(mixed $data = null, string $message = 'Berhasil', int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Response sukses dengan pagination.
     * Otomatis mengekstrak items() dan meta dari LengthAwarePaginator.
     */
    protected function paginated(LengthAwarePaginator $paginator, string $message = 'Berhasil'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => [
                'total'       => $paginator->total(),
                'page'        => $paginator->currentPage(),
                'last_page'   => $paginator->lastPage(),
                'per_page'    => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * Response untuk resource yang baru dibuat (201 Created).
     */
    protected function created(mixed $data, string $message = 'Data berhasil dibuat'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Response untuk operasi hapus / aksi tanpa data kembalian.
     */
    protected function deleted(string $message = 'Data berhasil dihapus'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * Response error umum.
     */
    protected function error(string $message = 'Terjadi kesalahan', int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Response 403 Forbidden.
     */
    protected function forbidden(string $message = 'Akses ditolak'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * Response 404 Not Found.
     */
    protected function notFound(string $message = 'Data tidak ditemukan'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Response 422 Unprocessable (validasi manual, di luar Form Request).
     */
    protected function validationError(string $message = 'Validasi gagal', mixed $errors = null): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }
}
