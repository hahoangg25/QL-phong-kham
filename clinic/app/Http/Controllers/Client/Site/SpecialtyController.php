<?php

namespace App\Http\Controllers\Client\Site;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Specialty;

/** BN-05: tra cứu chuyên khoa. */
class SpecialtyController extends Controller
{
    public function index()
    {
        $specialties = Specialty::active()
            ->withCount(['doctors' => fn ($q) => $q->where('is_active', true)])
            ->get();

        return view('client.site.specialties.index', compact('specialties'));
    }

    public function show(Specialty $specialty)
    {
        abort_unless($specialty->is_active, 404);

        $doctors = $specialty->doctors()
            ->where('is_active', true)
            ->with(['user', 'specialty'])
            ->orderByDesc('rating_avg')
            ->get();

        $services = $specialty->services()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('price')
            ->get();

        $others = Specialty::active()->whereKeyNot($specialty->id)->get(['id', 'name', 'slug', 'icon']);

        return view('client.site.specialties.show', compact('specialty', 'doctors', 'services', 'others'));
    }
}
