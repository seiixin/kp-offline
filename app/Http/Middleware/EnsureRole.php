<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnsureRole
{
    /**
     * Usage:
     *   ->middleware('role:admin')
     *   ->middleware('role:agent')
     *   ->middleware('role:admin,agent')
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        // If not authenticated, let auth middleware handle it (or redirect as fallback).
        if (!$user) {
            return redirect()->route('login');
        }

        $allowed = collect($roles)
            ->flatMap(fn ($r) => explode(',', (string) $r))
            ->map(fn ($r) => strtolower(trim($r)))
            ->filter()
            ->values()
            ->all();

        // If no roles were provided, allow through.
        if (count($allowed) === 0) {
            return $next($request);
        }

        $role = strtolower((string) ($user->role ?? ''));

        // Debug logging (safe + minimal)
        Log::info('RBAC role check', [
            'user_id' => $user->id ?? null,
            'role' => $role ?: null,
            'path' => $request->path(),
            'allowed' => $allowed,
        ]);

        if (!in_array($role, $allowed, true)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
