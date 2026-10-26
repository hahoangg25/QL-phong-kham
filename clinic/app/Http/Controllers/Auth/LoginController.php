<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** BN-02: đăng nhập bằng email hoặc số điện thoại, đăng xuất. */
class LoginController extends Controller
{
    public function show()
    {
        return view('common.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, trim($data['login']))->first();

        if (! $user || ! Auth::attempt([$field => $user->{$field}, 'password' => $data['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'login' => 'Tài khoản đã bị khóa'.($user->locked_reason ? ': '.$user->locked_reason : '.').' Vui lòng liên hệ phòng khám.',
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        activity('auth')->causedBy($user)->log('Đăng nhập');

        if ($user->isStaff()) {
            return redirect()->intended(route('staff.dashboard'));
        }

        if (! $user->isVerified()) {
            return redirect()->route('verify.show');
        }

        return redirect()->intended(route('account.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Bạn đã đăng xuất.');
    }
}
