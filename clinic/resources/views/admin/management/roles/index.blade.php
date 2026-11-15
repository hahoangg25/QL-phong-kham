@extends('admin.layouts.app')

@section('title', 'Phân quyền')
@section('page_title', 'Phân quyền theo vai trò (RBAC)')
@section('breadcrumbs', 'Quản trị / Nhân sự / Phân quyền')

@section('content')
  <form method="POST" action="{{ route('staff.admin.roles.update') }}" class="card">
    @csrf
    @method('PUT')
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Ma trận vai trò × quyền</span>
      <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Lưu phân quyền</button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="min-width:300px">Quyền</th>
            @foreach ($roleLabels as $role => $label)
              <th class="text-center" style="min-width:110px">
                {{ $label }}<br>
                <small class="text-muted fw-normal text-lowercase">{{ $userCounts[$role] ?? 0 }} người</small>
              </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach ($groups as $group => $permissions)
            <tr class="table-light">
              <td colspan="{{ count($roleLabels) + 1 }}" class="fw-semibold text-primary small text-uppercase">{{ $group }}</td>
            </tr>
            @foreach ($permissions as $permission => $label)
              <tr>
                <td>
                  {{ $label }}<br><code class="small text-muted">{{ $permission }}</code>
                </td>
                @foreach ($roleLabels as $role => $roleLabel)
                  <td class="text-center">
                    @if ($role === 'admin')
                      <input class="form-check-input" type="checkbox" checked disabled title="Quản trị viên luôn có toàn quyền">
                    @else
                      <input class="form-check-input perm-check" type="checkbox" name="permissions[{{ $role }}][]" value="{{ $permission }}"
                             data-role="{{ $role }}" @checked(in_array($permission, $matrix[$role] ?? [], true))>
                    @endif
                  </td>
                @endforeach
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Quản trị viên luôn có toàn quyền. Thay đổi có hiệu lực ngay và được ghi vào nhật ký hệ thống.</small>
      <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu phân quyền</button>
    </div>
  </form>
@endsection
