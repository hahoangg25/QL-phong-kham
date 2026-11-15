<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Tài khoản cá nhân của nhân viên: thông tin liên hệ, đổi mật khẩu. */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.profile.edit', ['user' => $request->user()->load('doctor.specialty')]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^0\d{9}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'phone.regex' => 'Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.',
        ]);

        $user->fill(collect($data)->only(['name', 'email', 'phone'])->all());
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('success', 'Đã cập nhật thông tin tài khoản.');
    }
}
