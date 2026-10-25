<?php

namespace App\Http\Controllers\Client\Site;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Service;
use Illuminate\Http\Request;

/** BN-05: bảng giá dịch vụ khám, cận lâm sàng, thủ thuật. */
class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $type = ServiceType::tryFrom((string) $request->query('type'));

        $services = Service::active()
            ->with('specialty')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', $q)))
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->orderBy('price')
            ->get()
            ->groupBy(fn (Service $s) => $s->type->value);

        // Giữ thứ tự nhóm theo khai báo enum
        $groups = collect(ServiceType::cases())
            ->filter(fn (ServiceType $t) => $services->has($t->value))
            ->map(fn (ServiceType $t) => ['type' => $t, 'services' => $services->get($t->value)]);

        return view('client.site.services.index', [
            'groups' => $groups,
            'types' => ServiceType::options(),
            'q' => $q,
            'type' => $type?->value,
        ]);
    }
}
