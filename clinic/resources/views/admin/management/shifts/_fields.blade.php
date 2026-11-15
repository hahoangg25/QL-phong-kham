{{-- Trường nhập ca làm việc dùng chung cho form thêm và modal sửa --}}
@php $p = $shift ? 'e'.$shift->id : 'new'; @endphp
<div class="row g-2">
  <div class="col-12">
    <label class="form-label required">Thứ</label>
    <select name="weekday" class="form-select" required>
      @foreach (\App\Models\Scheduling\WorkShift::WEEKDAYS as $wd => $label)
        <option value="{{ $wd }}" @selected((string) ($shift ? $shift->weekday : old('weekday', 1)) === (string) $wd)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-6">
    <label class="form-label required">Bắt đầu</label>
    <input type="time" name="start_time" value="{{ $shift ? substr($shift->start_time, 0, 5) : old('start_time', '07:30') }}" class="form-control" required>
  </div>
  <div class="col-6">
    <label class="form-label required">Kết thúc</label>
    <input type="time" name="end_time" value="{{ $shift ? substr($shift->end_time, 0, 5) : old('end_time', '11:30') }}" class="form-control" required>
  </div>
  <div class="col-6">
    <label class="form-label required">Phút/slot</label>
    <input type="number" name="slot_minutes" min="10" max="120" step="5" value="{{ $shift?->slot_minutes ?? old('slot_minutes', 30) }}" class="form-control" required>
  </div>
  <div class="col-6">
    <label class="form-label required">Tối đa BN/slot</label>
    <input type="number" name="max_patients_per_slot" min="1" max="20" value="{{ $shift?->max_patients_per_slot ?? old('max_patients_per_slot', 2) }}" class="form-control" required>
  </div>
  <div class="col-12">
    <label class="form-label">Phòng</label>
    <select name="room_id" class="form-select">
      <option value="">-- Phòng mặc định của bác sĩ --</option>
      @foreach ($rooms as $room)
        <option value="{{ $room->id }}" @selected(($shift?->room_id ?? old('room_id')) == $room->id)>{{ $room->label() }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-6">
    <label class="form-label">Hiệu lực từ</label>
    <input type="date" name="effective_from" value="{{ $shift?->effective_from?->toDateString() }}" class="form-control">
  </div>
  <div class="col-6">
    <label class="form-label">Đến</label>
    <input type="date" name="effective_to" value="{{ $shift?->effective_to?->toDateString() }}" class="form-control">
  </div>
  <div class="col-12">
    <div class="form-check form-switch mt-1">
      <input type="hidden" name="is_active" value="0">
      <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active_{{ $p }}" @checked($shift?->is_active ?? true)>
      <label class="form-check-label" for="active_{{ $p }}">Đang áp dụng</label>
    </div>
  </div>
</div>
