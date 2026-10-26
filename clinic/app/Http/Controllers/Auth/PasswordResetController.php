<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** BN-02: quên mật khẩu - đặt lại bằng OTP. */
class PasswordResetController extends Controller
{
    public function __construct(protected OtpService $otp) {}

    public function request()
    {
        return view('common.auth.forgot');
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:191']]);
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, trim($data['login']))->first();

        if (! $user || ! $user->email) {
            throw ValidationException::withMessages(['login' => 'Không tìm thấy tài khoản với thông tin này.']);
        }

        $this->otp->send($user->email, 'reset_password', $user);
        $request->session()->put('password_reset_email', $user->email);

        return redirect()->route('password.reset')
            ->with('success', 'Mã OTP đặt lại mật khẩu đã được gửi tới email '.$this->maskEmail($user->email).'.');
    }

    public function showReset(Request $request)
    {
        if (! $request->session()->has('password_reset_email')) {
            return redirect()->route('password.request');
        }

        return view('common.auth.reset', ['email' => $this->maskEmail($request->session()->get('password_reset_email'))]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $email = $request->session()->get('password_reset_email');
        abort_unless($email, 419);

        $this->otp->verify($email, 'reset_password', $request->input('otp'));

        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['password' => $request->input('password')])->save();
        $request->session()->forget('password_reset_email');
        activity('auth')->causedBy($user)->log('Đặt lại mật khẩu bằng OTP');

        return redirect()->route('login')->with('success', 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập bằng mật khẩu mới.');
    }

    protected function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email);

        return mb_substr($name, 0, 2).str_repeat('*', max(1, mb_strlen($name) - 2)).'@'.$domain;
    }
}
