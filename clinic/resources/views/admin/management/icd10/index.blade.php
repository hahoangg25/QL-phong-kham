@extends('admin.layouts.app')

@section('title', 'Danh mục bệnh lý')
@section('page_title', 'Danh mục bệnh lý')
@section('breadcrumbs', 'Quản trị / Danh mục / Bệnh lý')

@section('content')
  <div class="row g-4">
    <div class="col-lg-8">
      <form class="filter-bar row g-2 align-items-end" method="GET">
        <div class="col-md-6">
          <label class="form-label">Tra cứu</label>
          <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Mã (vd: MB01) hoặc tên bệnh">
        </div>
        <div class="col-md-4">
          <label class="form-label">Chương</label>
          <select name="chapter" class="form-select">
            <option value="">Tất cả</option>
            @foreach ($chapters as $ch)
              <option value="{{ $ch }}" @selected(request('chapter') === $ch)>{{ $ch }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
      </form>

      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <span>Danh sách mã bệnh</span>
          <small class="text-muted fw-normal">Tổng cộng {{ number_format($total) }} mã</small>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="width:100px">Mã bệnh</th>
                <th>Tên bệnh</th>
                <th>Chương</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              @forelse ($codes as $code)
                <tr>
                  <td><span class="badge bg-primary-subtle text-primary-emphasis fs-6">{{ $code->code }}</span></td>
                  <td>
                    {{ $code->name }}
                    @if ($code->name_en)<br><small class="text-muted fst-italic">{{ $code->name_en }}</small>@endif
                  </td>
                  <td class="small text-muted">{{ $code->chapter }}</td>
                  <td class="text-end">
                    <form method="POST" action="{{ route('staff.admin.icd10.destroy', $code) }}" data-confirm="Xóa mã {{ $code->code }}? Các chẩn đoán đã ghi vẫn được giữ nguyên.">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="4"><div class="empty-state"><i class="bi bi-journal-x"></i>Không tìm thấy mã phù hợp.</div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($codes->hasPages())
          <div class="card-footer">{{ $codes->links() }}</div>
        @endif
      </div>
    </div>

    <div class="col-lg-4">
      <form method="POST" action="{{ route('staff.admin.icd10.store') }}" class="card mb-4">
        @csrf
        <div class="card-header"><i class="bi bi-plus-circle me-1"></i>Thêm mã bệnh mới</div>
        <div class="card-body">
          <div class="mb-2">
            <label class="form-label required">Mã bệnh</label>
            <input type="text" name="code" value="{{ old('code') }}" class="form-control text-uppercase" maxlength="10" required placeholder="MB01">
          </div>
          <div class="mb-2">
            <label class="form-label required">Tên bệnh (tiếng Việt)</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Tên tiếng Anh</label>
            <input type="text" name="name_en" value="{{ old('name_en') }}" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Chương</label>
            <input type="text" name="chapter" value="{{ old('chapter') }}" class="form-control" list="chapterList">
            <datalist id="chapterList">@foreach ($chapters as $ch)<option value="{{ $ch }}">@endforeach</datalist>
          </div>
          <button class="btn btn-primary w-100">Thêm mã</button>
        </div>
      </form>

      <form method="POST" action="{{ route('staff.admin.icd10.import') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="card-header"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import từ file CSV</div>
        <div class="card-body">
          <input type="file" name="file" accept=".csv,.txt" class="form-control mb-2" required>
          <div class="small text-muted mb-3">
            Mỗi dòng: <code>mã,tên tiếng Việt,tên tiếng Anh,chương</code> (2 cột cuối không bắt buộc).
            Có thể có dòng tiêu đề. Mã đã tồn tại sẽ được cập nhật. Mã hóa UTF-8.
          </div>
          <button class="btn btn-outline-primary w-100"><i class="bi bi-upload me-1"></i>Import</button>
        </div>
      </form>
    </div>
  </div>
@endsection
