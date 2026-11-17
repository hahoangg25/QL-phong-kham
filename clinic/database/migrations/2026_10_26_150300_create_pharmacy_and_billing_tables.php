<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Thuoc (TD-07)
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('active_ingredient')->nullable();
            $table->string('strength', 50)->nullable(); // hàm lượng
            $table->string('dosage_form', 50)->nullable(); // dạng bào chế
            $table->string('unit', 30); // đơn vị tính
            $table->string('route', 30)->default('Uống');
            $table->unsignedInteger('price');
            $table->unsignedInteger('min_stock')->default(0); // định mức tồn tối thiểu (TD-10)
            $table->string('manufacturer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('name');
        });

        // Phiếu nhập kho (TD-08)
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('supplier');
            $table->date('received_date');
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // LoThuoc: xuất kho theo FEFO (hết hạn trước xuất trước)
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_no', 50);
            $table->date('expiry_date');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('remaining');
            $table->unsignedInteger('import_price')->default(0);
            $table->timestamps();
            $table->index(['medicine_id', 'expiry_date']);
        });

        // Thẻ kho: mọi biến động nhập/xuất/hủy/điều chỉnh (TD-09, TD-11)
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // import | dispense | dispose | adjust
            $table->integer('quantity'); // dương: nhập, âm: xuất
            $table->nullableMorphs('reference');
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['medicine_id', 'created_at']);
        });

        // DonThuoc (BS-09)
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('doctor_id')->constrained();
            $table->string('status', 20)->default('pending'); // App\Enums\PrescriptionStatus
            $table->string('note', 500)->nullable();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();
        });

        // ChiTietDonThuoc
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->string('dosage', 100); // liều mỗi lần
            $table->string('usage')->nullable(); // cách dùng
            $table->unsignedSmallInteger('days')->default(1);
            $table->unsignedInteger('unit_price');
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });

        // HoaDon (TD-01)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('visit_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->string('status', 20)->default('unpaid'); // App\Enums\InvoiceStatus
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('deposit_deducted')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'paid_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20); // exam | service | medicine
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price');
            $table->unsignedBigInteger('amount');
            $table->nullableMorphs('reference');
            $table->timestamps();
        });

        // ThanhToan: thu tiền hóa đơn, đặt cọc lịch hẹn, hoàn tiền (TD-02, TD-04, BN-10, HT-08)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->string('type', 20)->default('payment'); // payment | deposit | refund
            $table->string('method', 20); // App\Enums\PaymentMethod
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending'); // App\Enums\PaymentStatus
            $table->string('transaction_no')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('stock_receipts');
        Schema::dropIfExists('medicines');
    }
};
