@extends('admin.layouts.app')

@section('title', 'Duyệt nghỉ bác sĩ')
@section('page_title', 'Duyệt nghỉ / đổi ca bác sĩ')
@section('breadcrumbs', 'Quản trị / Lịch làm việc / Duyệt nghỉ')

@section('content')
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link {{ $tab === 'pending' ? 'active' : '' }}" href="{{ route('staff.admin.leaves.index', ['tab' => 'pending']) }}">
        Chờ duyệt @if ($pendingCount)<span class="badge bg-danger ms-1">{{ $pendingCount }}</span>@endif
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab === 'all' ? 'active' : '' }}" href="{{ route('staff.admin.leaves.index', ['tab' => 'all']) }}">Tất cả</a>
    </li>
  </ul>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Bác sĩ</th>
            <th>Thời gian nghỉ</th>
            <th>Lý do</th>
            <th class="text-center">Lịch bị ảnh hưởng</th>
            <th>Trạng thái</th>
            <th>Người duyệt</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($requests as $leave)
            <tr>
              <td>
                <div class="fw-semibold">{{ $leave->doctor?->display_name }}</div>
                <small class="text-muted">{{ $leave->doctor?->specialty?->name }}</small>
              </td>
              <td class="text-nowrap">
                {{ $leave->start_date->format('d/m/Y') }}
                @if (! $leave->start_date->equalTo($leave->end_date)) → {{ $leave->end_date->format('d/m/Y') }} @endif
                <br><small class="text-muted">{{ $leave->start_date->diffInDays($leave->end_date) + 1 }} ngày · gửi {{ $leave->created_at->diffForHumans() }}</small>
              </td>
              <td style="max-width:280px">{{ \Illuminate\Support\Str::limit($leave->reason, 90) }}</td>
              <td class="text-center">
                @if ($affected[$leave->id] ?? 0)
                  <span class="badge bg-warning text-dark">{{ $affected[$leave->id] }} lịch</span>
                @else
                  <span class="text-muted">0</span>
                @endif
              </td>
              <td>{!! status_badge($leave->status) !!}</td>
              <td class="small">
                @if ($leave->reviewer)
                  {{ $leave->reviewer->name }}<br><span class="text-muted">{{ $leave->reviewed_at?->format('H:i d/m/Y') }}</span>
                @else
                  —
                @endif
              </td>
              <td class="text-end">
                <a href="{{ route('staff.admin.leaves.show', $leave) }}" class="btn btn-sm {{ $leave->status === \App\Enums\LeaveStatus::Pending ? 'btn-primary' : 'btn-outline-primary' }}">
                  {{ $leave->status === \App\Enums\LeaveStatus::Pending ? 'Xem & duyệt' : 'Chi tiết' }}
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-check"></i>{{ $tab === 'pending' ? 'Không có yêu cầu nào đang chờ duyệt.' : 'Chưa có yêu cầu nghỉ nào.' }}</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($requests->hasPages())
      <div class="card-footer">{{ $requests->links() }}</div>
    @endif
  </div>
@endsection
