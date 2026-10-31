@extends('admin.layouts.app')

@section('title', 'Check-in')
@section('page_title', 'Check-in bệnh nhân có lịch hẹn')
@section('breadcrumbs', 'Lễ tân / Check-in')

@section('content')
  @include('admin.reception._ticket_alert')

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card mb-4">
        <div class="card-header"><i class="bi bi-qr-code-scan me-1"></i>Tra cứu lịch hẹn</div>
        <div class="card-body">
          <form method="POST" action="{{ route('staff.reception.lookup') }}" id="lookupForm" data-no-disable="1">
            @csrf
            <label class="form-label">Mã lịch hẹn (quét QR) hoặc số điện thoại</label>
            <div class="input-group input-group-lg">
              <input type="text" name="q" id="lookupInput" value="{{ old('q', $q) }}" class="form-control @error('q') is-invalid @enderror" placeholder="LH... hoặc 09..." autofocus autocomplete="off">
              <button class="btn btn-primary"><i class="bi bi-search"></i></button>
            </div>
            <div class="form-text">Máy quét mã vạch/QR cầm tay sẽ tự nhập mã và nhấn Enter.</div>
          </form>
          <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="cameraBtn"><i class="bi bi-camera me-1"></i>Quét QR bằng camera</button>
          <div id="qrReader" class="mt-3" style="display:none; max-width: 360px"></div>
        </div>
      </div>

      @if ($q !== '')
        <div class="card">
          <div class="card-header">Kết quả cho "{{ $q }}"</div>
          <div class="card-body">
            @forelse ($results as $a)
              @php $late = now()->gt($a->scheduled_at->copy()->addMinutes($lateMinutes)); @endphp
              <div class="border rounded p-3 mb-3 {{ $a->appointment_date->isToday() && $a->status->isCancellable() ? 'border-primary' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <div class="fw-semibold fs-5">{{ $a->patient->full_name }}</div>
                    <div class="small text-muted">{{ $a->patient->code }} · {{ $a->patient->summary() }}</div>
                  </div>
                  {!! status_badge($a->status) !!}
                </div>
                <div class="mt-2 small">
                  <div><i class="bi bi-upc me-1"></i><a href="{{ route('staff.appointments.show', $a) }}">{{ $a->code }}</a></div>
                  <div><i class="bi bi-clock me-1"></i>{{ $a->scheduled_at->format('H:i d/m/Y') }}
                    @if ($a->appointment_date->isToday() && $late && $a->status->isCancellable())
                      <span class="badge bg-danger ms-1">Trễ - sẽ xếp cuối hàng (BR-06)</span>
                    @endif
                  </div>
                  <div><i class="bi bi-person-badge me-1"></i>{{ $a->doctor->display_name }} · {{ $a->specialty->name }} · Phòng {{ $a->doctor->room?->code ?? '-' }}</div>
                  @if ($a->symptoms)<div><i class="bi bi-chat-left-text me-1"></i>{{ $a->symptoms }}</div>@endif
                </div>
                <div class="mt-3">
                  @if (! $a->appointment_date->isToday())
                    <div class="alert alert-warning py-2 mb-0 small">Lịch hẹn ngày {{ $a->appointment_date->format('d/m/Y') }}, không phải hôm nay.</div>
                  @elseif ($a->status->isCancellable())
                    <form method="POST" action="{{ route('staff.reception.checkin.store', $a) }}">@csrf
                      <button class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Check-in & cấp số thứ tự</button>
                    </form>
                  @elseif ($a->visit)
                    <div class="d-flex align-items-center gap-2">
                      <span class="small text-muted">Đã check-in - STT {{ $a->visit->queue_number }}</span>
                      <a href="{{ route('staff.queue.ticket', $a->visit) }}" target="_blank" class="btn btn-sm btn-outline-primary ms-auto"><i class="bi bi-printer"></i> In phiếu</a>
                    </div>
                  @endif
                </div>
              </div>
            @empty
              <div class="empty-state py-3"><i class="bi bi-search"></i>Không tìm thấy lịch hẹn hôm nay phù hợp.
                <div class="mt-2"><a href="{{ route('staff.reception.walkin') }}" class="btn btn-sm btn-outline-primary">Tiếp nhận như bệnh nhân vãng lai</a></div>
              </div>
            @endforelse
          </div>
        </div>
      @endif
    </div>

    <div class="col-lg-7">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-calendar-day me-1"></i>Lịch hẹn hôm nay chưa check-in ({{ $todayList->count() }})</span>
          <input type="search" class="form-control form-control-sm w-auto" placeholder="Lọc nhanh..." id="quickFilter">
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="todayTable">
            <thead><tr><th>Giờ</th><th>Bệnh nhân</th><th>Bác sĩ</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
              @forelse ($todayList as $a)
                @php $late = now()->gt($a->scheduled_at->copy()->addMinutes($lateMinutes)); @endphp
                <tr>
                  <td class="fw-semibold">{{ $a->scheduled_at->format('H:i') }}</td>
                  <td>
                    <div>{{ $a->patient->full_name }}</div>
                    <div class="small text-muted">{{ $a->code }} · {{ $a->patient->phone }}</div>
                  </td>
                  <td><div class="small">{{ $a->doctor->display_name }}</div><div class="small text-muted">{{ $a->specialty->name }}</div></td>
                  <td>
                    {!! status_badge($a->status) !!}
                    @if ($late)<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Trễ</span>@endif
                  </td>
                  <td class="text-end">
                    <form method="POST" action="{{ route('staff.reception.checkin.store', $a) }}">@csrf
                      <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-box-arrow-in-right"></i> Check-in</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="empty-state"><i class="bi bi-check2-circle"></i>Tất cả lịch hẹn hôm nay đã được tiếp đón.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script>
    (function () {
      // Lọc nhanh danh sách hôm nay
      document.getElementById('quickFilter').addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('#todayTable tbody tr').forEach((tr) => {
          tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
      });

      // Quét QR bằng camera (html5-qrcode)
      let scanner = null;
      document.getElementById('cameraBtn').addEventListener('click', async () => {
        const box = document.getElementById('qrReader');
        if (scanner) {
          await scanner.stop().catch(() => {});
          scanner = null;
          box.style.display = 'none';
          return;
        }
        if (typeof Html5Qrcode === 'undefined') {
          alert('Không tải được thư viện quét QR. Vui lòng nhập mã thủ công.');
          return;
        }
        box.style.display = '';
        scanner = new Html5Qrcode('qrReader');
        try {
          await scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, (text) => {
            scanner.stop().catch(() => {});
            document.getElementById('lookupInput').value = text.trim();
            document.getElementById('lookupForm').submit();
          });
        } catch (err) {
          alert('Không mở được camera: ' + err);
          box.style.display = 'none';
          scanner = null;
        }
      });
    })();
  </script>
@endpush
