<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Location::all()]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'address' => 'required|string|max:2000',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:1000',
            'is_active' => 'sometimes|boolean',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        $location = DB::transaction(function () use ($request, $id, $validated) {
            $location = Location::lockForUpdate()->findOrFail($id);
            $oldValues = $location->toArray();
            $location->update(collect($validated)->except('reason')->all());
            AuditLog::logAction(
                $request->user()->id,
                Location::class,
                $location->id,
                'UPDATE_LOCATION',
                $oldValues,
                $location->fresh()->toArray(),
                $validated['reason'],
                $request->ip() ?: '127.0.0.1',
                $request->header('User-Agent')
            );

            return $location;
        });

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan lokasi SPPG berhasil diperbarui',
            'data' => $location,
        ]);
    }
}
