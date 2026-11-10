<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * 使用例: Route::middleware(['auth', 'role:admin'])->group(...)
     *        Route::middleware(['auth', 'role:admin,coach'])->group(...)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403);
        }

        if ($user->role === UserRole::Student && $user->status === UserStatus::Graduated) {
            abort(403, 'プラン期間が満了しました。');
        }

        return $next($request);
    }
}
