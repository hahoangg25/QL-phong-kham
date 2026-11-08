@extends('admin.layouts.app')

@section('title', 'Nghỉ / đổi ca')
@section('page_title', 'Yêu cầu nghỉ / đổi ca')

@section('content')
  @if (! $doctor)
    <div class="card">
      <div class="empty-state">
        <i class="bi bi-calendar-x"></i>
        <h5 class="text-body">Tài khoản chưa có hồ sơ bác sĩ</h5>
        <p class="mb-0">Chức năng này dành cho bác sĩ gửi yêu cầu nghỉ để quản trị viên duyệt.</p>
      </div>
    </div>
  @else
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header"><i class="bi bi-send me-1"></i>Gửi yêu cầu mới</div>
          <div class="card-body">
            <form method="POST" action="{{ route('staff.leave.store') }}">
              @csrf
              <div class="mb-3">
                <label class="form-label required">Từ ngày</label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" min="{{ today()->toDateString() }}" class="form-control @error('start_date') is-invalid @enderror" required>
              </div>
              <div class="mb-3">
                <label class="form-label required">Đến ngày</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" min="{{ today()->toDateString() }}" class="form-control @error('end_date') is-invalid @enderror" required>
              </div>
              <div class="mb-3">
                <label class="form-label required">Lý do</label>
                <textarea name="reason" rows="3" maxlength="500" class="form-control @error('reason') is-invalid @enderror" required placeholder="Ví dụ: tham dự hội nghị chuyên ngành, việc gia đình...">{{ old('reason') }}</textarea>
              </div>
              <button class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Gửi yêu cầu</button>
            </form>
            <div class="form-text mt-2">
              Khi được duyệt, các khung giờ trong thời gian nghỉ sẽ ngừng nhận đặt lịch. Lịch hẹn đã có sẽ được quản trị viên
              chuyển sang bác sĩ khác hoặc thông báo cho bệnh nhân (QT-10).
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card">
          <div class="card-header"><i class="bi bi-list-check me-1"></i>Yêu cầu của tôi</div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead><tr><th>Thời gian nghỉ</th><th>Lý do</th><th>Trạng thái</th><th>Phản hồi</th><th></th></tr></thead>
              <tbody>
                @forelse ($leaves as $leave)
                  <tr>
                    <td class="text-nowrap">
                      {{ $leave->start_date->format('d/m/Y') }}
                      @if (! $leave->start_date->isSameDay($leave->end_date)) - {{ $leave->end_date->format('d/m/Y') }} @endif
                      <div class="small text-muted">{{ (int) $leave->start_date->diffInDays($leave->end_date) + 1 }} ngày · gửi {{ $leave->created_at->format('d/m') }}</div>
                    </td>
                    <td class="small">{{ $leave->reason }}</td>
                    <td>{!! status_badge($leave->status) !!}</td>
                    <td class="small">
                      @if ($leave->reviewed_at)
                        {{ $leave->reviewer?->name }} · {{ $leave->reviewed_at->format('d/m H:i') }}
                        @if ($leave->review_note)<div class="text-muted">{{ $leave->review_note }}</div>@endif
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td class="text-end">
                      @if ($leave->status === \App\Enums\LeaveStatus::Pending)
                        <form method="POST" action="{{ route('staff.leave.destroy', $leave) }}" data-confirm="Rút lại yêu cầu nghỉ này?">
                          @csrf @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger" title="Rút lại"><i class="bi bi-trash"></i></button>
                        </form>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5"><div class="empty-state py-4"><i class="bi bi-calendar-check"></i>Chưa có yêu cầu nghỉ nào.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @if ($leaves instanceof \Illuminate\Contracts\Pagination\Paginator && $leaves->hasPages())
            <div class="card-footer">{{ $leaves->links() }}</div>
          @endif
        </div>
      </div>
    </div>
  @endif
@endsection
