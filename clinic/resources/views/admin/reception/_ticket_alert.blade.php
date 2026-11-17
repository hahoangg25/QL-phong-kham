{{-- Nút in phiếu số thứ tự sau khi check-in / tiếp nhận (LT-07) --}}
@if (session('ticket_url'))
  <div class="alert alert-primary d-flex align-items-center gap-2">
    <i class="bi bi-ticket-perforated fs-5"></i>
    <div class="flex-grow-1">Phiếu số thứ tự đã sẵn sàng.</div>
    <a href="{{ session('ticket_url') }}?auto=1" target="_blank" class="btn btn-sm btn-primary"><i class="bi bi-printer me-1"></i>In phiếu khám</a>
  </div>
@endif
