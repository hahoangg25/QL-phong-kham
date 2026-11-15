{{-- Badge trạng thái hoạt động: @include('admin.management.partials.status', ['active' => $x->is_active]) --}}
@if ($active)
  <span class="badge badge-status bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle me-1"></i>{{ $on ?? 'Hoạt động' }}</span>
@else
  <span class="badge badge-status bg-secondary-subtle text-secondary-emphasis"><i class="bi bi-pause-circle me-1"></i>{{ $off ?? 'Ngừng' }}</span>
@endif
