@extends('admin.layouts.app')

@section('title', 'Tài khoản bệnh nhân')
@section('page_title', 'Tài khoản bệnh nhân')
@section('breadcrumbs', 'Quản trị / Nhân sự / Tài khoản bệnh nhân')

@section('content')
  <div class="alert alert-light border small d-flex gap-2">
    <i class="bi bi-info-circle text-primary"></i>
    <div>
      Theo quy tắc BR-05: tài khoản vắng mặt từ <strong>{{ $threshold }}</strong> lần trở lên sẽ
      <strong>{{ $policy === 'block' ? 'bị khóa đặt lịch online' : 'phải đặt cọc khi đặt lịch' }}</strong>.
      Hiện có <strong>{{ $restrictedCount }}</strong> tài khoản đang bị hạn chế.
      <a href="{{ route('staff.admin.settings.edit') }}">Thay đổi tham số</a>
    </div>
  </div>

  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-5">
      <label class="form-label">Tìm kiếm</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên, email, số điện thoại">
    </div>
    <div class="col-md-4">
      <label class="form-label">Lọc</label>
      <select name="filter" class="form-select">
        <option value="">Tất cả</option>
        <option value="restricted" @selected(request('filter') === 'restricted')>Đang bị hạn chế no-show</option>
        <option value="locked" @selected(request('filter') === 'locked')>Đã khóa</option>
        <option value="unverified" @selected(request('filter') === 'unverified')>Chưa xác thực OTP</option>
      </select>
    </div>
    <div class="col-md-3"><button class="btn btn-outline-primary w-100"><i class="bi bi-search me-1"></i>Lọc</button></div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Tài khoản</th>
            <th>Liên hệ</th>
            <th class="text-center">Hồ sơ</th>
            <th class="text-center">Vắng mặt</th>
            <th>Ngày đăng ký</th>
            <th>Trạng thái</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($users as $user)
            <tr>
              <td>
                <div class="fw-semibold">{{ $user->name }}</div>
                @unless ($user->isVerified())<span class="badge bg-warning-subtle text-warning-emphasis">Chưa xác thực</span>@endunless
              </td>
              <td class="small">{{ $user->email }}<br><span class="text-muted">{{ $user->phone }}</span></td>
              <td class="text-center">{{ $user->patients_count }}</td>
              <td class="text-center">
                @if ($user->no_show_count >= $threshold)
                  <span class="badge bg-danger" title="Đang bị hạn chế">{{ $user->no_show_count }} <i class="bi bi-exclamation-triangle"></i></span>
                @elseif ($user->no_show_count)
                  <span class="badge bg-warning text-dark">{{ $user->no_show_count }}</span>
                @else
                  <span class="text-muted">0</span>
                @endif
              </td>
              <td class="small text-muted">{{ $user->created_at?->format('d/m/Y') }}</td>
              <td>
                @include('admin.management.partials.status', ['active' => $user->is_active, 'off' => 'Đã khóa'])
                @if (! $user->is_active && $user->locked_reason)<div class="small text-muted">{{ $user->locked_reason }}</div>@endif
              </td>
              <td class="text-end text-nowrap">
                @if ($user->no_show_count > 0)
                  <form method="POST" action="{{ route('staff.admin.patient-accounts.reset-no-show', $user) }}" class="d-inline" data-confirm="Gỡ hạn chế và đặt lại số lần vắng mặt của {{ $user->name }} về 0?">
                    @csrf
                    <button class="btn btn-sm btn-outline-warning" title="Gỡ hạn chế no-show"><i class="bi bi-arrow-counterclockwise"></i> Gỡ hạn chế</button>
                  </form>
                @endif
                @if ($user->is_active)
                  <form method="POST" action="{{ route('staff.admin.patient-accounts.toggle', $user) }}" class="d-inline" data-prompt="Lý do khóa tài khoản {{ $user->name }}" data-prompt-required="false">
                    @csrf
                    <button class="btn btn-sm btn-outline-danger" title="Khóa"><i class="bi bi-lock"></i></button>
                  </form>
                @else
                  <form method="POST" action="{{ route('staff.admin.patient-accounts.toggle', $user) }}" class="d-inline" data-confirm="Mở khóa tài khoản {{ $user->name }}?">
                    @csrf
                    <button class="btn btn-sm btn-outline-success" title="Mở khóa"><i class="bi bi-unlock"></i></button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-person-lock"></i>Không có tài khoản phù hợp.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($users->hasPages())
      <div class="card-footer">{{ $users->links() }}</div>
    @endif
  </div>
@endsection
