@extends('client.layouts.site')

@section('title', 'Bảng giá dịch vụ')
@section('page_title', 'Bảng giá dịch vụ')
@section('page_subtitle', 'Giá khám, xét nghiệm, chẩn đoán hình ảnh và thủ thuật được niêm yết công khai')

@section('content')
  <section class="section pt-4">
    <div class="container">
      <form method="GET" action="{{ route('services.index') }}" class="card card-soft mb-4">
        <div class="card-body">
          <div class="row g-2 align-items-end">
            <div class="col-md-6">
              <label class="form-label small text-muted mb-1">Tên hoặc mã dịch vụ</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="vd: siêu âm, xét nghiệm máu...">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">Loại dịch vụ</label>
              <select name="type" class="form-select">
                <option value="">Tất cả</option>
                @foreach ($types as $value => $label)
                  <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
              <button class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Lọc</button>
              @if ($q !== '' || $type)
                <a href="{{ route('services.index') }}" class="btn btn-outline-secondary">Xóa lọc</a>
              @endif
            </div>
          </div>
        </div>
      </form>

      <div class="alert alert-light border d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle text-primary fs-5"></i>
        <div class="small">
          Phí khám thực tế tùy theo bác sĩ (xem tại <a href="{{ route('doctors.index') }}">trang bác sĩ</a>).
          Chi phí cận lâm sàng và thuốc chỉ phát sinh khi bác sĩ chỉ định; tiền đã đặt cọc online được trừ vào hóa đơn.
        </div>
      </div>

      @forelse ($groups as $group)
        <div class="card card-soft mb-4" id="loai-{{ $group['type']->value }}">
          <div class="card-header d-flex align-items-center gap-2">
            <span class="icon-circle" style="width:36px;height:36px;font-size:17px"><i class="bi {{ $group['type']->icon() }}"></i></span>
            {{ $group['type']->label() }}
            <span class="badge bg-light text-secondary ms-auto">{{ $group['services']->count() }} dịch vụ</span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:90px">Mã</th>
                  <th>Tên dịch vụ</th>
                  <th class="d-none d-md-table-cell">Chuyên khoa</th>
                  <th class="d-none d-md-table-cell">Đơn vị</th>
                  <th class="text-end">Giá</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($group['services'] as $service)
                  <tr>
                    <td class="text-muted small">{{ $service->code }}</td>
                    <td>
                      {{ $service->name }}
                      @if ($service->description)<div class="small text-muted">{{ $service->description }}</div>@endif
                    </td>
                    <td class="d-none d-md-table-cell small">
                      @if ($service->specialty)
                        <a href="{{ route('specialties.show', $service->specialty) }}">{{ $service->specialty->name }}</a>
                      @else
                        <span class="text-muted">Tất cả chuyên khoa</span>
                      @endif
                    </td>
                    <td class="d-none d-md-table-cell small text-muted">{{ $service->unit }}</td>
                    <td class="text-end fw-semibold text-nowrap">{{ money($service->price) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @empty
        <div class="text-center text-muted py-5">
          <i class="bi bi-search fs-1 d-block mb-2"></i>Không tìm thấy dịch vụ phù hợp.
        </div>
      @endforelse
    </div>
  </section>
@endsection
