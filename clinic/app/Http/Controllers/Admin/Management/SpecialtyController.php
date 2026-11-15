<?php

namespace App\Http\Controllers\Admin\Management;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** QT-03: quản lý chuyên khoa. */
class SpecialtyController extends Controller
{
    public function index(Request $request)
    {
        $specialties = Specialty::query()
            ->withCount(['doctors', 'services', 'rooms'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('admin.management.specialties.index', compact('specialties'));
    }

    public function create()
    {
        return view('admin.management.specialties.form', ['specialty' => new Specialty(['sort_order' => Specialty::max('sort_order') + 1])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['image'] = $this->upload($request) ?? null;
        Specialty::create($data);

        return redirect()->route('staff.admin.specialties.index')->with('success', 'Đã thêm chuyên khoa "'.$data['name'].'".');
    }

    public function edit(Specialty $specialty)
    {
        return view('admin.management.specialties.form', compact('specialty'));
    }

    public function update(Request $request, Specialty $specialty)
    {
        $data = $this->validated($request, $specialty);
        if ($path = $this->upload($request)) {
            $data['image'] = $path;
        }
        $specialty->update($data);

        return redirect()->route('staff.admin.specialties.index')->with('success', 'Đã cập nhật chuyên khoa "'.$specialty->name.'".');
    }

    public function destroy(Specialty $specialty)
    {
        $used = $specialty->doctors()->exists() || $specialty->services()->exists() || $specialty->rooms()->exists()
            || \App\Models\Scheduling\Appointment::where('specialty_id', $specialty->id)->exists()
            || \App\Models\Clinical\Visit::where('specialty_id', $specialty->id)->exists();

        if ($used) {
            $specialty->update(['is_active' => false]);

            return back()->with('warning', 'Chuyên khoa "'.$specialty->name.'" đang có dữ liệu liên quan nên được chuyển sang ngừng hoạt động thay vì xóa.');
        }

        $specialty->delete();

        return back()->with('success', 'Đã xóa chuyên khoa "'.$specialty->name.'".');
    }

    protected function validated(Request $request, ?Specialty $specialty = null): array
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191', Rule::unique('specialties', 'slug')->ignore($specialty?->id)],
            'icon' => ['nullable', 'string', 'max:50'],
            'image_file' => ['nullable', 'image', 'max:2048'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        unset($data['image_file']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    protected function upload(Request $request): ?string
    {
        if (! $request->hasFile('image_file')) {
            return null;
        }

        $file = $request->file('image_file');
        $name = Str::random(20).'.'.$file->getClientOriginalExtension();
        $file->move(public_path('uploads/specialties'), $name);

        return 'uploads/specialties/'.$name;
    }
}
