@extends('admin.layouts.app')

@section('title', 'Chỉ định '.$order->service->name)
@section('page_title', $order->service->name)
@section('breadcrumbs')
  <a href="{{ route('staff.lab.index') }}">Cận lâm sàng</a> / {{ $visit->patient->full_name }}
@endsection

@php
  $patient = $visit->patient;
  $result = $order->result;
  $editable = $order->status !== \App\Enums\ServiceOrderStatus::Cancelled && ! $visit->isLocked();
@endphp

@section('content')
  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-clipboard2-plus me-1"></i>Thông tin chỉ định</span>
          {!! status_badge($order->status) !!}
        </div>
        <div class="card-body small">
          <dl class="row mb-0">
            <dt class="col-5 fw-normal text-muted">Dịch vụ</dt><dd class="col-7 fw-semibold">{{ $order->service->name }}</dd>
            <dt class="col-5 fw-normal text-muted">Loại</dt><dd class="col-7">{{ $order->service->type->label() }}</dd>
            <dt class="col-5 fw-normal text-muted">Bác sĩ chỉ định</dt><dd class="col-7">{{ $visit->doctor->display_name }}</dd>
            <dt class="col-5 fw-normal text-muted">Thời điểm</dt><dd class="col-7">{{ $order->created_at->format('H:i d/m/Y') }}</dd>
            @if ($order->started_at)
              <dt class="col-5 fw-normal text-muted">Bắt đầu thực hiện</dt><dd class="col-7">{{ $order->started_at->format('H:i d/m/Y') }}</dd>
            @endif
            <dt class="col-5 fw-normal text-muted">Ghi chú bác sĩ</dt><dd class="col-7">{{ $order->note ?: '—' }}</dd>
          </dl>
          @if ($order->status === \App\Enums\ServiceOrderStatus::Pending)
            <form method="POST" action="{{ route('staff.lab.start', $order) }}" class="mt-2">@csrf
              <button class="btn btn-primary btn-sm w-100"><i class="bi bi-play-fill me-1"></i>Bắt đầu thực hiện</button>
            </form>
          @endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-person me-1"></i>Bệnh nhân</div>
        <div class="card-body small">
          <div class="fw-semibold fs-6">{{ $patient->full_name }}</div>
          <div class="text-muted mb-2">{{ $patient->code }} · {{ $patient->genderLabel() }}{{ $patient->age !== null ? ' · '.$patient->age.' tuổi' : '' }}{{ $patient->phone ? ' · '.$patient->phone : '' }}</div>
          @if ($patient->allergies)<div class="allergy-alert mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>Dị ứng: {{ $patient->allergies }}</div>@endif
          @if ($visit->vitalsSummary())<div><strong>Sinh hiệu:</strong> {{ $visit->vitalsSummary() }}</div>@endif
          @if ($visit->reason)<div><strong>Lý do khám:</strong> {{ $visit->reason }}</div>@endif
          @if ($visit->diagnoses->isNotEmpty())
            <div><strong>Chẩn đoán:</strong> {{ $visit->diagnoses->map(fn ($d) => $d->icd10_code.' '.$d->name)->implode('; ') }}</div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header"><i class="bi bi-list-task me-1"></i>Các chỉ định khác của lượt khám</div>
        <ul class="list-group list-group-flush">
          @foreach ($visit->serviceOrders->where('id', '!=', $order->id) as $other)
            <li class="list-group-item d-flex justify-content-between align-items-center small">
              <a href="{{ route('staff.lab.show', $other) }}">{{ $other->service->name }}</a>
              {!! status_badge($other->status) !!}
            </li>
          @endforeach
          @if ($visit->serviceOrders->count() <= 1)
            <li class="list-group-item small text-muted">Không có.</li>
          @endif
        </ul>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card">
        <div class="card-header"><i class="bi bi-file-earmark-medical me-1"></i>{{ $result ? 'Kết quả' : 'Nhập kết quả' }}</div>
        <div class="card-body">
          @if ($result)
            <div class="alert alert-success py-2 small">
              <i class="bi bi-check-circle me-1"></i>Đã trả kết quả lúc {{ $result->completed_at?->format('H:i d/m/Y') }} bởi {{ $result->performer?->name ?? '—' }}.
              @if ($editable) Bạn vẫn có thể cập nhật kết quả, bác sĩ sẽ nhận thông báo. @endif
            </div>
          @endif

          @if (! $editable)
            @if ($order->status === \App\Enums\ServiceOrderStatus::Cancelled)
              <div class="alert alert-secondary"><i class="bi bi-x-circle me-1"></i>Chỉ định này đã bị bác sĩ hủy.</div>
            @else
              <div class="locked-badge mb-3"><i class="bi bi-lock-fill me-1"></i>Bệnh án đã hoàn thành và hồ sơ đã khóa, không thể chỉnh sửa kết quả.</div>
            @endif
            @if ($result)
              <div class="p-3 bg-light rounded border mb-3">
                <div class="fw-semibold text-secondary mb-1">Mô tả kết quả:</div>
                <div style="white-space: pre-line">{{ $result->result }}</div>
                @if ($result->conclusion)
                  <hr class="my-2">
                  <div><strong>Kết luận:</strong> <span class="text-primary fw-semibold">{{ $result->conclusion }}</span></div>
                @endif
              </div>

              @if ($result->attachment_path)
                <div class="p-2 border rounded bg-light mb-3">
                  <div class="small fw-semibold mb-2"><i class="bi bi-image me-1"></i>Phim X-Quang / Tài liệu đính kèm:</div>
                  @if ($result->isImageAttachment())
                    <div class="mb-2">
                      <a href="{{ route('staff.lab-results.attachment', $result) }}" target="_blank">
                        <img src="{{ route('staff.lab-results.attachment', $result) }}" alt="Ảnh phim X-Quang" class="img-thumbnail" style="max-height: 250px;">
                      </a>
                    </div>
                  @endif
                  <a href="{{ route('staff.lab-results.attachment', $result) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Xem tệp đính kèm: {{ $result->attachment_name ?? 'Ảnh chụp / PDF' }}
                  </a>
                </div>
              @endif
            @endif
          @else
            <form method="POST" action="{{ route('staff.lab.result', $order) }}" enctype="multipart/form-data">
              @csrf
              <div class="mb-3">
                <label class="form-label required">Mô tả kết quả / hình ảnh</label>
                <textarea name="result" rows="6" class="form-control @error('result') is-invalid @enderror" required maxlength="10000"
                          placeholder="Ví dụ mô tả phim X-quang / CT:&#10;- Hình ảnh tổn thương quanh cuống răng 36, tiêu xương đường kính ~3mm&#10;- Răng 48 mọc lệch nghiêng gần 80 độ, chèn ép chân răng 47&#10;- Không phát hiện u nang hoặc bất thường tại xoang hàm">{{ old('result', $result?->result) }}</textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Kết luận chẩn đoán hình ảnh</label>
                <input type="text" name="conclusion" value="{{ old('conclusion', $result?->conclusion) }}" class="form-control" maxlength="2000" placeholder="Ví dụ: Viêm quanh cuống răng 36 / Răng khôn 48 mọc lệch ngầm">
              </div>
              <div class="mb-3">
                <label class="form-label">Tệp phim X-quang / Kết quả (Ảnh hoặc PDF, tối đa 5MB)</label>
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control @error('attachment') is-invalid @enderror">
                @if ($result?->attachment_path)
                  <div class="mt-2 p-2 border rounded bg-light">
                    <div class="small fw-semibold mb-1"><i class="bi bi-paperclip me-1"></i>Tệp đính kèm hiện tại:</div>
                    @if ($result->isImageAttachment())
                      <div class="mb-2">
                        <a href="{{ route('staff.lab-results.attachment', $result) }}" target="_blank">
                          <img src="{{ route('staff.lab-results.attachment', $result) }}" alt="Ảnh phim cận lâm sàng" class="img-thumbnail" style="max-height: 180px;">
                        </a>
                      </div>
                    @endif
                    <a href="{{ route('staff.lab-results.attachment', $result) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-box-arrow-up-right me-1"></i>Xem/Tải: {{ $result->attachment_name ?? 'Ảnh chụp / PDF' }}
                    </a>
                    <span class="small text-muted ms-2">(Chọn tệp mới ở trên nếu muốn thay thế)</span>
                  </div>
                @endif
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted"><i class="bi bi-bell me-1"></i>Lưu xong hệ thống sẽ tự động cập nhật kết quả và thông báo cho Bác sĩ điều trị.</small>
                <button class="btn btn-primary"><i class="bi bi-send-check me-1"></i>{{ $result ? 'Cập nhật kết quả' : 'Lưu và trả kết quả' }}</button>
              </div>
            </form>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
