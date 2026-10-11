<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DivisionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Division::withCount('employees')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, 'unique:divisions,code');
        $division = DB::transaction(function () use ($request, $validated) {
            $division = Division::create(collect($validated)->except('reason')->all());
            $this->audit($request, $division, 'CREATE_DIVISION', null, $division->toArray(), $validated['reason']);

            return $division;
        });

        return response()->json(['success' => true, 'message' => 'Divisi berhasil dibuat', 'data' => $division], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validatePayload($request, 'unique:divisions,code,'.$id);
        $division = DB::transaction(function () use ($request, $id, $validated) {
            $division = Division::lockForUpdate()->findOrFail($id);
            $oldValues = $division->toArray();
            $division->update(collect($validated)->except('reason')->all());
            $this->audit($request, $division, 'UPDATE_DIVISION', $oldValues, $division->fresh()->toArray(), $validated['reason']);

            return $division;
        });

        return response()->json(['success' => true, 'message' => 'Divisi berhasil diperbarui', 'data' => $division]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|min:3|max:1000']);

        DB::transaction(function () use ($request, $id, $validated) {
            $division = Division::lockForUpdate()->findOrFail($id);
            if ($division->employees()->exists()) {
                abort(422, 'Divisi tidak dapat dihapus karena masih memiliki pegawai terikat.');
            }

            $oldValues = $division->toArray();
            $division->delete();
            $this->audit($request, $division, 'DELETE_DIVISION', $oldValues, null, $validated['reason']);
        });

        return response()->json(['success' => true, 'message' => 'Divisi berhasil dihapus']);
    }

    private function validatePayload(Request $request, string $codeRule): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', $codeRule],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'reason' => 'required|string|min:3|max:1000',
        ]);
    }

    private function audit(Request $request, Division $division, string $action, ?array $oldValues, ?array $newValues, string $reason): void
    {
        AuditLog::logAction($request->user()->id, Division::class, $division->id, $action, $oldValues, $newValues, $reason, $request->ip() ?: '127.0.0.1', $request->header('User-Agent'));
    }
}
