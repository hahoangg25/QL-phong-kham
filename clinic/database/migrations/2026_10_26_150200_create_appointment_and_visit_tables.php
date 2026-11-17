<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LichHen: ý định đặt chỗ (tách khỏi LuotKham)
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('doctor_id')->constrained();
            $table->foreignId('specialty_id')->constrained();
            $table->foreignId('time_slot_id')->constrained();
            $table->date('appointment_date');
            $table->dateTime('scheduled_at');
            $table->string('status', 20)->index(); // App\Enums\AppointmentStatus
            $table->string('source', 20)->default('online'); // online | phone | follow_up
            $table->text('symptoms')->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('deposit_required')->default(0);
            $table->unsignedInteger('prepaid_amount')->default(0);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->boolean('is_late_cancel')->default(false);
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('rescheduled_to_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamp('reminded_2h_at')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'appointment_date']);
            $table->index(['doctor_id', 'appointment_date']);
            $table->index(['status', 'scheduled_at']);
        });

        // Ghi vết mọi lần chuyển trạng thái lịch hẹn (người thực hiện, thời điểm)
        Schema::create('appointment_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // LuotKham: sự kiện khám thực tế (có thể không có lịch hẹn - vãng lai)
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('doctor_id')->constrained();
            $table->foreignId('specialty_id')->constrained();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->date('visit_date');
            $table->unsignedSmallInteger('queue_number');
            $table->boolean('is_priority')->default(false);
            // Khóa sắp xếp hàng đợi: giờ hẹn nếu đến đúng giờ, giờ check-in nếu vãng lai/trễ (BR-06)
            $table->dateTime('queue_sort');
            $table->string('status', 20)->index(); // App\Enums\VisitStatus
            $table->string('type', 20)->default('appointment'); // appointment | walk_in
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // Khám lâm sàng (BS-05)
            $table->string('reason', 500)->nullable();
            $table->text('symptoms')->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('bp_systolic')->nullable();
            $table->unsignedSmallInteger('bp_diastolic')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedTinyInteger('spo2')->nullable();
            $table->decimal('weight', 5, 1)->nullable();
            $table->decimal('height', 5, 1)->nullable();
            $table->text('clinical_notes')->nullable();
            $table->text('conclusion')->nullable();
            $table->text('advice')->nullable(); // dặn dò (BS-11)
            $table->date('follow_up_date')->nullable();
            $table->timestamp('locked_at')->nullable(); // BS-12: khóa bệnh án
            $table->timestamps();
            $table->unique(['doctor_id', 'visit_date', 'queue_number']);
            $table->index(['visit_date', 'status']);
        });

        // BR-08: bệnh án đã hoàn thành chỉ được bổ sung, có ghi vết
        Schema::create('visit_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('content');
            $table->timestamps();
        });

        // ChanDoan (BS-06)
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('icd10_code', 10);
            $table->string('name');
            $table->boolean('is_primary')->default(false);
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        // ChiDinh (BS-07, CL-01, CL-02)
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('ordered_by')->constrained('users');
            $table->unsignedInteger('price');
            $table->string('status', 20)->default('pending'); // App\Enums\ServiceOrderStatus
            $table->string('note', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });

        // KetQuaCLS (BS-08, CL-03, CL-04)
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('result');
            $table->text('conclusion')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();
        });

        // DanhGia (BN-19, QT-13)
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('service_orders');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('visit_addenda');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('appointment_status_logs');
        Schema::dropIfExists('appointments');
    }
};
