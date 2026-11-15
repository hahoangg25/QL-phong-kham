<?php

namespace App\Http\Controllers\Admin\Management;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Doctor;
use App\Models\Catalog\Room;
use App\Models\Catalog\Specialty;
use App\Models\Scheduling\TimeSlot;
use App\Models\Clinical\Visit;
use App\Models\Scheduling\WorkShift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** QT-04: quản lý phòng khám. */
class RoomController extends Controller
{
    public function index(Request $request)
    {
        $rooms = Room::with('specialty')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('specialty_id'), fn ($q) => $q->where('specialty_id', $request->specialty_id))
            ->orderBy('code')
            ->paginate(20)->withQueryString();

        $doctorCounts = Doctor::whereIn('room_id', $rooms->pluck('id'))->get()->countBy('room_id');

        return view('admin.management.rooms.index', [
            'rooms' => $rooms,
            'doctorCounts' => $doctorCounts,
            'specialties' => Specialty::orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.management.rooms.form', ['room' => new Room(), 'specialties' => Specialty::orderBy('sort_order')->get()]);
    }

    public function store(Request $request)
    {
        $room = Room::create($this->validated($request));

        return redirect()->route('staff.admin.rooms.index')->with('success', 'Đã thêm phòng '.$room->code.'.');
    }

    public function edit(Room $room)
    {
        return view('admin.management.rooms.form', ['room' => $room, 'specialties' => Specialty::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Room $room)
    {
        $room->update($this->validated($request, $room));

        return redirect()->route('staff.admin.rooms.index')->with('success', 'Đã cập nhật phòng '.$room->code.'.');
    }

    public function destroy(Room $room)
    {
        $used = Doctor::where('room_id', $room->id)->exists()
            || WorkShift::where('room_id', $room->id)->exists()
            || TimeSlot::where('room_id', $room->id)->exists()
            || Visit::where('room_id', $room->id)->exists();

        if ($used) {
            $room->update(['is_active' => false]);

            return back()->with('warning', 'Phòng '.$room->code.' đang được sử dụng nên được chuyển sang ngừng hoạt động thay vì xóa.');
        }

        $room->delete();

        return back()->with('success', 'Đã xóa phòng '.$room->code.'.');
    }

    protected function validated(Request $request, ?Room $room = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('rooms', 'code')->ignore($room?->id)],
            'name' => ['required', 'string', 'max:191'],
            'floor' => ['nullable', 'string', 'max:20'],
            'specialty_id' => ['nullable', 'exists:specialties,id'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
