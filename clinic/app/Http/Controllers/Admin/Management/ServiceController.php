<?php

namespace App\Http\Controllers\Admin\Management;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Service;
use App\Models\Clinical\ServiceOrder;
use App\Models\Catalog\Specialty;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** QT-06: quản lý dịch vụ khám, cận lâm sàng và bảng giá. */
class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Service::with('specialty')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('specialty_id'), fn ($q) => $q->where('specialty_id', $request->specialty_id))
            ->orderBy('type')->orderBy('code')
            ->paginate(20)->withQueryString();

        return view('admin.management.services.index', [
            'services' => $services,
            'types' => ServiceType::options(),
            'specialties' => Specialty::orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.management.services.form', $this->formData(new Service(['type' => ServiceType::Lab, 'unit' => 'lần'])));
    }

    public function store(Request $request)
    {
        $service = Service::create($this->validated($request));

        return redirect()->route('staff.admin.services.index')->with('success', 'Đã thêm dịch vụ "'.$service->name.'".');
    }

    public function edit(Service $service)
    {
        return view('admin.management.services.form', $this->formData($service));
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request, $service));

        return redirect()->route('staff.admin.services.index')->with('success', 'Đã cập nhật dịch vụ "'.$service->name.'".');
    }

    public function destroy(Service $service)
    {
        if (ServiceOrder::where('service_id', $service->id)->exists()) {
            $service->update(['is_active' => false]);

            return back()->with('warning', 'Dịch vụ "'.$service->name.'" đã được chỉ định cho bệnh nhân nên được chuyển sang ngừng áp dụng thay vì xóa.');
        }

        $service->delete();

        return back()->with('success', 'Đã xóa dịch vụ "'.$service->name.'".');
    }

    protected function formData(Service $service): array
    {
        return [
            'service' => $service,
            'types' => ServiceType::options(),
            'specialties' => Specialty::orderBy('sort_order')->get(),
        ];
    }

    protected function validated(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('services', 'code')->ignore($service?->id)],
            'name' => ['required', 'string', 'max:191'],
            'type' => ['required', Rule::enum(ServiceType::class)],
            'specialty_id' => ['nullable', 'exists:specialties,id'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'unit' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
