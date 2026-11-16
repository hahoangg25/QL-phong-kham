<?php

namespace App\Http\Controllers\Admin\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy\Medicine;
use App\Models\Clinical\PrescriptionItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** TD-07: danh mục thuốc. */
class MedicineController extends Controller
{
    public const UNITS = ['Viên', 'Gói', 'Lọ', 'Chai', 'Ống', 'Tuýp', 'Bình', 'Hộp', 'Vỉ', 'Túi'];

    public const ROUTES = ['Uống', 'Tiêm', 'Bôi ngoài da', 'Nhỏ mắt', 'Nhỏ mũi', 'Nhỏ mắt/mũi', 'Hít', 'Đặt', 'Ngậm'];

    public function index(Request $request)
    {
        $medicines = Medicine::query()
            ->withUsableStock()
            ->search(trim((string) $request->input('q')) ?: null)
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pharmacy.medicines.index', compact('medicines'));
    }

    public function create()
    {
        return view('admin.pharmacy.medicines.form', [
            'medicine' => new Medicine(['is_active' => true, 'route' => 'Uống', 'unit' => 'Viên']),
            'units' => self::UNITS,
            'routes' => self::ROUTES,
        ]);
    }

    public function store(Request $request)
    {
        $medicine = Medicine::create($this->validated($request));

        return redirect()->route('staff.medicines.index')->with('success', 'Đã thêm thuốc '.$medicine->fullName().'.');
    }

    public function edit(Medicine $medicine)
    {
        return view('admin.pharmacy.medicines.form', [
            'medicine' => $medicine,
            'units' => self::UNITS,
            'routes' => self::ROUTES,
        ]);
    }

    public function update(Request $request, Medicine $medicine)
    {
        // Bật/tắt nhanh từ danh sách
        if ($request->boolean('toggle_active')) {
            $medicine->update(['is_active' => ! $medicine->is_active]);

            return back()->with('success', ($medicine->is_active ? 'Đã kích hoạt ' : 'Đã ngừng sử dụng ').$medicine->fullName().'.');
        }

        $medicine->update($this->validated($request, $medicine));

        return redirect()->route('staff.medicines.index')->with('success', 'Đã cập nhật thuốc '.$medicine->fullName().'.');
    }

    public function destroy(Medicine $medicine)
    {
        $used = PrescriptionItem::where('medicine_id', $medicine->id)->exists() || $medicine->batches()->exists();

        if ($used) {
            $medicine->update(['is_active' => false]);

            return back()->with('warning', 'Thuốc '.$medicine->fullName().' đã có lô nhập hoặc đã được kê đơn nên không thể xóa. Hệ thống đã chuyển sang trạng thái ngừng sử dụng.');
        }

        $medicine->delete();

        return back()->with('success', 'Đã xóa thuốc '.$medicine->fullName().'.');
    }

    protected function validated(Request $request, ?Medicine $medicine = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('medicines', 'code')->ignore($medicine?->id)],
            'name' => ['required', 'string', 'max:191'],
            'active_ingredient' => ['nullable', 'string', 'max:191'],
            'strength' => ['nullable', 'string', 'max:50'],
            'dosage_form' => ['nullable', 'string', 'max:50'],
            'unit' => ['required', 'string', 'max:30'],
            'route' => ['required', 'string', 'max:30'],
            'price' => ['required', 'integer', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'manufacturer' => ['nullable', 'string', 'max:191'],
        ], [], [
            'code' => 'mã thuốc',
            'name' => 'tên thuốc',
            'active_ingredient' => 'hoạt chất',
            'strength' => 'hàm lượng',
            'dosage_form' => 'dạng bào chế',
            'route' => 'đường dùng',
            'price' => 'giá bán',
            'min_stock' => 'định mức tồn',
            'manufacturer' => 'hãng sản xuất',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
