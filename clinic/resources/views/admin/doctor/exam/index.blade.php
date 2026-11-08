@extends('admin.layouts.app')

@section('title', 'Khám bệnh')
@section('page_title', 'Khám bệnh - Hàng đợi hôm nay')

@section('content')
  @if (! $doctor)
    <div class="card">
      <div class="empty-state">
        <i class="bi bi-person-badge"></i>
        <h5 class="text-body">Tài khoản chưa có hồ sơ bác sĩ</h5>
        <p class="mb-0">Chức năng khám bệnh chỉ dành cho tài khoản được gán hồ sơ bác sĩ (Quản trị &rsaquo; Bác sĩ).</p>
      </div>
    </div>
  @else
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1">{{ $doctor->display_name }}</h2>
        <div class="text-muted small">
          <i class="bi bi-hospital me-1"></i>{{ $doctor->specialty->name }}
          @if ($doctor->room) · <i class="bi bi-door-open me-1"></i>Phòng {{ $doctor->room->code }} @endif
          · <span id="realtimeState"><i class="bi bi-arrow-repeat me-1"></i>Tự động cập nhật</span>
        </div>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ route('staff.schedule.index') }}" class="btn btn-outline-secondary"><i class="bi bi-calendar3 me-1"></i>Lịch làm việc</a>
        <form method="POST" action="{{ route('staff.exam.call-next') }}">
          @csrf
          <button class="btn btn-primary"><i class="bi bi-megaphone me-1"></i>Gọi bệnh nhân tiếp theo</button>
        </form>
      </div>
    </div>

    <div id="queueBoard">
      @include('admin.doctor.exam._queue')
    </div>
  @endif
@endsection

@if ($doctor)
  @push('scripts')
    <script>
      (function () {
        const board = document.getElementById('queueBoard');
        const url = @json(route('staff.exam.index', ['partial' => 1]));
        let first = true;
        App.poll(async function () {
          if (first) { first = false; return; } // lần đầu đã render phía server
          try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) board.innerHTML = await res.text();
          } catch (e) { /* bỏ qua lỗi mạng tạm thời */ }
        }, 10000);
        if (App.realtime) document.getElementById('realtimeState').innerHTML = '<i class="bi bi-broadcast me-1 text-success"></i>Realtime';
      })();
    </script>
  @endpush
@endif
