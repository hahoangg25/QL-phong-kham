<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BenhNhan: một tài khoản quản lý nhiều hồ sơ (BN-04); bệnh nhân vãng lai có thể không có tài khoản
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('relationship', 30)->default('Bản thân');
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('id_number', 20)->nullable()->index(); // CCCD
            $table->string('insurance_number', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('occupation')->nullable();
            $table->string('blood_type', 5)->nullable();
            // BR-11: dữ liệu y tế nhạy cảm được mã hóa ở tầng ứng dụng (cast encrypted)
            $table->text('allergies')->nullable();
            $table->text('medical_history')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('full_name');
        });

        // CaLamViec (QT-08): lịch tuần lặp lại của bác sĩ
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Chủ nhật ... 6 = Thứ 7 (Carbon dayOfWeek)
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_minutes')->default(30);
            $table->unsignedSmallInteger('max_patients_per_slot')->default(2);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // KhungGio (QT-09): slot sinh tự động từ ca làm việc
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('capacity');
            // BR-01: số lịch đang chiếm chỗ, chỉ cập nhật trong transaction có khóa dòng
            $table->unsignedSmallInteger('booked_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['doctor_id', 'date', 'start_time']);
            $table->index(['date', 'is_active']);
        });

        // HT-01: giữ slot tạm trong lúc chờ xác thực OTP
        Schema::create('slot_holds', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained();
            $table->text('symptoms')->nullable();
            $table->foreignId('reschedule_from_id')->nullable(); // đổi lịch (BN-13)
            $table->string('otp_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        // Yêu cầu nghỉ/đổi ca của bác sĩ (BS-02, QT-10)
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('slot_holds');
        Schema::dropIfExists('time_slots');
        Schema::dropIfExists('work_shifts');
        Schema::dropIfExists('patients');
    }
};
