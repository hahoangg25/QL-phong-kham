<?php

namespace App\Http\Controllers\Client\Site;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Specialty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** BN-20: gợi ý chuyên khoa từ triệu chứng bằng so khớp từ khóa. */
class SymptomController extends Controller
{
    public function suggest(Request $request): JsonResponse
    {
        $q = mb_strtolower(trim((string) $request->input('q')));

        if (mb_strlen($q) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $suggestions = Specialty::active()->get()
            ->map(function (Specialty $s) use ($q) {
                $matched = collect($s->keywordList())
                    ->filter(fn ($k) => str_contains($q, $k) || (mb_strlen($q) >= 3 && str_contains($k, $q)))
                    ->values();

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    // Từ khóa dài (cụm từ) khớp được tính điểm cao hơn
                    'score' => $matched->sum(fn ($k) => 1 + substr_count($k, ' ')),
                    'matched' => $matched->all(),
                ];
            })
            ->filter(fn ($s) => $s['score'] > 0)
            ->sortByDesc('score')
            ->take(3)
            ->values();

        return response()->json(['suggestions' => $suggestions]);
    }
}
