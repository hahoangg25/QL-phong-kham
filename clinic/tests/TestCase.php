<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Mỗi test chạy trên SQLite in-memory với dữ liệu nền (TestSeeder). */
    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    /** Đăng nhập bằng tài khoản demo theo email, vd: $this->loginAs('letan@phongkham.test'). */
    protected function loginAs(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }
}
