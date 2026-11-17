<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Chỉ nhân viên (có vai trò nội bộ) mới vào được hệ thống quản lý. */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isStaff()) {
            return redirect()->route('account.dashboard');
        }

        return $next($request);
    }
}
