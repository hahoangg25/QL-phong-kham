{{-- ================================================================= --}}
{{-- BLOCK CỐ ĐỊNH: SIDEBAR ĐIỀU HƯỚNG QUẢN TRỊ & NHÂN VIÊN (STAFF/ADMIN)--}}
{{-- ================================================================= --}}
@php
  $u = auth()->user();
  $groups = [
      '' => [
          ['staff.dashboard', 'bi-speedometer2', 'Tổng quan', null, 'staff.dashboard'],
      ],
      'Lễ tân & Thu ngân' => [
          ['staff.appointments.index', 'bi-calendar2-week', 'Lịch hẹn', 'appointments.view', 'staff.appointments.*'],
          ['staff.reception.checkin', 'bi-qr-code-scan', 'Check-in', 'reception.manage', 'staff.reception.checkin*'],
          ['staff.reception.walkin', 'bi-person-plus', 'Tiếp nhận vãng lai', 'reception.manage', 'staff.reception.walkin*'],
          ['staff.queue.index', 'bi-list-ol', 'Hàng đợi', 'reception.manage', 'staff.queue.*'],
          ['staff.patients.index', 'bi-person-vcard', 'Bệnh nhân', 'patients.view', 'staff.patients.*'],
          ['staff.cashier.index', 'bi-cash-coin', 'Quầy thu ngân', 'billing.manage', ['staff.cashier.index', 'staff.cashier.show']],
          ['staff.cashier.report', 'bi-bar-chart-line', 'Báo cáo thu theo ca', 'billing.manage', 'staff.cashier.report'],
      ],
      'Bác sĩ' => [
          ['staff.exam.index', 'bi-clipboard2-pulse', 'Khám bệnh', 'medical.examine', 'staff.exam.*'],
          ['staff.schedule.index', 'bi-calendar3', 'Lịch làm việc', 'medical.examine', 'staff.schedule.*'],
          ['staff.leave.index', 'bi-calendar-x', 'Nghỉ / đổi ca', 'leave.request', 'staff.leave.*'],
      ],
      'Cận lâm sàng' => [
          ['staff.lab.index', 'bi-droplet-half', 'Chỉ định CLS & X-Quang', 'lab.manage', 'staff.lab.*'],
      ],
      'Dược & kho' => [
          ['staff.pharmacy.index', 'bi-capsule', 'Cấp phát thuốc', 'pharmacy.dispense', 'staff.pharmacy.*'],
          ['staff.medicines.index', 'bi-prescription2', 'Danh mục thuốc', 'inventory.manage', 'staff.medicines.*'],
          ['staff.receipts.index', 'bi-box-arrow-in-down', 'Nhập kho', 'inventory.manage', 'staff.receipts.*'],
          ['staff.inventory.index', 'bi-boxes', 'Tồn kho & cảnh báo', 'inventory.manage', ['staff.inventory.index', 'staff.inventory.show']],
          ['staff.inventory.report', 'bi-file-earmark-bar-graph', 'Báo cáo nhập - xuất - tồn', 'inventory.manage', 'staff.inventory.report'],
      ],
      'Quản trị' => [
          ['staff.admin.reports.index', 'bi-graph-up-arrow', 'Báo cáo thống kê', 'reports.view', 'staff.admin.reports.*'],
          ['staff.admin.specialties.index', 'bi-hospital', 'Chuyên khoa', 'catalog.manage', 'staff.admin.specialties.*'],
          ['staff.admin.rooms.index', 'bi-door-open', 'Phòng khám', 'catalog.manage', 'staff.admin.rooms.*'],
          ['staff.admin.doctors.index', 'bi-person-badge', 'Bác sĩ', 'catalog.manage', 'staff.admin.doctors.*'],
          ['staff.admin.services.index', 'bi-tags', 'Dịch vụ & bảng giá', 'catalog.manage', 'staff.admin.services.*'],
          ['staff.admin.icd10.index', 'bi-journal-medical', 'Danh mục bệnh lý', 'catalog.manage', 'staff.admin.icd10.*'],
          ['staff.admin.shifts.index', 'bi-calendar-week', 'Ca làm việc', 'schedule.manage', 'staff.admin.shifts.*'],
          ['staff.admin.slots.index', 'bi-grid-3x3-gap', 'Khung giờ khám', 'schedule.manage', 'staff.admin.slots.*'],
          ['staff.admin.leaves.index', 'bi-calendar-check', 'Duyệt nghỉ bác sĩ', 'schedule.manage', 'staff.admin.leaves.*'],
          ['staff.admin.staff.index', 'bi-people', 'Nhân viên', 'staff.manage', 'staff.admin.staff.*'],
          ['staff.admin.roles.index', 'bi-shield-lock', 'Phân quyền', 'staff.manage', 'staff.admin.roles.*'],
          ['staff.admin.patient-accounts.index', 'bi-person-lock', 'Tài khoản bệnh nhân', 'staff.manage', 'staff.admin.patient-accounts.*'],
          ['staff.admin.reviews.index', 'bi-star-half', 'Đánh giá', 'reviews.manage', 'staff.admin.reviews.*'],
          ['staff.admin.settings.edit', 'bi-sliders', 'Cấu hình quy tắc', 'settings.manage', 'staff.admin.settings.*'],
          ['staff.admin.audit.index', 'bi-clock-history', 'Nhật ký hệ thống', 'audit.view', 'staff.admin.audit.*'],
      ],
  ];
@endphp

<aside class="staff-sidebar" id="staffSidebar">
  <a href="{{ route('staff.dashboard') }}" class="sidebar-brand">
    <span class="logo-mark"><i class="fa-solid fa-tooth"></i></span>
    <span>Lộc Phát <small>Dental Clinic</small></span>
  </a>
  <nav class="sidebar-nav">
    @foreach ($groups as $group => $items)
      @php $visible = collect($items)->filter(fn ($i) => ! $i[3] || $u->can($i[3])); @endphp
      @if ($visible->isNotEmpty())
        @if ($group)<div class="sidebar-heading">{{ $group }}</div>@endif
        @foreach ($visible as [$route, $icon, $label, $perm, $pattern])
          <a href="{{ route($route) }}" class="sidebar-link {{ request()->routeIs(...(array) $pattern) ? 'active' : '' }}">
            <i class="bi {{ $icon }}"></i><span>{{ $label }}</span>
          </a>
        @endforeach
      @endif
    @endforeach
    <div class="sidebar-heading">Tiện ích</div>
    <a href="{{ route('lobby') }}" target="_blank" class="sidebar-link"><i class="bi bi-tv"></i><span>Màn hình sảnh</span></a>
    <a href="{{ route('home') }}" target="_blank" class="sidebar-link"><i class="bi bi-globe2"></i><span>Cổng bệnh nhân</span></a>
  </nav>
</aside>
