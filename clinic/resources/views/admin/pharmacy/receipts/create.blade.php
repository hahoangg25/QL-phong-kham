@extends('admin.layouts.app')

@section('title', 'Lập phiếu nhập kho')
@section('page_title', 'Lập phiếu nhập kho')
@section('breadcrumbs')
  <a href="{{ route('staff.receipts.index') }}">Nhập kho</a> / Lập phiếu
@endsection

@php
  $medicineLabel = fn ($m) => $m->code.' - '.$m->name.($m->strength ? ' '.$m->strength : '').' ('.$m->unit.')';
  $oldItems = old('items', [['medicine_id' => '', 'batch_no' => '', 'expiry_date' => '', 'quantity' => '', 'import_price' => '']]);
  $byId = $medicines->keyBy('id');
@endphp

@section('content')
  <form method="POST" action="{{ route('staff.receipts.store') }}" id="receiptForm">
    @csrf
    <div class="card mb-3">
      <div class="card-header">Thông tin phiếu nhập</div>
      <div class="card-body row g-3">
        <div class="col-md-5">
          <label class="form-label required">Nhà cung cấp</label>
          <input type="text" name="supplier" value="{{ old('supplier') }}" class="form-control @error('supplier') is-invalid @enderror" required maxlength="191">
        </div>
        <div class="col-md-3">
          <label class="form-label required">Ngày nhập</label>
          <input type="date" name="received_date" value="{{ old('received_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control @error('received_date') is-invalid @enderror" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Ghi chú</label>
          <input type="text" name="note" value="{{ old('note') }}" class="form-control" maxlength="500">
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex align-items-center">
        <span>Danh sách thuốc nhập kho (Cộng dồn số lượng tồn kho)</span>
        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="addRow"><i class="bi bi-plus-lg me-1"></i>Thêm dòng</button>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th style="min-width:320px">Thuốc</th>
              <th style="width:160px">Số lượng nhập</th>
              <th style="width:180px">Giá nhập (đ)</th>
              <th class="text-end" style="width:180px">Thành tiền</th>
              <th style="width:50px"></th>
            </tr>
          </thead>
          <tbody id="rows">
            @foreach ($oldItems as $i => $item)
              <tr class="receipt-row">
                <td>
                  <input type="text" class="form-control med-search @error("items.$i.medicine_id") is-invalid @enderror" list="medicineList" placeholder="Gõ mã hoặc tên thuốc..."
                         value="{{ isset($byId[$item['medicine_id'] ?? null]) ? $medicineLabel($byId[$item['medicine_id']]) : '' }}" required>
                  <input type="hidden" name="items[{{ $i }}][medicine_id]" class="med-id" value="{{ $item['medicine_id'] ?? '' }}">
                  <input type="hidden" name="items[{{ $i }}][batch_no]" value="{{ $item['batch_no'] ?? 'NK' . date('Ymd') }}">
                  <input type="hidden" name="items[{{ $i }}][expiry_date]" value="{{ $item['expiry_date'] ?? now()->addYears(2)->toDateString() }}">
                </td>
                <td><input type="number" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? '' }}" min="1" class="form-control qty @error("items.$i.quantity") is-invalid @enderror" placeholder="VD: 100" required></td>
                <td><input type="number" name="items[{{ $i }}][import_price]" value="{{ $item['import_price'] ?? '' }}" min="0" step="100" class="form-control price @error("items.$i.import_price") is-invalid @enderror" placeholder="Giá nhập" required></td>
                <td class="text-end line-total">0 đ</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Xóa dòng"><i class="bi bi-x-lg"></i></button></td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="text-end fw-bold">Tổng giá trị phiếu nhập</td>
              <td class="text-end fw-bold text-primary" id="grandTotal">0 đ</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu phiếu nhập</button>
        <a href="{{ route('staff.receipts.index') }}" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </div>
  </form>

  <datalist id="medicineList">
    @foreach ($medicines as $m)
      <option value="{{ $medicineLabel($m) }}" data-id="{{ $m->id }}" data-price="{{ $m->price }}"></option>
    @endforeach
  </datalist>
@endsection

@push('scripts')
  <script>
    (function () {
      const rows = document.getElementById('rows');
      const options = Array.from(document.querySelectorAll('#medicineList option'));
      const byLabel = Object.fromEntries(options.map((o) => [o.value, o]));
      let index = rows.querySelectorAll('.receipt-row').length;

      function recalc() {
        let total = 0;
        rows.querySelectorAll('.receipt-row').forEach((tr) => {
          const line = (Number(tr.querySelector('.qty').value) || 0) * (Number(tr.querySelector('.price').value) || 0);
          tr.querySelector('.line-total').textContent = App.money(line);
          total += line;
        });
        document.getElementById('grandTotal').textContent = App.money(total);
      }

      function bindMedicine(input) {
        const hidden = input.closest('td').querySelector('.med-id');
        input.addEventListener('change', () => {
          const opt = byLabel[input.value];
          hidden.value = opt ? opt.dataset.id : '';
          input.classList.toggle('is-invalid', !opt);
          const price = input.closest('tr').querySelector('.price');
          if (opt && !price.value) {
            price.value = Math.round(Number(opt.dataset.price) * 0.7 / 100) * 100;
            recalc();
          }
        });
      }

      document.getElementById('addRow').addEventListener('click', () => {
        const tpl = rows.querySelector('.receipt-row').cloneNode(true);
        tpl.querySelectorAll('input').forEach((el) => {
          el.value = '';
          el.classList.remove('is-invalid');
          if (el.name) el.name = el.name.replace(/items\[\d+\]/, 'items[' + index + ']');
        });
        index++;
        rows.appendChild(tpl);
        bindMedicine(tpl.querySelector('.med-search'));
        recalc();
      });

      rows.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-row');
        if (!btn) return;
        if (rows.querySelectorAll('.receipt-row').length > 1) {
          btn.closest('tr').remove();
          recalc();
        }
      });

      rows.addEventListener('input', (e) => {
        if (e.target.matches('.qty, .price')) recalc();
      });

      document.getElementById('receiptForm').addEventListener('submit', (e) => {
        const invalid = Array.from(rows.querySelectorAll('.med-id')).some((h) => !h.value);
        if (invalid) {
          e.preventDefault();
          e.stopImmediatePropagation();
          alert('Vui lòng chọn thuốc từ danh sách gợi ý cho tất cả các dòng.');
        }
      }, true);

      rows.querySelectorAll('.med-search').forEach(bindMedicine);
      recalc();
    })();
  </script>
@endpush
