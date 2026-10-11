<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response|JsonResponse
    {
        $admin = $request->user();

        if (! $admin || ! $admin->is_active || ! in_array($admin->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki kewenangan untuk mengakses endpoint ini.',
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        return $next($request);
    }
}
