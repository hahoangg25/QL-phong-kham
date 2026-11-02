@extends('admin.layouts.app')

@section('title', 'Hàng đợi')
@section('page_title', $doctor ? 'Hàng đợi - '.$doctor->display_name : 'Hàng đợi hôm nay')
@section('breadcrumbs')
  <a href="{{ route('staff.queue.index') }}">Hàng đợi</a>{{ $doctor ? ' / '.$doctor->display_name : '' }}
@endsection

@section('content')
  @include('admin.reception._ticket_alert')

  <div class="filter-bar d-flex flex-wrap align-items-center gap-2">
    <select class="form-select form-select-sm w-auto" onchange="location.href = this.value">
      <option value="{{ route('staff.queue.index') }}">Tổng quan tất cả bác sĩ</option>
      @foreach ($allDoctors as $d)
        <option value="{{ route('staff.queue.index', ['doctor_id' => $d->id]) }}" @selected($doctor?->id === $d->id)>{{ $d->display_name }} - {{ $d->specialty->name }}</option>
      @endforeach
    </select>
    @if ($doctor)
      <span class="small text-muted"><i class="bi bi-door-open me-1"></i>Phòng {{ $doctor->room?->code ?? '-' }} · {{ $doctor->specialty->name }}</span>
    @endif
    <span class="ms-auto small text-muted">
      <span class="spinner-grow spinner-grow-sm text-success me-1" role="status" style="width:.6rem;height:.6rem"></span>
      Tự cập nhật · <span id="updatedAt">{{ now()->format('H:i:s') }}</span>
    </span>
    <a href="{{ route('staff.reception.walkin', $doctor ? ['doctor_id' => $doctor->id] : []) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Tiếp nhận</a>
    <a href="{{ route('lobby') }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-tv me-1"></i>Màn hình sảnh</a>
  </div>

  <div id="queueBoard">
    @include('admin.reception.queue._board')
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      const url = @json(route('staff.queue.data', $doctor ? ['doctor_id' => $doctor->id] : []));
      const board = document.getElementById('queueBoard');
      let first = true;
      App.poll(async () => {
        if (first) { first = false; return; } // lần đầu đã render sẵn từ server
        // Không làm mới khi người dùng đang thao tác trong menu
        if (board.querySelector('.dropdown-menu.show')) return;
        try {
          const data = await App.fetchJson(url);
          board.innerHTML = data.html;
          document.getElementById('updatedAt').textContent = data.updated_at;
        } catch (e) { /* giữ nguyên bảng hiện tại */ }
      }, 5000);
    })();
  </script>
@endpush
