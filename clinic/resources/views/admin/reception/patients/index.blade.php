@extends('admin.layouts.app')

@section('title', 'Bệnh nhân')
@section('page_title', 'Tra cứu bệnh nhân')
@section('breadcrumbs', 'Lễ tân / Bệnh nhân')

@section('content')
  <form method="GET" class="filter-bar">
    <div class="row g-2 align-items-center">
      <div class="col-md-6">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên, số điện thoại, mã bệnh nhân (BN...) hoặc CCCD" autofocus>
          <button class="btn btn-primary">Tìm</button>
        </div>
      </div>
      <div class="col-md-6 text-md-end">
        <span class="small text-muted me-2">{{ number_format($patients->total()) }} hồ sơ</span>
        @can('patients.manage')
          <a href="{{ route('staff.patients.create') }}" class="btn btn-success"><i class="bi bi-person-plus me-1"></i>Thêm bệnh nhân</a>
        @endcan
      </div>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr><th>Mã BN</th><th>Họ tên</th><th>Ngày sinh</th><th>Giới tính</th><th>Điện thoại</th><th>CCCD</th><th>Tài khoản</th><th class="text-end">Thao tác</th></tr>
        </thead>
        <tbody>
          @forelse ($patients as $p)
            <tr>
              <td><a href="{{ route('staff.patients.show', $p) }}" class="fw-semibold font-monospace">{{ $p->code }}</a></td>
              <td>
                {{ $p->full_name }}
                @if ($p->isPriority())<span class="badge bg-warning text-dark ms-1" title="Thuộc diện ưu tiên theo độ tuổi">Ưu tiên</span>@endif
              </td>
              <td>{{ $p->date_of_birth?->format('d/m/Y') ?? '-' }} @if ($p->age !== null)<span class="small text-muted">({{ $p->age }} tuổi)</span>@endif</td>
              <td>{{ $p->genderLabel() ?: '-' }}</td>
              <td>{{ $p->phone ?: '-' }}</td>
              <td>{{ $p->id_number ?: '-' }}</td>
              <td>
                @if ($p->user)
                  <span class="small">{{ $p->user->name }}</span>
                  <div class="small text-muted">{{ $p->relationship }}</div>
                @else
                  <span class="badge bg-light text-muted border">Vãng lai</span>
                @endif
              </td>
              <td class="text-end text-nowrap">
                <a href="{{ route('staff.patients.show', $p) }}" class="btn btn-sm btn-outline-secondary" title="Xem"><i class="bi bi-eye"></i></a>
                @can('patients.manage')
                  <a href="{{ route('staff.patients.edit', $p) }}" class="btn btn-sm btn-outline-secondary" title="Sửa"><i class="bi bi-pencil"></i></a>
                @endcan
                @can('appointments.manage')
                  <a href="{{ route('staff.appointments.create', ['patient_id' => $p->id]) }}" class="btn btn-sm btn-outline-primary" title="Đặt lịch"><i class="bi bi-calendar-plus"></i></a>
                @endcan
                @can('reception.manage')
                  <a href="{{ route('staff.reception.walkin', ['patient_id' => $p->id]) }}" class="btn btn-sm btn-outline-primary" title="Tiếp nhận khám ngay"><i class="bi bi-ticket-perforated"></i></a>
                @endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="empty-state"><i class="bi bi-person-x"></i>Không tìm thấy bệnh nhân.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($patients->hasPages())
      <div class="card-footer">{{ $patients->links() }}</div>
    @endif
  </div>
@endsection
