<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureOtpVerified;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsStaff;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'staff' => EnsureUserIsStaff::class,
            'verified.otp' => EnsureOtpVerified::class,
        ]);

        // IPN của MoMo là request POST từ máy chủ MoMo, không có CSRF token (đã xác thực bằng chữ ký HMAC)
        $middleware->validateCsrfTokens(except: ['thanh-toan/momo/ipn']);

        // Người đã đăng nhập vào trang khách (đăng nhập/đăng ký) -> về trang chính theo vai trò
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isStaff()
            ? route('staff.dashboard')
            : route('account.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Vi phạm quy tắc nghiệp vụ: hiển thị thông báo thân thiện thay vì trang lỗi
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });

        $exceptions->dontReport(BusinessRuleException::class);
    })->create();
