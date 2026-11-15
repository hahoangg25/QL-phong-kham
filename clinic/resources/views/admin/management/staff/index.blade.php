@extends('admin.layouts.app')

@section('title', 'Nhân viên')
@section('page_title', 'Tài khoản nhân viên')
@section('breadcrumbs', 'Quản trị / Nhân sự / Nhân viên')

@section('content')
  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-4">
      <label class="form-label">Tìm kiếm</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên, email, SĐT, mã nhân viên">
    </div>
    <div class="col-md-3">
      <label class="form-label">Vai trò</label>
      <select name="role" class="form-select">
        <option value="">Tất cả</option>
        @foreach ($roles as $value => $label)
          <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Trạng thái</label>
      <select name="status" class="form-select">
        <option value="">Tất cả</option>
        <option value="active" @selected(request('status') === 'active')>Hoạt động</option>
        <option value="locked" @selected(request('status') === 'locked')>Đã khóa</option>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
      <a href="{{ route('staff.admin.staff.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-person-plus me-1"></i>Thêm nhân viên</a>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Mã NV</th>
            <th>Nhân viên</th>
            <th>Liên hệ</th>
            <th>Vai trò</th>
            <th>Đăng nhập gần nhất</th>
            <th>Trạng thái</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($users as $user)
            @php $isMe = $user->id === auth()->id(); @endphp
            <tr>
              <td><code>{{ $user->employee_code ?? '—' }}</code></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <img src="{{ $user->avatarUrl() }}" width="36" height="36" class="rounded-circle" style="object-fit:cover" alt="">
                  <div>
                    <div class="fw-semibold">{{ $user->name }} @if ($isMe)<span class="badge bg-info-subtle text-info-emphasis">Bạn</span>@endif</div>
                    @if ($user->hasRole('doctor') && ! $user->doctor)
                      <a href="{{ route('staff.admin.doctors.create', ['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]) }}" class="small text-warning">
                        <i class="bi bi-exclamation-triangle"></i> Chưa có hồ sơ bác sĩ - tạo ngay
                      </a>
                    @endif
                  </div>
                </div>
              </td>
              <td class="small">{{ $user->email }}<br><span class="text-muted">{{ $user->phone }}</span></td>
              <td>
                @foreach ($user->roles as $role)
                  <span class="badge bg-primary-subtle text-primary-emphasis">{{ \App\Enums\Role::labelFor($role->name) }}</span>
                @endforeach
              </td>
              <td class="small text-muted">{{ $user->last_login_at?->diffForHumans() ?? 'Chưa đăng nhập' }}</td>
              <td>
                @include('admin.management.partials.status', ['active' => $user->is_active, 'off' => 'Đã khóa'])
                @if (! $user->is_active && $user->locked_reason)<div class="small text-muted">{{ $user->locked_reason }}</div>@endif
              </td>
              <td class="text-end text-nowrap">
                <a href="{{ route('staff.admin.staff.edit', $user) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                @unless ($isMe)
                  @if ($user->is_active)
                    <form method="POST" action="{{ route('staff.admin.staff.toggle', $user) }}" class="d-inline" data-prompt="Lý do khóa tài khoản {{ $user->name }}" data-prompt-required="false">
                      @csrf
                      <button class="btn btn-sm btn-outline-danger" title="Khóa"><i class="bi bi-lock"></i></button>
                    </form>
                  @else
                    <form method="POST" action="{{ route('staff.admin.staff.toggle', $user) }}" class="d-inline" data-confirm="Mở khóa tài khoản {{ $user->name }}?">
                      @csrf
                      <button class="btn btn-sm btn-outline-success" title="Mở khóa"><i class="bi bi-unlock"></i></button>
                    </form>
                  @endif
                @endunless
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i>Không có nhân viên phù hợp.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($users->hasPages())
      <div class="card-footer">{{ $users->links() }}</div>
    @endif
  </div>
@endsection
