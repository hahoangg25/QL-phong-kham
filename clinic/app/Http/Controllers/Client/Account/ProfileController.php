<?php

namespace App\Http\Controllers\Client\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** BN-03: cập nhật thông tin tài khoản, đổi mật khẩu. */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('client.account.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'regex:/^0\d{9}$/', Rule::unique('users', 'phone')->ignore($user->id)],
        ], [
            'phone.regex' => 'Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.',
        ]);

        $user->update($data);

        return back()->with('success', 'Đã cập nhật thông tin tài khoản.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->forceFill(['password' => $request->input('password')])->save();
        activity('auth')->causedBy($request->user())->log('Đổi mật khẩu');

        return back()->with('success', 'Đã đổi mật khẩu thành công.');
    }
}
