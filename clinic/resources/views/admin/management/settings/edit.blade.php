@extends('admin.layouts.app')

@section('title', 'Cấu hình quy tắc')
@section('page_title', 'Cấu hình quy tắc nghiệp vụ')
@section('breadcrumbs', 'Quản trị / Cấu hình quy tắc')

@section('content')
  <div class="row g-4">
    <div class="col-xl-8">
      <form method="POST" action="{{ route('staff.admin.settings.update') }}" class="card">
        @csrf
        @method('PUT')
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Tham số quy tắc (BR)</span>
          <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Lưu cấu hình</button>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th style="width:90px">Mã</th>
                <th>Tham số</th>
                <th style="width:260px">Giá trị</th>
                <th style="width:120px" class="text-end">Mặc định</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($definitions as $key => $def)
                @php $value = old($key, $values[$key] ?? $def['default']); @endphp
                <tr>
                  <td><span class="badge bg-primary-subtle text-primary-emphasis">{{ $def['code'] }}</span></td>
                  <td>
                    <label for="s_{{ $key }}" class="fw-medium mb-0">{{ $def['label'] }}</label><br>
                    <code class="small text-muted">{{ $key }}</code>
                  </td>
                  <td>
                    @if ($def['type'] === 'bool')
                      <div class="form-check form-switch">
                        <input type="hidden" name="{{ $key }}" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="{{ $key }}" value="1" id="s_{{ $key }}" @checked((bool) $value)>
                        <label class="form-check-label small" for="s_{{ $key }}">Bật</label>
                      </div>
                    @elseif ($def['type'] === 'select')
                      <select name="{{ $key }}" id="s_{{ $key }}" class="form-select form-select-sm">
                        @foreach ($def['options'] as $optValue => $optLabel)
                          <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                        @endforeach
                      </select>
                    @else
                      <input type="number" min="0" name="{{ $key }}" id="s_{{ $key }}" value="{{ $value }}" class="form-control form-control-sm @error($key) is-invalid @enderror" required>
                    @endif
                  </td>
                  <td class="text-end small text-muted">
                    @if ($def['type'] === 'bool')
                      {{ $def['default'] ? 'Bật' : 'Tắt' }}
                    @elseif ($def['type'] === 'select')
                      {{ $def['options'][$def['default']] ?? $def['default'] }}
                    @else
                      {{ is_numeric($def['default']) ? number_format($def['default'], 0, ',', '.') : $def['default'] }}
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="card-footer text-end">
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu cấu hình</button>
        </div>
      </form>
    </div>

    <div class="col-xl-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-journal-text me-1"></i>Các quy tắc liên quan</div>
        <div class="card-body small">
          <dl class="mb-0">
            <dt>BR-02 - Thời điểm đặt lịch</dt>
            <dd class="text-muted">Đặt cách giờ khám tối thiểu X giờ và không quá Y ngày.</dd>
            <dt>BR-04 - Hủy lịch</dt>
            <dd class="text-muted">Hủy miễn phí nếu trước giờ khám X giờ; hủy trễ hơn tính một lần vắng mặt.</dd>
            <dt>BR-05 - Hạn chế no-show</dt>
            <dd class="text-muted">Tích lũy N lần vắng mặt thì khóa đặt online hoặc bắt buộc đặt cọc.</dd>
            <dt>BR-06 - Check-in trễ</dt>
            <dd class="text-muted">Trễ quá K phút bị chuyển xuống cuối hàng đợi.</dd>
            <dt>HT-01 - Giữ slot</dt>
            <dd class="text-muted">Thời gian giữ chỗ tạm trong lúc bệnh nhân xác thực OTP.</dd>
            <dt>HT-04 - Đánh dấu vắng mặt</dt>
            <dd class="text-muted">Quá giờ hẹn bao lâu mà chưa check-in thì hệ thống chuyển sang Vắng mặt.</dd>
          </dl>
          <hr>
          <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>Giá trị mặc định nằm trong <code>config/clinic.php</code>; giá trị lưu tại đây ghi đè mặc định và áp dụng ngay cho mọi chức năng.</p>
        </div>
      </div>
    </div>
  </div>
@endsection
