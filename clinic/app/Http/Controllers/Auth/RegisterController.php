<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Gender;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Clinical\Patient;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** BN-01: đăng ký bằng email/SĐT, xác thực OTP, đồng ý điều khoản xử lý dữ liệu (BR-11). */
class RegisterController extends Controller
{
    public function __construct(protected OtpService $otp) {}

    public function show()
    {
        return view('common.auth.register', ['genders' => Gender::options()]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['required', 'regex:/^0\d{9}$/', 'unique:users,phone'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'consent' => ['accepted'],
        ], [
            'phone.regex' => 'Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'consent_at' => now(),
            ]);
            $user->assignRole(Role::Patient->value);

            // Hồ sơ "Bản thân" tạo sẵn để đặt lịch ngay
            Patient::create([
                'user_id' => $user->id,
                'relationship' => 'Bản thân',
                'full_name' => $data['name'],
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
                'phone' => $data['phone'],
                'email' => $data['email'],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $this->otp->send($user->email, 'register', $user);

        return redirect()->route('verify.show')
            ->with('success', 'Đăng ký thành công. Mã OTP đã được gửi tới email '.$user->email.'.');
    }

    public function showVerify(Request $request)
    {
        if ($request->user()->isVerified()) {
            return redirect()->route($request->user()->isStaff() ? 'staff.dashboard' : 'account.dashboard');
        }

        return view('common.auth.verify', ['user' => $request->user()]);
    }

    public function verify(Request $request)
    {
        $request->validate(['otp' => ['required', 'digits:6']]);
        $user = $request->user();

        $this->otp->verify($user->email, 'register', $request->input('otp'));
        $user->forceFill(['verified_at' => now(), 'email_verified_at' => now()])->save();

        return redirect()->route('account.dashboard')->with('success', 'Xác thực tài khoản thành công. Chào mừng bạn đến với '.config('clinic.name').'!');
    }

    public function resend(Request $request)
    {
        $user = $request->user();
        $this->otp->send($user->email, 'register', $user);

        return back()->with('success', 'Đã gửi lại mã OTP tới '.$user->email.'.');
    }
}
