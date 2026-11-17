<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** BN-01: tài khoản bệnh nhân phải xác thực OTP trước khi sử dụng. */
class EnsureOtpVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isVerified()) {
            if ($user->isStaff()) {
                return redirect()->route('staff.dashboard');
            }

            return redirect()->route('verify.show');
        }

        return $next($request);
    }
}
