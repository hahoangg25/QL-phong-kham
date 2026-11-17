<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_page_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Đăng nhập');
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();
    }

    public function test_patient_can_login_with_phone_and_staff_redirects_to_dashboard(): void
    {
        $this->post(route('login'), ['login' => '0901234567', 'password' => 'password'])
            ->assertRedirect(route('account.dashboard'));
        $this->post(route('logout'));

        $this->post(route('login'), ['login' => 'letan@phongkham.test', 'password' => 'password'])
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->post(route('login'), ['login' => 'benhnhan@phongkham.test', 'password' => 'sai-mat-khau'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_register_requires_consent_and_otp_verification(): void
    {
        $payload = [
            'name' => 'Người Dùng Mới', 'email' => 'moi@example.com', 'phone' => '0911222333',
            'date_of_birth' => '1995-01-01', 'gender' => 'male',
            'password' => 'matkhau123', 'password_confirmation' => 'matkhau123',
        ];

        // BR-11: bắt buộc đồng ý điều khoản xử lý dữ liệu
        $this->post(route('register'), $payload)->assertSessionHasErrors('consent');

        $this->post(route('register'), $payload + ['consent' => 1])->assertRedirect(route('verify.show'));
        $user = User::where('email', 'moi@example.com')->first();
        $this->assertNotNull($user->consent_at);
        $this->assertFalse($user->isVerified());
        $this->assertSame(1, $user->patients()->count());

        // Chưa xác thực OTP thì không vào được cổng bệnh nhân
        $this->get(route('account.dashboard'))->assertRedirect(route('verify.show'));

        $code = session('dev_otp') ?? '000000';
        $this->assertTrue(OtpCode::where('identifier', 'moi@example.com')->exists());
        if (session('dev_otp')) {
            $this->post(route('verify.submit'), ['otp' => $code])->assertRedirect(route('account.dashboard'));
            $this->assertTrue($user->fresh()->isVerified());
        }
    }

    public function test_patient_cannot_access_staff_area(): void
    {
        $this->loginAs('benhnhan@phongkham.test');
        $this->get(route('staff.dashboard'))->assertRedirect(route('account.dashboard'));
    }
}
