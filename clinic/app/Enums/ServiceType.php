<?php

namespace App\Enums;

enum ServiceType: string
{
    case Exam = 'exam';
    case Lab = 'lab';
    case Imaging = 'imaging';
    case Procedure = 'procedure';

    public function label(): string
    {
        return match ($this) {
            self::Exam => 'Khám bệnh',
            self::Lab => 'Xét nghiệm',
            self::Imaging => 'Chẩn đoán hình ảnh',
            self::Procedure => 'Thủ thuật',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Exam => 'bi-clipboard2-pulse',
            self::Lab => 'bi-droplet-half',
            self::Imaging => 'bi-radioactive',
            self::Procedure => 'bi-bandaid',
        };
    }

    /** Dịch vụ cận lâm sàng có thể chỉ định (BS-07). */
    public function isParaclinical(): bool
    {
        return $this !== self::Exam;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
