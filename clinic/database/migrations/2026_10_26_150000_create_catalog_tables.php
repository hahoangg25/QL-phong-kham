<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ChuyenKhoa (QT-03)
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 50)->nullable();
            $table->string('image')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            // BN-20: từ khóa triệu chứng để gợi ý chuyên khoa
            $table->text('keywords')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // PhongKham (QT-04)
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('floor', 20)->nullable();
            $table->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // BacSi (QT-05) - hồ sơ chuyên môn gắn với tài khoản nhân viên
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 50)->nullable(); // học hàm/học vị: ThS.BS, BSCKII...
            $table->string('position')->nullable();
            $table->unsignedTinyInteger('experience_years')->default(0);
            $table->unsignedInteger('consultation_fee')->default(0);
            $table->string('avatar')->nullable();
            $table->string('short_bio', 500)->nullable();
            $table->text('bio')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // DichVu (QT-06): khám, xét nghiệm, chẩn đoán hình ảnh, thủ thuật
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20); // App\Enums\ServiceType
            $table->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('price')->default(0);
            $table->string('unit', 30)->default('lần');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Danh mục ICD-10 (QT-07)
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('chapter', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd10_codes');
        Schema::dropIfExists('services');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('specialties');
    }
};
