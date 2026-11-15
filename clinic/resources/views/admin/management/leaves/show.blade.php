@extends('admin.layouts.app')

@php $pending = $leave->status === \App\Enums\LeaveStatus::Pending; @endphp

@section('title', 'Yêu cầu nghỉ')
@section('page_title', 'Yêu cầu nghỉ của '.$leave->doctor?->display_name)
@section('breadcrumbs')
  <a href="{{ route('staff.admin.leaves.index') }}">Duyệt nghỉ</a> / Chi tiết
@endsection

@section('content')
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between">Thông tin yêu cầu {!! status_badge($leave->status) !!}</div>
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-3">
            <img src="{{ $leave->doctor?->avatarUrl() }}" width="56" height="56" class="rounded-circle" style="object-fit:cover;object-position:top" alt="">
            <div>
              <div class="fw-semibold">{{ $leave->doctor?->display_name }}</div>
              <small class="text-muted">{{ $leave->doctor?->specialty?->name }}</small>
            </div>
          </div>
          <dl class="row mb-0 small">
            <dt class="col-5">Từ ngày</dt><dd class="col-7">{{ $leave->start_date->translatedFormat('l, d/m/Y') }}</dd>
            <dt class="col-5">Đến ngày</dt><dd class="col-7">{{ $leave->end_date->translatedFormat('l, d/m/Y') }}</dd>
            <dt class="col-5">Số ngày</dt><dd class="col-7">{{ $leave->start_date->diffInDays($leave->end_date) + 1 }}</dd>
            <dt class="col-5">Gửi lúc</dt><dd class="col-7">{{ $leave->created_at->format('H:i d/m/Y') }}</dd>
            <dt class="col-5">Lý do</dt><dd class="col-7">{{ $leave->reason }}</dd>
            @if ($leave->reviewer)
              <dt class="col-5">Người duyệt</dt><dd class="col-7">{{ $leave->reviewer->name }} · {{ $leave->reviewed_at?->format('H:i d/m/Y') }}</dd>
              <dt class="col-5">Ghi chú</dt><dd class="col-7">{{ $leave->review_note ?: '—' }}</dd>
            @endif
          </dl>
        </div>
        @if ($pending)
          <div class="card-footer">
            <form method="POST" action="{{ route('staff.admin.leaves.approve', $leave) }}" class="mb-2">
              @csrf
              <textarea name="note" rows="2" class="form-control mb-2" placeholder="Ghi chú (không bắt buộc)"></textarea>
              <button class="btn btn-success w-100" data-no-disable><i class="bi bi-check2-circle me-1"></i>Duyệt nghỉ và khóa khung giờ</button>
            </form>
            <form method="POST" action="{{ route('staff.admin.leaves.reject', $leave) }}" data-prompt="Lý do từ chối" data-prompt-field="note" data-prompt-required="false">
              @csrf
              <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-1"></i>Từ chối</button>
            </form>
          </div>
        @endif
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Lịch hẹn bị ảnh hưởng ({{ $appointments->count() }})</span>
          @if ($pending && $appointments->isNotEmpty())
            <small class="text-warning fw-normal"><i class="bi bi-exclamation-triangle me-1"></i>Nên duyệt trước để khóa slot, sau đó xử lý từng lịch</small>
          @endif
        </div>
        @if ($appointments->isEmpty())
          <div class="empty-state"><i class="bi bi-calendar2-check"></i>Không có lịch hẹn nào cần xử lý trong thời gian nghỉ.</div>
        @else
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Lịch hẹn</th>
                  <th>Bệnh nhân</th>
                  <th style="min-width:280px">Chuyển sang bác sĩ khác (cùng giờ)</th>
                  <th class="text-end">Hủy & báo</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($appointments as $a)
                  @php $alts = $alternatives[$a->id] ?? collect(); @endphp
                  <tr>
                    <td>
                      <div class="fw-semibold">{{ $a->scheduled_at->format('H:i d/m/Y') }}</div>
                      <small class="text-muted">{{ $a->code }}</small> {!! status_badge($a->status) !!}
                    </td>
                    <td>
                      {{ $a->patient?->full_name }}<br>
                      <small class="text-muted">{{ $a->patient?->phone }}</small>
                    </td>
                    <td>
                      @if ($alts->isEmpty())
                        <span class="small text-muted">Không có bác sĩ cùng chuyên khoa còn chỗ cùng giờ</span>
                      @else
                        <form method="POST" action="{{ route('staff.admin.leaves.reassign', $a) }}" class="d-flex gap-2" data-confirm="Chuyển lịch {{ $a->code }} sang bác sĩ đã chọn và thông báo bệnh nhân?">
                          @csrf
                          <select name="time_slot_id" class="form-select form-select-sm" required>
                            @foreach ($alts as $slot)
                              <option value="{{ $slot->id }}">{{ $slot->doctor->display_name }} (còn {{ $slot->capacity - $slot->booked_count }})</option>
                            @endforeach
                          </select>
                          <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-arrow-left-right"></i> Chuyển</button>
                        </form>
                      @endif
                    </td>
                    <td class="text-end">
                      <form method="POST" action="{{ route('staff.admin.leaves.cancel-appointment', $a) }}" data-confirm="Hủy lịch {{ $a->code }} và thông báo bệnh nhân đặt lại? (không tính vắng mặt)">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-calendar-x"></i></button>
                      </form>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
@endsection
