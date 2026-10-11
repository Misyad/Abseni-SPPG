<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaProxyController extends Controller
{
    /**
     * Menyajikan foto selfie dari Private Storage hanya untuk admin terotorisasi
     */
    public function viewSelfie(int $id, string $type): BinaryFileResponse|JsonResponse
    {
        $attendance = Attendance::findOrFail($id);

        $filePath = match ($type) {
            'check_in' => $attendance->check_in_photo_path,
            'check_out' => $attendance->check_out_photo_path,
            default => null,
        };

        if (! $filePath || ! Storage::disk('private')->exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Berkas foto tidak ditemukan.',
            ], 404);
        }

        $absolutePath = Storage::disk('private')->path($filePath);
        $mimeType = Storage::disk('private')->mimeType($filePath) ?: 'image/jpeg';

        $response = response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
