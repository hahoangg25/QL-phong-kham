@extends('admin.layouts.app')

@section('title', 'Đơn thuốc '.$prescription->code)
@section('page_title', 'Đơn thuốc '.$prescription->code)
@section('breadcrumbs')
  <a href="{{ route('staff.pharmacy.index') }}">Cấp phát thuốc</a> / {{ $prescription->code }}
@endsection

@section('content')
  @php $patient = $prescription->patient; @endphp
  <div class="patient-banner mb-3 d-flex flex-wrap align-items-center gap-3">
    <div>
      <h3 class="h5 mb-1">{{ $patient?->full_name }}</h3>
      <div class="small opacity-75">
        {{ $patient?->code }} · {{ $patient?->genderLabel() }}
        @if ($patient?->date_of_birth) · {{ $patient->date_of_birth->format('d/m/Y') }} ({{ $patient->age }} tuổi)@endif
        @if ($patient?->phone) · {{ $patient->phone }}@endif
      </div>
    </div>
    <div class="ms-auto text-end">
      <div>{!! status_badge($prescription->status) !!}</div>
      <div class="small opacity-75 mt-1">Kê lúc {{ $prescription->created_at->format('H:i d/m/Y') }}</div>
    </div>
  </div>

  @if ($patient?->allergyList())
    <div class="allergy-alert mb-3"><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Tiền sử dị ứng:</strong> {{ implode(', ', $patient->allergyList()) }}</div>
  @endif

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header">Thông tin kê đơn</div>
        <div class="card-body row g-3">
          <div class="col-md-6">
            <div class="text-muted small">Bác sĩ</div>
            <div class="fw-semibold">{{ $prescription->doctor?->display_name }}</div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Chẩn đoán</div>
            <div>{{ $prescription->visit?->diagnoses->map->label()->implode('; ') ?: '-' }}</div>
          </div>
          @if ($prescription->note)
            <div class="col-12">
              <div class="text-muted small">Lời dặn</div>
              <div>{{ $prescription->note }}</div>
            </div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex align-items-center">
          <span>Danh sách thuốc</span>
          @if ($prescription->status->value === 'pending')
            <span class="ms-auto small text-muted fw-normal">Kiểm tra và trừ trực tiếp số lượng tồn kho</span>
          @endif
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Thuốc</th>
                <th>Liều dùng</th>
                <th class="text-center">SL</th>
                @if ($prescription->status->value === 'pending')
                  <th>Phương thức xuất</th>
                  <th>Số lượng tồn</th>
                @else
                  <th class="text-end">Thành tiền</th>
                @endif
              </tr>
            </thead>
            <tbody>
              @foreach ($prescription->items as $i => $item)
                <tr>
                  <td>{{ $i + 1 }}</td>
                  <td>
                    <div class="fw-semibold">{{ $item->medicine->fullName() }}</div>
                    <div class="small text-muted">{{ $item->medicine->active_ingredient }} · {{ $item->medicine->dosage_form }}</div>
                  </td>
                  <td class="small">
                    {{ $item->dosage }}
                    @if ($item->usage)<div class="text-muted">{{ $item->usage }}</div>@endif
                    <div class="text-muted">{{ $item->days }} ngày</div>
                  </td>
                  <td class="text-center fw-semibold">{{ $item->quantity }} <span class="small text-muted">{{ $item->medicine->unit }}</span></td>
                  @if ($prescription->status->value === 'pending')
                    @php $p = $plan[$item->id]; @endphp
                    <td class="small">
                      <div class="text-success"><i class="bi bi-box-seam me-1"></i>Trừ trực tiếp vào tồn kho ({{ $item->quantity }} {{ $item->medicine->unit }})</div>
                    </td>
                    <td>
                      @if ($p['enough'])
                        <span class="badge bg-success">Đủ tồn kho (còn {{ $p['available'] }})</span>
                      @else
                        <span class="badge bg-danger">Không đủ (còn {{ $p['available'] }})</span>
                      @endif
                    </td>
                  @else
                    <td class="text-end">{{ money($item->amount) }}</td>
                  @endif
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header">Thanh toán</div>
        <div class="card-body">
          @if ($prescription->visit?->invoice)
            <div class="d-flex justify-content-between mb-2">
              <span>Hóa đơn {{ $prescription->visit->invoice->code }}</span>
              {!! status_badge($prescription->visit->invoice->status) !!}
            </div>
            <div class="d-flex justify-content-between">
              <span class="text-muted">Tiền thuốc</span>
              <strong>{{ money($prescription->items->sum('amount')) }}</strong>
            </div>
          @else
            <span class="text-muted">Chưa lập hóa đơn.</span>
          @endif
        </div>
      </div>

      @if ($prescription->status->value === 'dispensed')
        <div class="card border-success">
          <div class="card-body">
            <div class="text-success fw-semibold mb-1"><i class="bi bi-check-circle-fill me-1"></i>Đã cấp phát</div>
            <div class="small">Bởi {{ $prescription->dispenser?->name }} lúc {{ $prescription->dispensed_at?->format('H:i d/m/Y') }}</div>
            <div class="locked-badge mt-2"><i class="bi bi-lock me-1"></i>Đơn đã cấp phát không được sửa (BR-10)</div>
          </div>
        </div>
      @elseif ($prescription->status->value === 'pending')
        <div class="card">
          <div class="card-body">
            @if (! $isPaid)
              <div class="alert alert-warning py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i>Đơn thuốc chưa được thanh toán, vui lòng hướng dẫn bệnh nhân thanh toán tại quầy thu ngân.</div>
            @elseif (! $canDispense)
              <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-octagon me-1"></i>Không đủ tồn kho còn hạn cho một số thuốc.</div>
            @endif
            <form method="POST" action="{{ route('staff.pharmacy.dispense', $prescription) }}" data-confirm="Xác nhận cấp phát đơn {{ $prescription->code }} và trừ kho?">
              @csrf
              <button class="btn btn-primary w-100" @disabled(! $canDispense)><i class="bi bi-box-arrow-right me-1"></i>Cấp phát thuốc</button>
            </form>
          </div>
        </div>
      @endif
      <a href="{{ route('staff.pharmacy.index') }}" class="btn btn-link px-0 mt-2"><i class="bi bi-arrow-left me-1"></i>Quay lại danh sách</a>
    </div>
  </div>
@endsection
