{{-- Hộp thoại hủy lịch hộ (LT-06). Nút kích hoạt: .js-cancel với data-action, data-code, data-late --}}
<div class="modal fade" id="cancelModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content" id="cancelForm">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Hủy lịch hẹn <span id="cancelCode"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Lý do hủy</label>
        <textarea name="reason" rows="2" class="form-control" placeholder="vd: Bệnh nhân gọi điện báo bận"></textarea>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" name="count_no_show" value="1" id="countNoShow">
          <label class="form-check-label" for="countNoShow">Tính 1 lần vắng mặt (no-show) cho tài khoản bệnh nhân</label>
        </div>
        <div class="form-text" id="lateHint">Theo BR-04, hủy muộn hơn {{ setting('cancel_free_hours_before') }} giờ trước giờ khám được tính no-show.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
        <button class="btn btn-danger"><i class="bi bi-calendar-x me-1"></i>Xác nhận hủy</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
  <script>
    document.querySelectorAll('.js-cancel').forEach((btn) => btn.addEventListener('click', () => {
      const form = document.getElementById('cancelForm');
      form.action = btn.dataset.action;
      document.getElementById('cancelCode').textContent = btn.dataset.code;
      document.getElementById('countNoShow').checked = btn.dataset.late === '1';
      document.getElementById('lateHint').classList.toggle('text-danger', btn.dataset.late === '1');
      bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelModal')).show();
    }));
  </script>
@endpush
