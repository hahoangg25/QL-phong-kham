@extends('admin.layouts.app')

@section('title', 'Nhật ký hệ thống')
@section('page_title', 'Nhật ký hệ thống (audit log)')
@section('breadcrumbs', 'Quản trị / Nhật ký hệ thống')

@section('content')
  <form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-2">
      <label class="form-label">Nhóm</label>
      <select name="log_name" class="form-select">
        <option value="">Tất cả</option>
        @foreach ($logNames as $name)
          <option value="{{ $name }}" @selected(request('log_name') === $name)>{{ $logLabels[$name] ?? $name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Hành động</label>
      <select name="event" class="form-select">
        <option value="">Tất cả</option>
        @foreach ($eventLabels as $event => [$label])
          <option value="{{ $event }}" @selected(request('event') === $event)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Người thực hiện</label>
      <input type="text" name="user" value="{{ request('user') }}" class="form-control" placeholder="Tên hoặc email">
    </div>
    <div class="col-md-2">
      <label class="form-label">Đối tượng</label>
      <div class="input-group">
        <select name="subject_type" class="form-select">
          <option value="">Tất cả</option>
          @foreach ($subjectTypes as $type)
            <option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ $type }}</option>
          @endforeach
        </select>
        <input type="number" name="subject_id" value="{{ request('subject_id') }}" class="form-control" placeholder="ID" style="max-width:70px">
      </div>
    </div>
    <div class="col-md-1">
      <label class="form-label">Từ</label>
      <input type="date" name="from" value="{{ request('from') }}" class="form-control px-1">
    </div>
    <div class="col-md-1">
      <label class="form-label">Đến</label>
      <input type="date" name="to" value="{{ request('to') }}" class="form-control px-1">
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Lọc</button>
      <a href="{{ route('staff.admin.audit.index') }}" class="btn btn-light" title="Xóa lọc"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <span>Nhật ký</span>
      <small class="text-muted fw-normal">{{ number_format($logs->total()) }} bản ghi</small>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:140px">Thời gian</th>
            <th>Người thực hiện</th>
            <th>Nhóm</th>
            <th>Hành động</th>
            <th>Mô tả</th>
            <th>Đối tượng</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($logs as $log)
            @php
              [$evLabel, $evColor] = $eventLabels[$log->event] ?? [$log->event ?: 'Khác', 'secondary'];
              $props = $log->properties ? $log->properties->toArray() : [];
              $old = $props['old'] ?? [];
              $new = $props['attributes'] ?? [];
              $extra = collect($props)->except(['old', 'attributes'])->all();
              $hasDetail = $old || $new || $extra;
            @endphp
            <tr>
              <td class="small text-nowrap">{{ $log->created_at->format('H:i:s d/m/Y') }}</td>
              <td class="small">
                @if ($log->causer)
                  <span class="fw-semibold">{{ $log->causer->name ?? '#'.$log->causer_id }}</span><br>
                  <span class="text-muted">{{ $log->causer->email ?? '' }}</span>
                @else
                  <span class="text-muted"><i class="bi bi-cpu me-1"></i>Hệ thống</span>
                @endif
              </td>
              <td><span class="badge bg-light text-dark border">{{ $logLabels[$log->log_name] ?? $log->log_name }}</span></td>
              <td><span class="badge bg-{{ $evColor }}-subtle text-{{ $evColor }}-emphasis">{{ $evLabel }}</span></td>
              <td class="small" style="max-width:300px">{{ $log->description }}</td>
              <td class="small text-nowrap">
                @if ($log->subject_type)
                  {{ class_basename($log->subject_type) }} <span class="text-muted">#{{ $log->subject_id }}</span>
                @else
                  —
                @endif
              </td>
              <td class="text-end">
                @if ($hasDetail)
                  <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#log{{ $log->id }}"><i class="bi bi-chevron-down"></i></button>
                @endif
              </td>
            </tr>
            @if ($hasDetail)
              <tr class="collapse" id="log{{ $log->id }}">
                <td colspan="7" class="bg-light">
                  @if ($old || $new)
                    <table class="table table-sm table-bordered bg-white mb-2 small">
                      <thead><tr><th style="width:25%">Trường</th><th>Giá trị cũ</th><th>Giá trị mới</th></tr></thead>
                      <tbody>
                        @foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $field)
                          @php
                            $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
                          @endphp
                          <tr>
                            <td><code>{{ $field }}</code></td>
                            <td class="text-danger">{{ array_key_exists($field, $old) ? \Illuminate\Support\Str::limit($fmt($old[$field]), 200) : '' }}</td>
                            <td class="text-success">{{ array_key_exists($field, $new) ? \Illuminate\Support\Str::limit($fmt($new[$field]), 200) : '' }}</td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  @endif
                  @if ($extra)
                    <pre class="small mb-0 bg-white border rounded p-2">{{ json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                  @endif
                </td>
              </tr>
            @endif
          @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-clock-history"></i>Không có bản ghi phù hợp.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($logs->hasPages())
      <div class="card-footer">{{ $logs->links() }}</div>
    @endif
  </div>
@endsection
